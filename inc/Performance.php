<?php

declare(strict_types=1);

namespace RhPerformance;

use RhPerformance\Admin\PerformanceGroup;
use WP_Post;

/**
 * LCP-Preload für CSS-Hintergrundbilder.
 *
 * WordPress (6.3+) setzt fetchpriority="high" selbst auf das wahrscheinliche
 * LCP-<img>. CSS-`background-image` (z.B. ein Hero) kann es aber nicht erkennen
 * und nicht priorisieren, der Browser entdeckt es erst nach dem CSS-Parsen. Wir
 * lesen die erste backgroundImage-URL aus dem Seiteninhalt und preloaden genau
 * EINE (kein Über-Preloading) mit hoher Priorität. Gradient-Heroes/Unterseiten
 * ohne Hintergrundbild werden übersprungen.
 */
final class Performance
{
    public function boot(): void
    {
        if (! (bool) rhbp_setting(PerformanceGroup::GROUP_ID, PerformanceGroup::FIELD_LCP_PRELOAD, true)) {
            return;
        }

        add_action('wp_head', [$this, 'preloadLcpImage'], 2);
    }

    public function preloadLcpImage(): void
    {
        if (is_admin() || ! is_singular()) {
            return;
        }

        $post = get_post();
        $content = $post instanceof WP_Post ? (string) $post->post_content : '';

        $url = '';
        if (preg_match('/"backgroundImage":\{"url":"([^"]+\.(?:webp|jpe?g|png|avif|gif))"/i', $content, $m)) {
            // Block-JSON escaped Slashes (https:\/\/...), vor esc_url entschärfen.
            $url = str_replace('\\/', '/', $m[1]);
        }

        // Fallback/Override: Theme kann die LCP-URL setzen (oder leeren), falls die
        // Auto-Erkennung nicht greift (z.B. Hintergrund nur per CSS, nicht im Block).
        $url = (string) apply_filters('rh-blueprint/performance/lcp_image', $url, $post);

        if ($url === '') {
            return;
        }

        echo '<link rel="preload" as="image" fetchpriority="high" href="' . esc_url($url) . '">' . "\n";
    }
}
