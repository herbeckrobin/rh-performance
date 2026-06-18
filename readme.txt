=== RH Performance ===
Contributors: robinherbeck
Tags: performance, diagnostics, pagespeed, lighthouse, lcp, core web vitals
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Performance diagnostics (server health, memory history, per-page scoring with PageSpeed Insights) plus LCP background-image preloading.

== Description ==

RH Performance adds a Performance tab with three areas.

Status: server health (PHP version, OPcache, object cache, memory limit, active plugins) and an opt-in memory history that records the PHP peak of real page views and shows it as a sparkline.

Page scoring: measures the whole site or a single page from the sitemap. Each page gets a 0-100 score from response time, weight, render-blocking CSS, cacheability and image formats, shown as a table. Per row you can pull the real Lighthouse detail from Google PageSpeed Insights (score, Web Vitals, top opportunities). Public live URLs only; an optional API key raises the Google quota.

Optimisation: preloads the LCP background image that WordPress cannot prioritise by itself. WordPress 6.3+ sets fetchpriority="high" on the likely LCP <img>, but a CSS background-image hero stays invisible to that. The module preloads exactly one image with high priority, skipping pages without a background image.

Part of the rh-blueprint collection. Everything lives under RH Blueprint > Performance.

== Changelog ==

= 0.3.0 =
* Add diagnostics panel: server health, opt-in memory history with sparkline.
* Add page scoring from the sitemap (whole site or single page) with a per-page loopback score and the real Google PageSpeed Insights / Lighthouse detail per row.
* Settings moved into the panel (switches and a PSI key modal), no more generic settings form.

= 0.1.0 =
* Initial release: LCP preload for CSS background images with fetchpriority, single-image, filter override.
