# RH Performance

LCP-Preload für CSS-Hintergrundbilder. Teil der rh-blueprint Kollektion.

Schließt eine konkrete Lücke: WordPress 6.3+ setzt `fetchpriority="high"` selbst auf das wahrscheinliche LCP-`<img>`, aber CSS-`background-image` (z.B. ein Hero) kann es nicht erkennen und nicht priorisieren. Der Browser entdeckt es erst nach dem CSS-Parsen, was den Largest Contentful Paint bremst.

## Was es macht

- Liest die erste Hintergrundbild-URL aus dem Seiteninhalt und preloadet **genau ein** Bild mit hoher Priorität (`<link rel="preload" as="image" fetchpriority="high">`). Kein Über-Preloading, das die Priorität entwerten würde.
- Greift nur, wo wirklich ein Hintergrundbild im Inhalt liegt. Gradient-Heroes und Unterseiten ohne Bild werden übersprungen.

## Einstellungen

Im Backend unter **RH Blueprint → Performance**: LCP-Hintergrundbild vorladen an/aus (Default an).

## Für Entwickler

Filter `rh-blueprint/performance/lcp_image` ($url, $post): die LCP-URL setzen, wenn die Auto-Erkennung sie nicht sieht (z.B. Hintergrund nur per CSS, nicht im Block-Attribut), oder den Preload unterdrücken (leeren String zurückgeben).

## Installation

ZIP hochladen und aktivieren. Der geteilte Core ist gebündelt.

## Voraussetzungen

WordPress 6.5+, PHP 8.1+.
