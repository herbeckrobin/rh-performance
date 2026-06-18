<?php

declare(strict_types=1);

namespace RhPerformance\Diagnostics;

use RhPerformance\Support\Http;
use SimpleXMLElement;

/**
 * Sammelt die zu prüfenden Seiten-URLs. Bevorzugt die Sitemap (Core-Sitemap,
 * Yoast/rh-seo), fällt auf veröffentlichte Seiten/Beiträge aus der DB zurück,
 * wenn keine Sitemap erreichbar ist. Gedeckelt, damit ein Scan nicht
 * unkontrolliert über hunderte URLs läuft.
 */
final class Sitemap
{
    private const MAX_URLS = 50;

    private const MAX_SUBSITEMAPS = 8;

    /**
     * @return list<string>
     */
    public function urls(): array
    {
        foreach ($this->candidateSitemaps() as $candidate) {
            $urls = $this->fromSitemap($candidate);
            if ($urls !== []) {
                return array_values(array_slice(array_unique($urls), 0, self::MAX_URLS));
            }
        }

        return $this->fromDatabase();
    }

    /**
     * @return list<string>
     */
    private function candidateSitemaps(): array
    {
        return [
            home_url('/wp-sitemap.xml'),      // WP-Core
            home_url('/sitemap_index.xml'),   // Yoast / rh-seo
            home_url('/sitemap.xml'),         // generisch
        ];
    }

    /**
     * @return list<string>
     */
    private function fromSitemap(string $url): array
    {
        $xml = $this->fetchXml($url);
        if ($xml === null) {
            return [];
        }

        // Sitemap-Index: auf die Sub-Sitemaps folgen.
        if (isset($xml->sitemap)) {
            $urls = [];
            $seen = 0;
            foreach ($xml->sitemap as $entry) {
                if ($seen++ >= self::MAX_SUBSITEMAPS) {
                    break;
                }
                $loc = trim((string) $entry->loc);
                if ($loc === '') {
                    continue;
                }
                $sub = $this->fetchXml($loc);
                if ($sub !== null) {
                    foreach ($this->locs($sub) as $pageUrl) {
                        $urls[] = $pageUrl;
                        if (count($urls) >= self::MAX_URLS) {
                            return $urls;
                        }
                    }
                }
            }
            return $urls;
        }

        return $this->locs($xml);
    }

    /**
     * Extrahiert die <url><loc>-Einträge eines urlset.
     *
     * @return list<string>
     */
    private function locs(SimpleXMLElement $xml): array
    {
        if (! isset($xml->url)) {
            return [];
        }

        $urls = [];
        foreach ($xml->url as $entry) {
            $loc = trim((string) $entry->loc);
            if ($loc !== '') {
                $urls[] = $loc;
            }
            if (count($urls) >= self::MAX_URLS) {
                break;
            }
        }

        return $urls;
    }

    private function fetchXml(string $url): ?SimpleXMLElement
    {
        $response = Http::get($url, 10);
        if (is_wp_error($response)) {
            return null;
        }
        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = trim((string) wp_remote_retrieve_body($response));
        if ($body === '' || ! str_contains($body, '<')) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml instanceof SimpleXMLElement ? $xml : null;
    }

    /**
     * Fallback: veröffentlichte Seiten + Beiträge direkt aus der DB.
     *
     * @return list<string>
     */
    private function fromDatabase(): array
    {
        $ids = get_posts([
            'post_type' => ['page', 'post'],
            'post_status' => 'publish',
            'numberposts' => self::MAX_URLS,
            'fields' => 'ids',
            'orderby' => 'menu_order date',
            'order' => 'ASC',
        ]);

        $urls = [home_url('/')];
        foreach ($ids as $id) {
            $permalink = get_permalink((int) $id);
            if (is_string($permalink) && $permalink !== '') {
                $urls[] = $permalink;
            }
        }

        return array_values(array_slice(array_unique($urls), 0, self::MAX_URLS));
    }
}
