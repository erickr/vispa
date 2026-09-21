<?php

namespace App\Jobs;

use App\Models\RecipeImport;
use App\Models\Unit;
use App\Models\User;
use App\Recipes\Import\RecipeExtractor;
use App\Recipes\Import\RecipeImporter;
use App\Recipes\Import\RecipeImportException;
use App\Recipes\Import\RecipePageReader;
use App\Support\SafeUrlFetcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetch → read → extract (Claude) → write the draft. Runs on the queue because the model call
 * can take tens of seconds; the status page polls the RecipeImport row.
 */
class ImportRecipe implements ShouldQueue
{
    use Queueable;

    /** Every attempt is a paid model call, so a failure is reported rather than retried. */
    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public RecipeImport $import) {}

    /** Record the attempt and queue it; the returned row is what the status page polls. */
    public static function start(User $user, string $url): RecipeImport
    {
        $import = RecipeImport::create([
            'user_id' => $user->getKey(),
            'source_type' => 'url',
            'source_url' => $url,
            'status' => RecipeImport::STATUS_PENDING,
        ]);

        static::dispatch($import);

        return $import;
    }

    public function handle(SafeUrlFetcher $fetcher, RecipePageReader $reader, RecipeExtractor $extractor, RecipeImporter $importer): void
    {
        $import = $this->import;
        // Error messages are shown to the importer, so write them in their language.
        App::setLocale($import->user->preferredLocale());
        $import->update(['status' => RecipeImport::STATUS_RUNNING, 'error' => null]);

        try {
            try {
                $html = $fetcher->fetchPage($import->source_url);
            } catch (Throwable $e) {
                throw new RecipeImportException(__('recipe.import.errors.unreachable'), previous: $e);
            }

            $extracted = $extractor->extract($reader->read($html, $import->source_url), Unit::query()->orderBy('code')->pluck('code')->all());

            $import->fill([
                'model' => $extracted->model,
                'input_tokens' => $extracted->inputTokens,
                'output_tokens' => $extracted->outputTokens,
            ]);

            $revision = $importer->import($extracted, $import->user, $import->source_url);

            $import->update([
                'status' => RecipeImport::STATUS_DONE,
                'recipe_id' => $revision->recipe_id,
                'recipe_revision_id' => $revision->getKey(),
            ]);
        } catch (RecipeImportException $e) {
            $import->update(['status' => RecipeImport::STATUS_FAILED, 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::error('Recipe import failed', ['import' => $import->uuid, 'error' => $e->getMessage()]);
            $import->update(['status' => RecipeImport::STATUS_FAILED, 'error' => __('recipe.import.errors.unexpected')]);
        }
    }

    public function failed(?Throwable $e): void
    {
        // A timeout kills handle() before its catch runs; don't leave the status page spinning.
        if (! $this->import->fresh()?->isFinished()) {
            $this->import->update(['status' => RecipeImport::STATUS_FAILED, 'error' => __('recipe.import.errors.unexpected')]);
        }
    }
}
