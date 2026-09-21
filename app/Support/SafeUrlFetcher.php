<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * Fetches a user-supplied URL from our server. It only ever reaches public http(s) hosts —
 * never localhost, the Docker network or other private ranges — and checks every redirect
 * hop the same way, so a public URL can't bounce the request inward.
 */
class SafeUrlFetcher
{
    public const MAX_PAGE_BYTES = 2_000_000;

    public const MAX_IMAGE_BYTES = 10_000_000;

    /**
     * The page's body, capped at MAX_PAGE_BYTES.
     *
     * @throws RuntimeException when the URL isn't public, can't be reached or doesn't answer 2xx
     */
    public function fetchPage(string $url): string
    {
        $response = $this->get($url);

        return substr($response->body(), 0, self::MAX_PAGE_BYTES);
    }

    /**
     * @return array{bytes: string, extension: string}
     *
     * @throws RuntimeException when it isn't a reachable image within MAX_IMAGE_BYTES
     */
    public function fetchImage(string $url): array
    {
        $response = $this->get($url);
        $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

        $extension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
        ][$type] ?? null;

        if ($extension === null) {
            throw new RuntimeException("Not an image ({$type}).");
        }

        $bytes = $response->body();

        if (strlen($bytes) === 0 || strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('Image is empty or too large.');
        }

        return ['bytes' => $bytes, 'extension' => $extension];
    }

    private function get(string $url): Response
    {
        $this->assertPublicUrl($url);

        $response = Http::timeout(10)
            ->connectTimeout(4)
            ->withUserAgent('Mozilla/5.0 (compatible; Vispa/1.0; +recipe import)')
            ->withOptions([
                'allow_redirects' => [
                    'max' => 3,
                    'protocols' => ['http', 'https'],
                    'on_redirect' => fn ($request, $response, UriInterface $uri) => $this->assertPublicUrl((string) $uri),
                ],
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("The page answered {$response->status()}.");
        }

        return $response;
    }

    public function assertPublicUrl(string $url): void
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
