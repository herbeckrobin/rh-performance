<?php

declare(strict_types=1);

namespace RhPerformance\Support;

use WP_Error;

/**
 * Gemeinsamer Loopback-/Außen-Request für die Diagnose (Selbsttest, Sitemap, PSI).
 * Eine Stelle für die Defaults, v.a. den `sslverify`-Filter, den Testumgebungen
 * mit selbstsigniertem Zertifikat (DDEV) auf false setzen.
 */
final class Http
{
    /**
     * @return array<string, mixed>|WP_Error
     */
    public static function get(string $url, int $timeout = 15): array|WP_Error
    {
        return wp_remote_get($url, [
            'timeout' => $timeout,
            'redirection' => 3,
            'sslverify' => (bool) apply_filters('rh-blueprint/performance/sslverify', true, $url),
            'headers' => ['Cache-Control' => 'no-cache'],
            'user-agent' => 'rh-performance Diagnose',
        ]);
    }
}
