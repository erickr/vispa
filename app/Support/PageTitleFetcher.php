<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

/**
 * Reads a recipe page's name from its URL: the first <h1>, falling back to og:title and then
 * <title>. The URL is user-supplied and fetched from our server, so it only ever reaches public
 * http(s) hosts — never localhost, the Docker network or other private ranges.
 */
class PageTitleFetcher
{
    private const MAX_BYTES = 2_000_000;

    public function fetch(string $url): ?string
    {
        try {
            $this->assertPublicUrl($url);

            $response = Http::timeout(8)
                ->connectTimeout(4)
                ->withUserAgent('Mozilla/5.0 (compatible; Vispa/1.0; +recipe title lookup)')
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'protocols' => ['http', 'https'],
                        // Each hop is checked too, so a public URL can't bounce us inward.
                        'on_redirect' => fn ($request, $response, UriInterface $uri) => $this->assertPublicUrl((string) $uri),
                    ],
                ])
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->titleFromHtml(substr($response->body(), 0, self::MAX_BYTES));
    }

    public function titleFromHtml(string $html): ?string
    {
        if (trim($html) === '') {
            return null;
        }

        $dom = new DOMDocument;
        // Declare UTF-8 up front, or libxml reads "Plankstek med äpple" as Latin-1.
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new DOMXPath($dom);

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

    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('Only http(s) URLs can be fetched.');
        }

        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : array_merge(gethostbynamel($host) ?: [], array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'));

        if ($ips === []) {
            throw new RuntimeException('Host does not resolve.');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Host is not public.');
            }
        }
    }
}
