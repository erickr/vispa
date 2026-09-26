<?php

namespace App\Console\Commands;

use App\Models\Unit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('units:check-translations')]
#[Description('List units missing a translation for a supported locale')]
class CheckUnitTranslations extends Command
{
    public function handle(): int
    {
        $missing = Unit::missingTranslations();

        if ($missing->isEmpty()) {
            $this->components->info('Every unit has a translation for each supported locale.');

            return self::SUCCESS;
        }

        $this->components->error('Some units are missing translations.');
        $this->table(['Unit', 'Missing locales'], $missing->map(fn (array $locales, string $code): array => [$code, implode(', ', $locales)]));

        return self::FAILURE;
    }
}
