<?php

declare(strict_types=1);

namespace RhPerformance\Diagnostics;

use RhPerformance\Settings;
use RhPerformance\Support\Bytes;

/**
 * Zeichnet den PHP-Peak-Memory echter Frontend-Requests auf, damit man im Audit
 * sieht, wie nah die Seite ans Limit kommt.
 *
 * Bewusst opt-in (Setting `record_memory`, default aus): die Aufzeichnung kostet
 * pro Request einen Options-Read, das soll nur während eines Audits laufen, nicht
 * dauerhaft. Gedrosselt auf eine Messung pro Minute, damit kein DB-Write-Sturm
 * entsteht. Buffer ist eine autoload=no Option (überlebt Object-Cache-Flush, lädt
 * aber nicht bei jedem Request mit).
 */
final class MemoryRecorder
{
    public const OPTION = 'rhbp_perf_memlog';

    private const MAX_SAMPLES = 100;

    private const THROTTLE_SECONDS = 60;

    public function boot(): void
    {
        if (! Settings::recordMemory()) {
            return;
        }

        add_action('shutdown', [$this, 'sample'], 999);
    }

    /**
     * Sampelt den Peak nur für echte Seitenaufrufe, nicht für Admin/AJAX/Cron/REST
     * (die sagen über die Besucher-Last nichts aus und würden den Verlauf verzerren).
     */
    public function sample(): void
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $log = $this->read();
        $now = time();
        if ($now - (int) $log['last'] < self::THROTTLE_SECONDS) {
            return;
        }

        $limitBytes = Bytes::fromIni((string) ini_get('memory_limit'));

        $log['samples'][] = [
            't' => $now,
            'peak' => memory_get_peak_usage(true),
            'limit' => $limitBytes,
        ];
        $log['samples'] = array_slice($log['samples'], -self::MAX_SAMPLES);
        $log['last'] = $now;

        update_option(self::OPTION, $log, false);
    }

    /**
     * @return array{enabled: bool, samples: list<array{t: int, peak: int, limit: int}>, peak: int, limit: int, percent: ?float}
     */
    public function snapshot(): array
    {
        $log = $this->read();
        $samples = $log['samples'];

        $peak = 0;
        $limit = 0;
        foreach ($samples as $s) {
            $peak = max($peak, (int) $s['peak']);
            $limit = max($limit, (int) $s['limit']);
        }

        return [
            'enabled' => Settings::recordMemory(),
            'samples' => $samples,
            'peak' => $peak,
            'limit' => $limit,
            'percent' => ($limit > 0) ? round($peak / $limit * 100, 1) : null,
        ];
    }

    public function reset(): void
    {
        delete_option(self::OPTION);
    }

    /**
     * @return array{samples: list<array{t: int, peak: int, limit: int}>, last: int}
     */
    private function read(): array
    {
        $log = get_option(self::OPTION, []);
        if (! is_array($log)) {
            $log = [];
        }

        return [
            'samples' => isset($log['samples']) && is_array($log['samples']) ? array_values($log['samples']) : [],
            'last' => isset($log['last']) ? (int) $log['last'] : 0,
        ];
    }
}
