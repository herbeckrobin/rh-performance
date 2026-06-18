# RH Performance

Performance-Diagnose und LCP-Preload. Teil der rh-blueprint Kollektion.

Ein Performance-Tab mit drei Bereichen: Status (Server-Health und Speicher-Verlauf), Seiten-Scoring (jede Seite messen, plus echtes Lighthouse) und Optimierung (LCP-Preload).

## Diagnose

### Status

- **Server-Health**: PHP-Version, OPcache, Object Cache, Memory Limit, aktive Plugins, WordPress-Version. Die Grundlagen, die das Boot-Tempo bestimmen, jeweils mit Hinweis wo es klemmt (z.B. OPcache aus).
- **Speicher-Verlauf** (opt-in): zeichnet bei echten Seitenaufrufen den PHP-Speicher-Peak auf (höchstens eine Messung pro Minute) und zeigt Spitzen-Auslastung plus Verlauf als Sparkline. Nur für ein Audit einschalten, danach wieder aus.

### Seiten-Scoring

- Misst die ganze Website oder eine einzelne Seite aus der Sitemap (Core-Sitemap, Yoast, rh-seo, Fallback auf veröffentlichte Seiten).
- Pro Seite ein Score von 0 bis 100 aus Ladezeit, Gewicht, Render-blocking CSS, Cachebarkeit und Bildformaten, dazu alle Werte als Tabelle.
- Pro Zeile das echte Lighthouse-Detail von Google PageSpeed Insights: Score, Web Vitals (LCP, CLS, TBT) und die größten Zeitfresser. Nur für öffentliche Live-URLs. Ein API-Key (optional) erhöht das Google-Kontingent.

## Optimierung

### LCP-Hintergrundbild vorladen

Schließt eine Lücke: WordPress 6.3+ setzt `fetchpriority="high"` selbst auf das wahrscheinliche LCP-`<img>`, aber ein CSS-`background-image` (z.B. ein Hero) kann es nicht erkennen, weil der Browser es erst nach dem CSS-Parsen entdeckt. Das Modul liest die erste Hintergrundbild-URL aus dem Inhalt und preloadet genau ein Bild mit hoher Priorität. Greift nur dort, wo wirklich ein Hintergrundbild liegt, Gradient-Heroes und Unterseiten ohne Bild werden übersprungen.

## Einstellungen

Alles unter **RH Blueprint → Performance**, kontextuell im Panel statt in einem Formular:

- Speicher-Verlauf: Switch in der Status-Karte.
- PageSpeed-Insights API-Key: Zahnrad-Symbol beim Seiten-Scoring.
- LCP-Preload: Switch in der Optimierung-Karte.

## Für Entwickler

- Filter `rh-blueprint/performance/lcp_image` ($url, $post): die LCP-URL setzen, wenn die Auto-Erkennung sie nicht sieht (z.B. Hintergrund nur per CSS, nicht im Block-Attribut), oder den Preload unterdrücken (leeren String zurückgeben).
- Filter `rh-blueprint/performance/sslverify` (bool, $url): steuert die TLS-Prüfung der Loopback-Diagnose (Selbsttest, Sitemap, Scan). In Testumgebungen mit selbstsigniertem Zertifikat (z.B. DDEV) auf false setzen, in Produktion an lassen.

## Installation

ZIP hochladen und aktivieren. Der geteilte Core ist gebündelt.

## Voraussetzungen

WordPress 6.5+, PHP 8.1+.
