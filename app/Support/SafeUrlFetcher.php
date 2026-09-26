<?php

namespace App\Support;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * Fetches a user-supplied URL from our server. It only ever reaches public http(s) hosts —
 * never localhost, the Docker network or other private ranges — and checks every redirect
 * hop the same way, so a public URL can't bounce the request inward.
 *
 * Each hop's host is resolved once, every address is checked, and curl is then pinned to
 * those addresses (CURLOPT_RESOLVE), so a second DNS answer can't swap in an internal one
 * between the check and the connect. That pin is per hop, which is why redirects are
 * followed here rather than by Guzzle.
 */
class SafeUrlFetcher
{
    public const MAX_PAGE_BYTES = 2_000_000;

    public const MAX_IMAGE_BYTES = 10_000_000;

    public const MAX_REDIRECTS = 3;

    /**
     * Never public, on top of what PHP's own private/reserved flags catch. The IPv6 forms that
     * carry an IPv4 address inside (mapped, compatible, NAT64, 6to4, Teredo) are blocked
     * whole rather than unpacked: no recipe site is reachable only through one of them.
     */
    private const BLOCKED_RANGES = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
        '172.16.0.0/12', '192.0.0.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', 'fc00::/7', 'fe80::/10', 'ff00::/8',
        '::ffff:0:0/96', '::/96', '64:ff9b::/96', '64:ff9b:1::/48', '2002::/16', '2001::/32',
    ];

    public function __construct(private readonly HostResolver $resolver = new HostResolver) {}

    /**
     * The page's body, capped at MAX_PAGE_BYTES.
     *
     * @throws RuntimeException when the URL isn't public, can't be reached or doesn't answer 2xx
     */
    public function fetchPage(string $url): string
    {
        return $this->get($url, self::MAX_PAGE_BYTES, strict: false)['body'];
    }

    /**
     * @return array{bytes: string, extension: string}
     *
     * @throws RuntimeException when it isn't a reachable image within MAX_IMAGE_BYTES
     */
    public function fetchImage(string $url): array
    {
        ['response' => $response, 'body' => $bytes] = $this->get($url, self::MAX_IMAGE_BYTES, strict: true);
        $type = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));

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

        if ($bytes === '') {
            throw new RuntimeException('Image is empty or too large.');
        }

        return ['bytes' => $bytes, 'extension' => $extension];
    }

    /**
     * Checks that the URL may be fetched and returns the addresses its host resolved to.
     *
     * @return list<string>
     *
     * @throws RuntimeException when it may not
     */
    public function assertPublicUrl(string $url): array
    {
        return $this->target($url)['ips'];
    }

    /**
     * @param  bool  $strict  reject a body over $cap instead of cutting it off there
     * @return array{response: ResponseInterface, body: string}
     */
    private function get(string $url, int $cap, bool $strict): array
    {
        $target = $this->target($url);

        for ($hop = 0; ; $hop++) {
            ['response' => $response, 'body' => $body, 'truncated' => $truncated] = $this->send($target, $cap, $strict);
            $location = $response->getHeaderLine('Location');

            if (! in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true) || $location === '') {
                break;
            }

            if ($hop >= self::MAX_REDIRECTS) {
                throw new RuntimeException('Too many redirects.');
            }

            $target = $this->target((string) UriResolver::resolve($target['uri'], new Uri($location)));
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException("The page answered {$response->getStatusCode()}.");
        }

        if ($strict && $truncated) {
            throw new RuntimeException('Image is empty or too large.');
        }

        return ['response' => $response, 'body' => $body];
    }

    /**
     * One request, no redirects, pinned to the addresses that were checked. The body is
     * collected by our own curl write callback so reading stops at $cap instead of after
     * the whole download.
     *
     * @param  array{uri: UriInterface, ips: list<string>}  $target
     * @return array{response: ResponseInterface, body: string, truncated: bool}
     */
    private function send(array $target, int $cap, bool $strict): array
    {
        $body = '';
        $truncated = false;
        $head = null;

        $collect = function ($curl, string $chunk) use (&$body, &$truncated, $cap): int {
            if (strlen($body) + strlen($chunk) > $cap) {
                $body .= substr($chunk, 0, $cap - strlen($body));
                $truncated = true;

                return 0; // Fewer bytes than offered makes curl abort the transfer.
            }

            $body .= $chunk;

            return strlen($chunk);
        };

        // Runs as soon as the headers are in, so an announced oversize image is never downloaded.
        $checkHead = function (ResponseInterface $response) use (&$head, &$truncated, $cap, $strict): void {
            $head = $response;

            if ($strict && $response->getStatusCode() < 300 && (int) $response->getHeaderLine('Content-Length') > $cap) {
                $truncated = true;

                throw new RuntimeException('Image is empty or too large.');
            }
        };

        try {
            $response = Http::timeout(10)
                ->connectTimeout(4)
                ->withUserAgent('Mozilla/5.0 (compatible; Vispa/1.0; +recipe import)')
                ->withOptions([
                    'allow_redirects' => false,
                    'on_headers' => $checkHead,
                    'curl' => $this->curlOptions($target) + [CURLOPT_WRITEFUNCTION => $collect],
                ])
                ->get((string) $target['uri'])
                ->toPsrResponse();
        } catch (Throwable $e) {
            // Cutting a page off at the cap aborts curl, but what came before is what we want.
            if ($truncated && ! $strict && $head !== null) {
                return ['response' => $head, 'body' => $body, 'truncated' => true];
            }

            throw new RuntimeException($strict && $truncated ? 'Image is empty or too large.' : 'The page could not be fetched.', previous: $e);
        }

        // Faked responses (tests) never run curl's callbacks, so hold them to the same rules here.
        if ($head === null) {
            $checkHead($response);
            $body = (string) $response->getBody();

            if (strlen($body) > $cap) {
                $body = substr($body, 0, $cap);
                $truncated = true;
            }
        }

        return ['response' => $response, 'body' => $body, 'truncated' => $truncated];
    }

    /**
     * @param  array{uri: UriInterface, ips: list<string>}  $target
     * @return array<int, mixed>
     */
    public function curlOptions(array $target): array
    {
        $options = [
            // An empty proxy also switches off any http_proxy from the environment, which would
            // otherwise resolve the host itself and bypass the pin.
            CURLOPT_PROXY => '',
        ];

        $host = $target['uri']->getHost();

        // An IP literal needs no lookup, so there is nothing to pin.
        if (! filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
            $port = $target['uri']->getScheme() === 'https' ? 443 : 80;
            $ips = array_map(fn (string $ip) => str_contains($ip, ':') ? "[{$ip}]" : $ip, $target['ips']);
            $options[CURLOPT_RESOLVE] = ["{$host}:{$port}:".implode(',', $ips)];
        }

        return $options;
    }

    /**
     * Parses the URL with the same parser the request will use, so what we check is what we
     * connect to, and resolves its host.
     *
     * @return array{uri: UriInterface, ips: list<string>}
     */
    private function target(string $url): array
    {
        try {
            $uri = new Uri($url);
        } catch (Throwable) {
            throw new RuntimeException('Only http(s) URLs can be fetched.');
        }

        if (! in_array($uri->getScheme(), ['http', 'https'], true) || $uri->getHost() === '') {
            throw new RuntimeException('Only http(s) URLs can be fetched.');
        }

        if ($uri->getUserInfo() !== '') {
            throw new RuntimeException('URLs with a user name or password are not fetched.');
        }

        // Uri drops a scheme's default port, so any port left is a non-standard one. Recipe
        // sites live on 80/443; other ports are where internal services and admin panels sit.
        if ($uri->getPort() !== null) {
            throw new RuntimeException('Only the standard http(s) ports can be fetched.');
        }

        $host = $uri->getHost();

        if (str_starts_with($host, '[')) {
            $ip = trim($host, '[]');

            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new RuntimeException('Not a valid host.');
            }

            $ips = [$ip];
        } else {
            if (preg_match('/[^\x21-\x7e]/', $host)) {
                $ascii = function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46) : false;

                if (! $ascii) {
                    throw new RuntimeException('Not a valid host.');
                }

                $host = strtolower($ascii);
                $uri = $uri->withHost($host);
            }

            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ips = [$host];
            } else {
                $labels = explode('.', rtrim($host, '.'));

                // A name ending in a number is an IPv4 address in disguise (2130706433, 0x7f.1,
                // 0177.0.0.1): resolvers would read it as one, so it never gets that far.
                if (! preg_match('/^[a-z0-9_-]+(\.[a-z0-9_-]+)*\.?$/', $host) || preg_match('/^(0x[0-9a-f]*|[0-9]+)$/', end($labels))) {
                    throw new RuntimeException('Not a valid host.');
                }

                $ips = $this->resolver->resolve($host);
            }
        }

        if ($ips === []) {
            throw new RuntimeException('Host does not resolve.');
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new RuntimeException('Host is not public.');
            }
        }

        return ['uri' => $uri, 'ips' => array_values($ips)];
    }

    public static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && ! IpUtils::checkIp($ip, self::BLOCKED_RANGES);
    }
}
