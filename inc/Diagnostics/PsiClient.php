<?php

declare(strict_types=1);

namespace RhPerformance\Diagnostics;

use RhPerformance\Settings;

/**
 * Holt ein echtes Lighthouse-Scoring von der Google PageSpeed-Insights-API.
 *
 * Das ist der einzige Weg zu echten Web Vitals (LCP/CLS/TBT) und den
 * Opportunities ("was frisst wie viele ms"), weil das ein gerendertes
 * Lighthouse-Audit braucht, das ein PHP-Plugin nicht selbst rechnen kann. Geht
 * nur gegen öffentlich erreichbare URLs (Google crawlt von außen), nicht gegen
 * lokale Umgebungen. API-Key ist optional (erhöht nur das Kontingent).
 */
final class PsiClient
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /**
     * @return array{ok: bool, error?: string, url?: string, strategy?: string, score?: ?int, metrics?: list<array{label: string, value: string}>, opportunities?: list<array{label: string, savings_ms: int}>, field?: ?string}
     */
    public function fetch(string $url, string $strategy = 'mobile'): array
    {
        $url = esc_url_raw($url);
        if ($url === '') {
            return ['ok' => false, 'error' => __('Keine gültige URL.', 'rh-performance')];
        }

        $strategy = in_array($strategy, ['mobile', 'desktop'], true) ? $strategy : 'mobile';
        $key = Settings::psiKey();

        $query = [
            'url' => $url,
            'category' => 'performance',
            'strategy' => $strategy,
        ];
        if ($key !== '') {
            $query['key'] = $key;
        }

        // Eigener Request statt des Loopback-Helpers: geht an Google (gültiges
        // Cert, also sslverify an), und ein Lighthouse-Lauf dauert lange.
        $response = wp_remote_get(add_query_arg($query, self::ENDPOINT), [
            'timeout' => 60,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'url' => $url, 'error' => $response->get_error_message()];
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($body)) {
            return ['ok' => false, 'url' => $url, 'error' => __('Antwort von Google nicht lesbar.', 'rh-performance')];
        }
        if (isset($body['error']['message'])) {
            return ['ok' => false, 'url' => $url, 'error' => (string) $body['error']['message']];
        }

        $lighthouse = is_array($body['lighthouseResult'] ?? null) ? $body['lighthouseResult'] : [];
        $audits = is_array($lighthouse['audits'] ?? null) ? $lighthouse['audits'] : [];

        return [
            'ok' => true,
            'url' => $url,
            'strategy' => $strategy,
            'score' => $this->performanceScore($lighthouse),
            'metrics' => $this->labMetrics($audits),
            'opportunities' => $this->opportunities($audits),
            'field' => $this->fieldCategory($body),
        ];
    }

    /**
     * @param array<string, mixed> $lighthouse
     */
    private function performanceScore(array $lighthouse): ?int
    {
        $score = $lighthouse['categories']['performance']['score'] ?? null;

        return is_numeric($score) ? (int) round((float) $score * 100) : null;
    }

    /**
     * Lab-Metriken in der PSI-typischen Reihenfolge.
     *
     * @param array<string, mixed> $audits
     *
     * @return list<array{label: string, value: string}>
     */
    private function labMetrics(array $audits): array
    {
        $wanted = [
            'first-contentful-paint' => 'FCP',
            'largest-contentful-paint' => 'LCP',
            'total-blocking-time' => 'TBT',
            'cumulative-layout-shift' => 'CLS',
            'speed-index' => 'Speed Index',
        ];

        $metrics = [];
        foreach ($wanted as $id => $label) {
            $value = $audits[$id]['displayValue'] ?? '';
            if (is_string($value) && $value !== '') {
                $metrics[] = ['label' => $label, 'value' => $value];
            }
        }

        return $metrics;
    }

    /**
     * Opportunities = was am meisten Ladezeit kostet, absteigend nach ms.
     *
     * @param array<string, mixed> $audits
     *
     * @return list<array{label: string, savings_ms: int}>
     */
    private function opportunities(array $audits): array
    {
        $opps = [];
        foreach ($audits as $audit) {
            if (! is_array($audit)) {
                continue;
            }
            $ms = $audit['details']['overallSavingsMs'] ?? 0;
            if (($audit['details']['type'] ?? '') === 'opportunity' && (float) $ms > 0) {
                $opps[] = [
                    'label' => (string) ($audit['title'] ?? ''),
                    'savings_ms' => (int) round((float) $ms),
                ];
            }
        }

        usort($opps, static fn (array $a, array $b): int => $b['savings_ms'] <=> $a['savings_ms']);

        return array_slice($opps, 0, 6);
    }

    /**
     * CrUX-Feld-Gesamtbewertung (echte Nutzerdaten), wenn Google welche hat.
     *
     * @param array<string, mixed> $body
     */
    private function fieldCategory(array $body): ?string
    {
        $category = $body['loadingExperience']['overall_category'] ?? null;

        return is_string($category) && $category !== '' ? $category : null;
    }
}
