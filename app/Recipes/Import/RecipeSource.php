<?php

namespace App\Recipes\Import;

/**
 * What the extractor reads: one recipe, in whatever shape it arrived. A web page becomes
 * either its schema.org JSON-LD or its readable text; PDFs, photos and pasted text plug in
 * through the other constructors without the extractor or importer changing.
 */
final class RecipeSource
{
    public const KIND_JSON_LD = 'json_ld';

    public const KIND_TEXT = 'text';

    public const KIND_PDF = 'pdf';

    public const KIND_IMAGE = 'image';

    private function __construct(
        public readonly string $kind,
        public readonly string $content,
        public readonly ?string $mediaType = null,
        public readonly ?string $url = null,
        public readonly ?string $languageHint = null,
        public readonly ?string $imageUrl = null,
    ) {}

    public static function fromJsonLd(string $json, ?string $url = null, ?string $languageHint = null): self
    {
        return new self(self::KIND_JSON_LD, $json, url: $url, languageHint: $languageHint);
    }

    public static function fromText(string $text, ?string $url = null, ?string $languageHint = null, ?string $imageUrl = null): self
    {
        return new self(self::KIND_TEXT, $text, url: $url, languageHint: $languageHint, imageUrl: $imageUrl);
    }

    public static function fromPdf(string $bytes): self
    {
        return new self(self::KIND_PDF, base64_encode($bytes), 'application/pdf');
    }

    public static function fromImage(string $bytes, string $mediaType): self
    {
        return new self(self::KIND_IMAGE, base64_encode($bytes), $mediaType);
    }

    /**
     * The user-turn content blocks for the Messages API. The source is untrusted page content,
     * so it is fenced and labelled as data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function contentBlocks(): array
    {
        $context = array_filter([
            $this->url ? "Source URL: {$this->url}" : null,
            $this->languageHint ? "Page language attribute: {$this->languageHint}" : null,
            $this->imageUrl ? "Page image: {$this->imageUrl}" : null,
        ]);

        return match ($this->kind) {
            self::KIND_PDF => [
                ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => $this->mediaType, 'data' => $this->content]],
                ['type' => 'text', 'text' => 'Extract the recipe from this document.'],
            ],
            self::KIND_IMAGE => [
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $this->mediaType, 'data' => $this->content]],
                ['type' => 'text', 'text' => 'Extract the recipe from this photo.'],
            ],
            default => [[
                'type' => 'text',
                'text' => implode("\n", [
                    ...$context,
                    $this->kind === self::KIND_JSON_LD
                        ? 'The page\'s schema.org Recipe data:'
                        : 'The page\'s readable text:',
                    '<source>',
                    $this->content,
                    '</source>',
                ]),
            ]],
        };
    }
}
