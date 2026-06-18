<?php

declare(strict_types=1);

namespace RhPerformance\Diagnostics;

use RhPerformance\Support\Bytes;

/**
 * Liest statische Server-/WP-Health-Werte, die den Boot-Speed bestimmen.
 *
 * Reiner Lese-Snapshot, kein Schreibzugriff, kein Per-Request-Overhead. Wird nur
 * im Admin beim Rendern des Diagnose-Panels berechnet. Beantwortet die Fragen,
 * die bei einem Performance-Audit zuerst kommen: läuft OPcache, wie alt ist PHP,
 * gibt es einen Persistent Object Cache, wie schwer ist der Plugin-Stack.
 */
final class ServerHealth
{
    public const LEVEL_OK = 'ok';

    public const LEVEL_WARN = 'warn';

    public const LEVEL_INFO = 'info';

    /**
     * @return list<array{label: string, value: string, level: string, hint: string}>
     */
    public function rows(): array
    {
        return [
            $this->phpVersion(),
            $this->opcache(),
            $this->objectCache(),
            $this->memoryLimit(),
            $this->activePlugins(),
            $this->wpVersion(),
        ];
    }

    /**
     * @return array{label: string, value: string, level: string, hint: string}
     */
    private function phpVersion(): array
    {
        $version = PHP_VERSION;
        // PHP < 8.1 ist EOL und merklich langsamer als die aktuellen Linien.
        $level = version_compare($version, '8.1', '<') ? self::LEVEL_WARN : self::LEVEL_OK;

        return [
            'label' => __('PHP-Version', 'rh-performance'),
            'value' => $version,
            'level' => $level,
            'hint' => $level === self::LEVEL_WARN
                ? __('Veraltet und langsam. Ein Update auf PHP 8.2+ bringt spürbar mehr Tempo.', 'rh-performance')
                : '',
        ];
    }

    /**
     * @return array{label: string, value: string, level: string, hint: string}
     */
    private function opcache(): array
    {
        $enabled = $this->opcacheEnabled();

        return [
            'label' => __('OPcache', 'rh-performance'),
            'value' => $enabled ? __('aktiv', 'rh-performance') : __('aus', 'rh-performance'),
            'level' => $enabled ? self::LEVEL_OK : self::LEVEL_WARN,
            'hint' => $enabled
                ? ''
                : __('Ohne OPcache kompiliert PHP bei jedem Request neu. Der größte Hebel für die Boot-Zeit, beim Hoster aktivieren.', 'rh-performance'),
        ];
    }

    private function opcacheEnabled(): bool
    {
        // opcache_get_status() ist die verlässliche Quelle, kann aber per
        // opcache.restrict_api gesperrt sein, dann auf die ini-Direktive fallen.
        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            if (is_array($status) && isset($status['opcache_enabled'])) {
                return (bool) $status['opcache_enabled'];
            }
        }

        return (bool) ini_get('opcache.enable');
    }

    /**
     * @return array{label: string, value: string, level: string, hint: string}
     */
    private function objectCache(): array
    {
        $external = function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();

        return [
            'label' => __('Object Cache', 'rh-performance'),
            // Kein Persistent Object Cache ist kein Fehler (viele kleine Sites
            // brauchen keinen), darum nur Info, nicht Warnung.
            'value' => $external ? __('persistent', 'rh-performance') : __('nur Request', 'rh-performance'),
            'level' => self::LEVEL_INFO,
            'hint' => $external
                ? ''
                : __('Kein persistenter Object Cache (z.B. Redis). Bei DB-lastigen Sites einen Gewinn wert, sonst unkritisch.', 'rh-performance'),
        ];
    }

    /**
     * @return array{label: string, value: string, level: string, hint: string}
     */
    private function memoryLimit(): array
    {
        $limit = (string) ini_get('memory_limit');
        $bytes = Bytes::fromIni($limit);
        // Unter 128 MB wird es bei einem typischen WP-Plugin-Stack eng.
        $level = ($bytes > 0 && $bytes < 128 * 1024 * 1024) ? self::LEVEL_WARN : self::LEVEL_INFO;

        return [
            'label' => __('PHP Memory Limit', 'rh-performance'),
            'value' => ($limit === '-1') ? __('unbegrenzt', 'rh-performance') : $limit,
            'level' => $level,
            'hint' => $level === self::LEVEL_WARN
                ? __('Knapp für einen WP-Stack. Bei "memory exhausted"-Fehlern auf 256M erhöhen.', 'rh-performance')
                : '',
        ];
    }

    /**
     * @return array{label: string, value: string, level: string, hint: string}
     */
    private function activePlugins(): array
    {
        $count = count((array) get_option('active_plugins', []));
        if (is_multisite()) {
            $count += count((array) get_site_option('active_sitewide_plugins', []));
        }

        // Nicht die Zahl an sich ist das Problem, aber ein schwerer Stack ist die
        // häufigste Boot-Bremse, darum ab ~30 als Hinweis flaggen.
        $level = $count >= 30 ? self::LEVEL_INFO : self::LEVEL_OK;

        return [
            'label' => __('Aktive Plugins', 'rh-performance'),
            /* translators: %d: number of active plugins. */
            'value' => sprintf(_n('%d Plugin', '%d Plugins', $count, 'rh-performance'), $count),
            'level' => $level,
            'hint' => $level === self::LEVEL_INFO
                ? __('Viele Plugins laden bei jedem Request. Ungenutzte deaktivieren spart Boot-Zeit.', 'rh-performance')
                : '',
        ];
    }

    /**
     * @return array{label: string, value: string, level: string, hint: string}
     */
    private function wpVersion(): array
    {
        return [
            'label' => __('WordPress-Version', 'rh-performance'),
            'value' => (string) get_bloginfo('version'),
            'level' => self::LEVEL_INFO,
            'hint' => '',
        ];
    }
}
