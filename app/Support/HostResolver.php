<?php

namespace App\Support;

/**
 * Looks up a host name's addresses for SafeUrlFetcher. A class of its own so tests can swap in
 * fixed answers instead of asking real DNS.
 */
class HostResolver
{
    /**
     * @return list<string> every IPv4 and IPv6 address the name resolves to, or [] if none
     */
    public function resolve(string $host): array
    {
        $ipv4 = gethostbynamel($host) ?: [];
        $ipv6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');

        return array_values(array_unique([...$ipv4, ...$ipv6]));
    }
}
