<?php

namespace App\Jobs;

use App\Models\RecipeImport;
use App\Models\RecipeRevision;
use App\Models\Unit;
use App\Models\User;
use App\Recipes\Import\ImportLimitReached;
use App\Recipes\Import\RecipeExtractor;
use App\Recipes\Import\RecipeImporter;
use App\Recipes\Import\RecipeImportException;
use App\Recipes\Import\RecipePageReader;
use App\Support\SafeUrlFetcher;
use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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

    /**
     * Record the attempt and queue it; the returned row is what the status page polls. With
     * $into, the page fills that link-only revision instead of becoming a new recipe.
     *
     * Every way in comes through here, so this is where the user's import budget is spent.
     *
     * @throws ImportLimitReached when the budget is used up; nothing is recorded or queued.
     */
    public static function start(User $user, string $url, ?RecipeRevision $into = null): RecipeImport
    {
        static::spendAllowance($user);

        $import = RecipeImport::create([
            'user_id' => $user->getKey(),
            'source_type' => 'url',
            'source_url' => $url,
            'status' => RecipeImport::STATUS_PENDING,
            'recipe_id' => $into?->recipe_id,
            'recipe_revision_id' => $into?->getKey(),
        ]);

        static::dispatch($import);

        return $import;
    }

    /**
     * start() for the panel's buttons: a used-up budget becomes a notification rather than an
     * error page, and null tells the caller to stay where it is. $into may be a closure, called
     * only once the import is allowed, for a caller that has to write a revision to fill.
     *
     * @param  RecipeRevision|(Closure(): RecipeRevision)|null  $into
     */
    public static function startOrNotify(User $user, string $url, RecipeRevision|Closure|null $into = null): ?RecipeImport
    {
        try {
            static::ensureAllowance($user);

            return static::start($user, $url, value($into));
        } catch (ImportLimitReached $e) {
            $e->notification()->send();

            return null;
        }
    }

    /**
     * An hourly and a daily allowance per user (services.anthropic.imports_per_*). Failed imports
     * count too: the model call may already have been paid for.
     *
     * @return array<string, array{0: int, 1: int}> rate limiter key => [max attempts, decay seconds]
     */
    private static function allowances(User $user): array
    {
        return [
            "recipe-imports:hour:{$user->getKey()}" => [(int) config('services.anthropic.imports_per_hour'), 3600],
            "recipe-imports:day:{$user->getKey()}" => [(int) config('services.anthropic.imports_per_day'), 86400],
        ];
    }

    /** @throws ImportLimitReached */
    private static function ensureAllowance(User $user): void
    {
        foreach (static::allowances($user) as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw new ImportLimitReached(RateLimiter::availableIn($key));
            }
        }
    }

    /**
     * Both windows are checked before either is counted, so a refused attempt costs nothing.
     *
     * @throws ImportLimitReached
     */
    private static function spendAllowance(User $user): void
    {
        static::ensureAllowance($user);

        foreach (static::allowances($user) as $key => [, $decaySeconds]) {
            RateLimiter::hit($key, $decaySeconds);
        }
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

            // A recipe set from the start is the one to fill. Its revision gone by now (deleted
            // while the job waited) is a failure, not a reason to start a second recipe.
            $revision = match (true) {
                $import->recipe_id === null => $importer->import($extracted, $import->user, $import->source_url),
                $import->revision !== null => $importer->importInto($extracted, $import->revision, $import->user),
                default => throw new RecipeImportException(__('recipe.import.errors.unexpected')),
            };

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
