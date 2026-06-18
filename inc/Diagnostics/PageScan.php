<?php

declare(strict_types=1);

namespace RhPerformance\Diagnostics;

/**
 * Scannt eine Liste von URLs per Loopback und vergibt pro Seite einen Score.
 *
 * Läuft synchron in einem Request, darum auf SCAN_LIMIT gedeckelt und mit kurzem
 * Timeout pro Seite, damit eine hängende Seite nicht den ganzen Scan blockiert.
 * Für sehr große Sites wäre eine Queue nötig (wie die Sync-Tick-Engine), das ist
 * hier bewusst nicht der Fall, dafür meldet das Ergebnis ehrlich, wie viele von
 * wie vielen Seiten gescannt wurden.
 */
final class PageScan
{
    private const SCAN_LIMIT = 20;

    private const PER_PAGE_TIMEOUT = 8;

    /**
     * @param list<string> $urls
     *
     * @return array{scanned: int, total: int, rows: list<array{url: string, ok: bool, error?: string, score?: int, status?: int, time_ms?: int, html_kb?: float, cacheable?: bool, css?: int, js?: int, img?: int, render_blocking_css?: int, modern_img?: bool, top?: string}>}
     */
    public function scan(array $urls): array
    {
        $total = count($urls);
        $batch = array_slice($urls, 0, self::SCAN_LIMIT);

        $test = new SelfTest();
        $calc = new ScoreCalculator();

        $rows = [];
        foreach ($batch as $url) {
            $result = $test->run($url, self::PER_PAGE_TIMEOUT);

            if (($result['ok'] ?? false) !== true) {
                $rows[] = [
                    'url' => $url,
                    'ok' => false,
                    'error' => (string) ($result['error'] ?? __('nicht erreichbar', 'rh-performance')),
                ];
                continue;
            }

            $scored = $calc->score($result);
            $rows[] = [
                'url' => $url,
                'ok' => true,
                'score' => $scored['score'],
                'status' => (int) $result['status'],
                'time_ms' => (int) $result['time_ms'],
                'html_kb' => (float) $result['html_kb'],
                'cacheable' => (bool) $result['cacheable'],
                'css' => (int) $result['css'],
                'js' => (int) $result['js'],
                'img' => (int) $result['img'],
                'render_blocking_css' => (int) $result['render_blocking_css'],
                'modern_img' => (bool) $result['modern_img'],
                'top' => $scored['factors'][0]['label'] ?? __('keine größeren Bremsen', 'rh-performance'),
            ];
        }

        return [
            'scanned' => count($rows),
            'total' => $total,
            'rows' => $rows,
        ];
    }
}
