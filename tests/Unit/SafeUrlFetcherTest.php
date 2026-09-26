<?php

namespace Tests\Unit;

use App\Support\HostResolver;
use App\Support\SafeUrlFetcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * The fetcher is the only way user-supplied URLs reach the network, so it must never be talked
 * into an internal address: not by URL tricks, not by DNS, not by a redirect.
 */
class SafeUrlFetcherTest extends TestCase
{
    private const PUBLIC_IP = '93.184.215.14';

    private const PUBLIC_IPV6 = '2606:2800:21f:cb07:6820:80da:af6b:8b2c';

    /**
     * A fetcher whose DNS answers come from $dns; any other name fails the test, so nothing
     * here ever asks a real resolver.
     *
     * @param  array<string, list<string>>  $dns
     */
    private function fetcher(array $dns = []): SafeUrlFetcher
    {
        $dns += ['a.com' => [self::PUBLIC_IP], 'b.com' => ['1.1.1.1']];

        return new SafeUrlFetcher(new class($dns) extends HostResolver
        {
            public function __construct(private array $dns) {}

            public function resolve(string $host): array
            {
                if (! array_key_exists($host, $this->dns)) {
                    throw new \LogicException("Unexpected DNS lookup for {$host}.");
                }

                return $this->dns[$host];
            }
        });
    }

    private function assertRejected(SafeUrlFetcher $fetcher, string $url): void
    {
        try {
            $fetcher->fetchPage($url);
            $this->fail("{$url} was fetched.");
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }
    }

    public static function trickyUrls(): array
    {
        return [
            'backslash before @' => ['http://a.com\@127.0.0.1/'],
            'fragment hiding the host' => ['http://127.0.0.1#@a.com/'],
            'user info' => ['http://user@127.0.0.1/'],
            'user info on a public host' => ['http://user:pass@a.com/'],
            'decimal IPv4' => ['http://2130706433/'],
            'hex IPv4' => ['http://0x7f.0.0.1/'],
            'octal IPv4' => ['http://017700000001/'],
            'octal dotted IPv4' => ['http://0177.0.0.1/'],
            'short IPv4' => ['http://127.1/'],
            'percent-encoded host' => ['http://%31%32%37.0.0.1/'],
            'fullwidth digits' => ['http://１２７.０.０.１/'],
            'IPv4-mapped IPv6' => ['http://[::ffff:127.0.0.1]/'],
            'IPv4-mapped IPv6, hex' => ['http://[::ffff:7f00:1]/'],
            'IPv6 loopback' => ['http://[::1]/'],
            'no host' => ['http:///etc/passwd'],
            'file scheme' => ['file:///etc/passwd'],
            'gopher scheme' => ['gopher://a.com/'],
            'non-standard port' => ['http://a.com:8080/'],
            'https port on http' => ['http://a.com:443/'],
            'metadata endpoint' => ['http://169.254.169.254/latest/meta-data/'],
        ];
    }

    #[DataProvider('trickyUrls')]
    public function test_tricky_urls_never_reach_an_internal_address(string $url): void
    {
        Http::fake();

        $this->assertRejected($this->fetcher(), $url);

        Http::assertNothingSent();
    }

    public static function blockedIps(): array
    {
        return [
            'this network' => ['0.1.2.3'],
            'private 10/8' => ['10.0.0.5'],
            'carrier-grade NAT' => ['100.64.1.1'],
            'loopback' => ['127.0.0.2'],
            'link-local / metadata' => ['169.254.169.254'],
            'private 172.16/12' => ['172.20.0.3'],
            'IETF protocol assignments' => ['192.0.0.8'],
            'private 192.168/16' => ['192.168.1.1'],
            'benchmarking' => ['198.18.0.1'],
            'multicast' => ['224.0.0.1'],
            'reserved' => ['240.0.0.1'],
            'broadcast' => ['255.255.255.255'],
            'unspecified IPv6' => ['::'],
            'IPv6 loopback' => ['::1'],
            'unique local' => ['fd00::1'],
            'IPv6 link-local' => ['fe80::1'],
            'IPv6 multicast' => ['ff02::1'],
            'IPv4-mapped' => ['::ffff:10.0.0.1'],
            'IPv4-mapped, public inside' => ['::ffff:1.1.1.1'],
            'IPv4-compatible' => ['::127.0.0.1'],
            'NAT64' => ['64:ff9b::a00:1'],
            'local NAT64' => ['64:ff9b:1::a00:1'],
            '6to4' => ['2002:7f00:1::1'],
            'Teredo' => ['2001:0:4136:e378:8000:63bf:3fff:fdd2'],
        ];
    }

    #[DataProvider('blockedIps')]
    public function test_internal_and_ipv4_carrying_addresses_are_not_public(string $ip): void
    {
        $this->assertFalse(SafeUrlFetcher::isPublicIp($ip));

        Http::fake();
        $this->assertRejected($this->fetcher(['blocked.test' => [$ip]]), 'http://blocked.test/');
        Http::assertNothingSent();
    }

    public function test_public_addresses_are_public(): void
    {
        foreach ([self::PUBLIC_IP, '1.1.1.1', self::PUBLIC_IPV6] as $ip) {
            $this->assertTrue(SafeUrlFetcher::isPublicIp($ip), $ip);
        }
    }

    public function test_a_name_with_any_internal_address_is_rejected(): void
    {
        Http::fake();

        $this->assertRejected($this->fetcher(['mixed.test' => [self::PUBLIC_IP, '10.0.0.1']]), 'https://mixed.test/');
        $this->assertRejected($this->fetcher(['nothing.test' => []]), 'https://nothing.test/');

        Http::assertNothingSent();
    }

    public function test_the_connection_is_pinned_to_the_checked_addresses(): void
    {
        $sent = [];
        Http::fake(function (Request $request, array $options) use (&$sent) {
            $sent[$request->url()] = $options['curl'];

            return Http::response('ok');
        });

        $fetcher = $this->fetcher(['pinned.test' => [self::PUBLIC_IP, self::PUBLIC_IPV6]]);
        $this->assertSame('ok', $fetcher->fetchPage('https://pinned.test/recept'));
        $this->assertSame('ok', $fetcher->fetchPage('http://pinned.test:80/recept'));
        $this->assertSame('ok', $fetcher->fetchPage('http://'.self::PUBLIC_IP.'/'));

        $this->assertSame(['pinned.test:443:'.self::PUBLIC_IP.',['.self::PUBLIC_IPV6.']'], $sent['https://pinned.test/recept'][CURLOPT_RESOLVE]);
        $this->assertSame(['pinned.test:80:'.self::PUBLIC_IP.',['.self::PUBLIC_IPV6.']'], $sent['http://pinned.test/recept'][CURLOPT_RESOLVE]);
        $this->assertSame('', $sent['https://pinned.test/recept'][CURLOPT_PROXY]);
        // An IP literal is connected to as written; there is no lookup to pin.
        $this->assertArrayNotHasKey(CURLOPT_RESOLVE, $sent['http://'.self::PUBLIC_IP.'/']);
    }

    public function test_redirects_are_followed_and_every_hop_is_checked_and_pinned(): void
    {
        $sent = [];
        Http::fake(function (Request $request, array $options) use (&$sent) {
            $sent[] = [$request->url(), $options['curl'][CURLOPT_RESOLVE] ?? null];

            return match ($request->url()) {
                'https://a.com/start' => Http::response('', 301, ['Location' => '/moved?x=1']),
                'https://a.com/moved?x=1' => Http::response('', 302, ['Location' => 'http://b.com/final']),
                'http://b.com/final' => Http::response('the recipe'),
            };
        });

        $this->assertSame('the recipe', $this->fetcher()->fetchPage('https://a.com/start'));
        $this->assertSame([
            ['https://a.com/start', ['a.com:443:'.self::PUBLIC_IP]],
            ['https://a.com/moved?x=1', ['a.com:443:'.self::PUBLIC_IP]],
            ['http://b.com/final', ['b.com:80:1.1.1.1']],
        ], $sent);
    }

    public static function badRedirects(): array
    {
        return [
            'loopback literal' => ['http://127.0.0.1/admin'],
            'name resolving inward' => ['http://internal.test/'],
            'metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'non-http scheme' => ['file:///etc/passwd'],
            'non-standard port' => ['http://b.com:6379/'],
            'decimal IPv4' => ['http://2130706433/'],
        ];
    }

    #[DataProvider('badRedirects')]
    public function test_a_redirect_inward_is_never_followed(string $location): void
    {
        Http::fake([
            'https://a.com/*' => Http::response('', 302, ['Location' => $location]),
            '*' => Http::response('inside'),
        ]);

        $this->assertRejected($this->fetcher(['internal.test' => ['10.1.2.3']]), 'https://a.com/start');
        Http::assertSentCount(1);
    }

    public function test_it_gives_up_after_three_redirects(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => '/again'])]);

        $this->assertRejected($this->fetcher(), 'https://a.com/start');
        Http::assertSentCount(1 + SafeUrlFetcher::MAX_REDIRECTS);
    }

    public function test_a_long_page_is_cut_off_at_the_cap(): void
    {
        Http::fake(['*' => Http::response(str_repeat('a', SafeUrlFetcher::MAX_PAGE_BYTES + 10))]);

        $this->assertSame(SafeUrlFetcher::MAX_PAGE_BYTES, strlen($this->fetcher()->fetchPage('https://a.com/')));
    }

    public function test_an_oversize_image_is_rejected(): void
    {
        Http::fake([
            'a.com/big.jpg' => Http::response(str_repeat('a', SafeUrlFetcher::MAX_IMAGE_BYTES + 1), 200, ['Content-Type' => 'image/jpeg']),
            'a.com/announced.jpg' => Http::response('tiny', 200, ['Content-Type' => 'image/jpeg', 'Content-Length' => (string) (SafeUrlFetcher::MAX_IMAGE_BYTES + 1)]),
            'a.com/ok.jpg' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $fetcher = $this->fetcher();

        foreach (['big', 'announced'] as $name) {
            try {
                $fetcher->fetchImage("https://a.com/{$name}.jpg");
                $this->fail("{$name} was accepted.");
            } catch (RuntimeException $e) {
                $this->assertSame('Image is empty or too large.', $e->getMessage());
            }
        }

        $this->assertSame(['bytes' => 'jpeg-bytes', 'extension' => 'jpg'], $fetcher->fetchImage('https://a.com/ok.jpg'));
    }

    public function test_curl_stops_reading_once_the_cap_is_reached(): void
    {
        $results = [];
        Http::fake(function (Request $request, array $options) use (&$results) {
            // Feed the write callback the way curl would, chunk by chunk.
            $write = $options['curl'][CURLOPT_WRITEFUNCTION];
            $chunk = str_repeat('a', 1_000_000);
            $results = [$write(null, $chunk), $write(null, $chunk), $write(null, $chunk)];

            return Http::response('');
        });

        $this->fetcher()->fetchPage('https://a.com/');

        // The third megabyte would pass MAX_PAGE_BYTES, so curl is told to abort there.
        $this->assertSame([1_000_000, 1_000_000, 0], $results);
    }
}
