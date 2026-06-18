<?php

declare(strict_types=1);

namespace RhPerformance\Diagnostics;

/**
 * Rechnet aus einem Loopback-Selbsttest-Ergebnis einen groben 0-100-Score plus
 * die Faktoren, die ihn drücken ("was zieht runter").
 *
 * Bewusst eine Heuristik, KEIN Lighthouse: ohne Rendering gibt es kein
 * LCP/CLS/INP. Der Score gewichtet, was per HTTP messbar ist (Antwortzeit,
 * Cachebarkeit, Render-blocking CSS, Gewicht, Bildformate). Für echte Web Vitals
 * gibt es daneben das PSI-Detail pro Seite.
 */
final class ScoreCalculator
{
    /**
     * @param array<string, mixed> $r SelfTest-Ergebnis
     *
     * @return array{score: int, factors: list<array{label: string, impact: int}>}
     */
    public function score(array $r): array
    {
        $factors = [];

        $status = (int) ($r['status'] ?? 0);
        if ($status !== 200) {
            // Eine nicht-200-Seite ist kein sinnvoller Messpunkt, hart abwerten.
            return [
                'score' => 0,
                'factors' => [[
                    /* translators: %d: HTTP status code. */
                    'label' => sprintf(__('HTTP-Status %d', 'rh-performance'), $status),
                    'impact' => 100,
                ]],
            ];
        }

        $time = (int) ($r['time_ms'] ?? 0);
        $this->add($factors, $this->band($time, 800, 1500, 2500, 10, 22, 35),
            /* translators: %d: response time in milliseconds. */
            sprintf(__('Langsame Antwort (%d ms)', 'rh-performance'), $time));

        if (($r['cacheable'] ?? true) === false) {
            $this->add($factors, 20, __('Nicht cachebar ausgeliefert', 'rh-performance'));
        }

        $rb = (int) ($r['render_blocking_css'] ?? 0);
        $this->add($factors, $this->band($rb, 1, 3, 6, 4, 10, 18),
            /* translators: %d: number of render-blocking stylesheets. */
            sprintf(__('Render-blocking CSS (%d)', 'rh-performance'), $rb));

        $html = (float) ($r['html_kb'] ?? 0);
        $this->add($factors, $this->band((int) $html, 150, 300, 600, 4, 8, 14),
            /* translators: %s: HTML size in kilobytes. */
            sprintf(__('Großes HTML (%s KB)', 'rh-performance'), $html));

        $js = (int) ($r['js'] ?? 0);
        $this->add($factors, $this->band($js, 12, 20, 30, 4, 8, 12),
            /* translators: %d: number of scripts. */
            sprintf(__('Viele Skripte (%d)', 'rh-performance'), $js));

        if ((int) ($r['img'] ?? 0) > 0 && ($r['modern_img'] ?? false) === false) {
            $this->add($factors, 6, __('Keine modernen Bildformate', 'rh-performance'));
        }

        $impactSum = array_sum(array_column($factors, 'impact'));
        $score = max(0, min(100, 100 - $impactSum));

        // Größte Bremsen zuerst.
        usort($factors, static fn (array $a, array $b): int => $b['impact'] <=> $a['impact']);

        return ['score' => $score, 'factors' => $factors];
    }

    /**
     * Dreistufige Schwellen-Bewertung. Unter $t1 kostet es nichts, sonst $p1/$p2/$p3.
     */
    private function band(int $value, int $t1, int $t2, int $t3, int $p1, int $p2, int $p3): int
    {
        if ($value > $t3) {
            return $p3;
        }
        if ($value > $t2) {
            return $p2;
        }
        if ($value > $t1) {
            return $p1;
        }

        return 0;
    }

    /**
     * @param list<array{label: string, impact: int}> $factors
     */
    private function add(array &$factors, int $impact, string $label): void
    {
        if ($impact > 0) {
            $factors[] = ['label' => $label, 'impact' => $impact];
        }
    }
}
