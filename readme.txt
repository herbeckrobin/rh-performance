=== RH Performance ===
Contributors: robinherbeck
Tags: performance, lcp, preload, fetchpriority, core web vitals
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Preloads the LCP background image that WordPress cannot prioritise by itself, to improve Largest Contentful Paint.

== Description ==

WordPress 6.3+ sets fetchpriority="high" on the likely LCP <img> automatically. CSS background-image heroes are invisible to that: the browser only discovers them after parsing CSS. RH Performance reads the first background-image URL from the page content and preloads exactly one image (no over-preloading) with high priority. Pages without a background image (gradient heroes, subpages) are skipped.

Override or supply the URL via the rh-blueprint/performance/lcp_image filter when auto-detection cannot see it (e.g. background set purely in CSS).

Part of the rh-blueprint collection. Settings live under RH Blueprint > Performance.

== Changelog ==

= 0.1.0 =
* Initial release: LCP preload for CSS background images with fetchpriority, single-image, filter override.
