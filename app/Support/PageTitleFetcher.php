<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads a recipe page's name from its URL: the first <h1>, falling back to og:title and then
 * <title>. The fetch itself goes through SafeUrlFetcher, so only public hosts are reached.
 */
class PageTitleFetcher
{
    public function __construct(private readonly SafeUrlFetcher $fetcher = new SafeUrlFetcher) {}

    public function fetch(string $url): ?string
    {
        try {
            return $this->titleFromHtml($this->fetcher->fetchPage($url));
        } catch (Throwable) {
            return null;
        }
    }

    public function titleFromHtml(string $html): ?string
    {
        if (trim($html) === '') {
            return null;
        }

        $xpath = new DOMXPath(self::parse($html));

        foreach (['//h1', '//meta[@property="og:title"]/@content', '//title'] as $expression) {
            foreach ($xpath->query($expression) ?: [] as $node) {
                $title = Str::of(html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish()->limit(255, '')->value();

                if ($title !== '') {
                    return $title;
                }
            }
        }

        return null;
    }

    public static function parse(string $html): DOMDocument
    {
        $dom = new DOMDocument;
        // Declare UTF-8 up front, or libxml reads "Plankstek med äpple" as Latin-1.
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);

        return $dom;
    }
}
