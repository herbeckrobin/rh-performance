<?php

declare(strict_types=1);

namespace RhPerformance\Diagnostics;

use RhPerformance\Support\Http;

/**
 * On-Demand Frontend-Selbsttest: ruft die eigene Seite anonym ab und misst, was
 * ein echter Besucher bekommt.
 *
 * Bewusst ein einzelner Loopback-Request statt Per-Request-Sampling im Frontend
 * (das würde bei jedem Besuch in die DB schreiben). `wp_remote_get` sendet keine
 * Auth-Cookies, misst also die ausgeloggte Besucher-Sicht, das ist der Punkt:
 * eingeloggt umgeht man oft den Cache. Liefert Ladezeit, ob die Seite überhaupt
 * cachebar ausgeliefert wird (der ebinger-Klassiker: no-store killt jeden Cache)
 * und ein Asset-Inventar aus dem echten HTML.
 */
final class SelfTest
{
    private const TIMEOUT = 15;

    /**
     * @return array{ok: bool, error?: string, url?: string, status?: int, time_ms?: int, html_kb?: float, cacheable?: bool, cache_header?: string, css?: int, js?: int, img?: int, modern_img?: bool, render_blocking_css?: int, server?: string}
     */
    public function run(string $url, ?int $timeout = null): array
    {
        $url = esc_url_raw($url);
        if ($url === '') {
            return ['ok' => false, 'error' => __('Keine gültige URL.', 'rh-performance')];
        }

        $start = microtime(true);
        $response = Http::get($url, $timeout ?? self::TIMEOUT);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'url' => $url,
                'error' => $response->get_error_message(),
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $cacheHeader = trim((string) wp_remote_retrieve_header($response, 'cache-control'));

        $assets = $this->parseAssets($body);

        return array_merge([
            'ok' => true,
            'url' => $url,
            'status' => $status,
            'time_ms' => $elapsedMs,
            'html_kb' => round(strlen($body) / 1024, 1),
            'cacheable' => $this->isCacheable($cacheHeader),
            'cache_header' => $cacheHeader !== '' ? $cacheHeader : __('keiner', 'rh-performance'),
            'server' => trim((string) wp_remote_retrieve_header($response, 'server')),
        ], $assets);
    }

    /**
     * Zählt die geladenen Frontend-Assets aus dem ausgelieferten HTML.
     *
     * @return array{css: int, js: int, img: int, modern_img: bool, render_blocking_css: int}
     */
    private function parseAssets(string $html): array
    {
        $cssLinks = preg_match_all('/<link\b[^>]*\brel=["\']?stylesheet["\']?[^>]*>/i', $html, $cssMatches);
        $js = preg_match_all('/<script\b[^>]*\bsrc=/i', $html, $unusedJs);
        $img = preg_match_all('/<img\b/i', $html, $unusedImg);

        // Render-blocking = Stylesheet im <head>, das den ersten Paint aufhält.
        $head = '';
        if (preg_match('/<head\b[^>]*>(.*?)<\/head>/is', $html, $headMatch)) {
            $head = $headMatch[1];
        }
        $renderBlocking = $head !== ''
            ? preg_match_all('/<link\b[^>]*\brel=["\']?stylesheet["\']?[^>]*>/i', $head, $unusedHead)
            : 0;

        return [
            'css' => (int) $cssLinks,
            'js' => (int) $js,
            'img' => (int) $img,
            // Moderne Bildformate im Einsatz? Grober, aber nützlicher Indikator.
            'modern_img' => (bool) preg_match('/\.(webp|avif)\b/i', $html),
            'render_blocking_css' => (int) $renderBlocking,
        ];
    }

    /**
     * Erkennt, ob die Antwort grundsätzlich von Caches gespeichert werden darf.
     * `no-store`/`private`/`no-cache` (Login-Plugins, Sessions) verhindern jedes
     * Page-Caching, das war bei ebinger der eigentliche Performance-Killer.
     */
    private function isCacheable(string $cacheHeader): bool
    {
        if ($cacheHeader === '') {
            return true;
        }

        $lower = strtolower($cacheHeader);

        return ! (
            str_contains($lower, 'no-store')
            || str_contains($lower, 'no-cache')
            || str_contains($lower, 'private')
            || str_contains($lower, 'max-age=0')
        );
    }
}
