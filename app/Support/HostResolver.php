<?php

namespace App\Support;

use RuntimeException;

/**
 * Resolves a URL's host and refuses anything that is not a public internet address,
 * so user-configured sources cannot reach internal services (SSRF).
 */
class HostResolver
{
    /**
     * @return array{host: string, port: int, ip: string}
     *
     * @throws RuntimeException
     */
    public function resolvePublic(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('Only http(s) URLs with a host are allowed');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->lookup($host);

        if ($ips === []) {
            throw new RuntimeException("Could not resolve {$host}");
        }

        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                throw new RuntimeException("{$host} resolves to a non-public address");
            }
        }

        // Prefer IPv4 for the pinned connection.
        usort($ips, fn ($a, $b) => str_contains($a, ':') <=> str_contains($b, ':'));

        return ['host' => $host, 'port' => (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)), 'ip' => $ips[0]];
    }

    public static function isPublic(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE);
    }

    /**
     * @return list<string>
     */
    protected function lookup(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ipv6'];
        }

        return array_values(array_unique($ips));
    }
}
