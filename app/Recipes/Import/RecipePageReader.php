<?php

namespace App\Recipes\Import;

use App\Support\PageTitleFetcher;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Turns a fetched recipe page into the smallest faithful RecipeSource. Most recipe sites embed
 * schema.org Recipe JSON-LD, which is a few thousand tokens against a page of hundreds of
 * thousands; the page's readable text is the fallback.
 */
class RecipePageReader
{
    /** Roughly 30k tokens. Past this the page is refused rather than cut. */
    public const MAX_TEXT_CHARS = 120_000;

    public function read(string $html, ?string $url = null): RecipeSource
    {
        $dom = PageTitleFetcher::parse($html);
        $xpath = new DOMXPath($dom);
        $lang = $this->attribute($xpath, '//html/@lang');

        if ($recipe = $this->jsonLdRecipe($xpath)) {
            return RecipeSource::fromJsonLd(
                json_encode($recipe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $url,
                $lang,
            );
        }

        $text = $this->readableText($dom, $xpath);

        if (mb_strlen($text) > self::MAX_TEXT_CHARS) {
            throw new RecipeImportException(__('recipe.import.errors.too_large'));
        }

        if (mb_strlen($text) < 40) {
            throw new RecipeImportException(__('recipe.import.errors.no_recipe'));
        }

        return RecipeSource::fromText($text, $url, $lang, $this->attribute($xpath, '//meta[@property="og:image"]/@content'));
    }

    /**
     * The first schema.org Recipe object on the page, wherever it sits: top level, in an
     * array, or inside a @graph.
     *
     * @return array<string, mixed>|null
     */
    public function jsonLdRecipe(DOMXPath $xpath): ?array
    {
        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $data = json_decode(trim($script->textContent), true);

            if (is_array($data) && ($recipe = $this->findRecipe($data))) {
                return $recipe;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $node
     * @return array<string, mixed>|null
     */
    private function findRecipe(array $node): ?array
    {
        $types = (array) ($node['@type'] ?? []);

        if (in_array('Recipe', $types, true)) {
            return $node;
        }

        foreach (array_is_list($node) ? $node : ($node['@graph'] ?? []) as $child) {
            if (is_array($child) && ($recipe = $this->findRecipe($child))) {
                return $recipe;
            }
        }

        return null;
    }

    private function readableText(DOMDocument $dom, DOMXPath $xpath): string
    {
        foreach ($xpath->query('//script|//style|//noscript|//nav|//header|//footer|//aside|//form|//svg|//iframe') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        $root = $xpath->query('//main')?->item(0)
            ?? $xpath->query('//article')?->item(0)
            ?? $xpath->query('//body')?->item(0)
            ?? $dom->documentElement;

        return $root ? $this->textOf($root) : '';
    }

    /** Block elements become line breaks, so lists and steps stay one per line. */
    private function textOf(DOMNode $root): string
    {
        $blocks = ['p', 'li', 'ul', 'ol', 'table', 'blockquote', 'pre', 'figure', 'article', 'main', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'tr', 'div', 'section', 'br', 'dd', 'dt'];
        $out = '';

        foreach ($root->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $out .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $isBlock = in_array(strtolower($child->nodeName), $blocks, true);
                $out .= ($isBlock ? "\n" : '').$this->textOf($child).($isBlock ? "\n" : '');
            }
        }

        return Str::of($out)
            ->explode("\n")
            ->map(fn (string $line) => Str::squish($line))
            ->filter()
            ->implode("\n");
    }

    private function attribute(DOMXPath $xpath, string $expression): ?string
    {
        $value = trim((string) $xpath->query($expression)?->item(0)?->nodeValue);

        return $value === '' ? null : $value;
    }
}
