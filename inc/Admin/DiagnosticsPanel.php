<?php

declare(strict_types=1);

namespace RhPerformance\Admin;

use RhBlueprint\Core\Settings\SettingsPage;
use RhPerformance\Diagnostics\MemoryRecorder;
use RhPerformance\Diagnostics\PageScan;
use RhPerformance\Diagnostics\PsiClient;
use RhPerformance\Diagnostics\ServerHealth;
use RhPerformance\Diagnostics\Sitemap;
use RhPerformance\Settings;
use RhPerformance\Support\Bytes;

/**
 * Der komplette Performance-Tab: ein kontextuelles Panel statt eines generischen
 * Settings-Formulars. Drei Karten, Status (Health + Speicher-Verlauf mit Switch),
 * Seiten-Scoring (Tabelle + PSI-Key im Zahnrad-Modal) und Optimierung (LCP-Switch).
 * Alle Einstellungen werden inline per Switch/Modal gepflegt und per AJAX
 * gespeichert, es gibt kein separates "Speichern"-Formular mehr.
 */
final class DiagnosticsPanel
{
    public const TAB_ID = 'performance';

    public const PSI_MODAL_ID = 'rhbp-perf-psikey';

    public const AJAX_MEM_RESET = 'rhbp_perf_memreset';

    public const AJAX_SITEMAP = 'rhbp_perf_sitemap';

    public const AJAX_SCAN = 'rhbp_perf_scan';

    public const AJAX_PSI = 'rhbp_perf_psi';

    public const AJAX_SETTING = 'rhbp_perf_setting';

    public const NONCE_MEM_RESET = 'rhbp_perf_memreset';

    public const NONCE_SCAN = 'rhbp_perf_scan';

    public const NONCE_SETTING = 'rhbp_perf_setting';

    public function boot(): void
    {
        // tab_content_after, weil dieser Tab keine GroupInterface-Gruppe hat: liefert
        // dieser Hook Inhalt, unterdrückt der Core seinen "keine Einstellungen"-Empty-State.
        add_action('rh-blueprint/settings/tab_content_after', [$this, 'render']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_ajax_' . self::AJAX_MEM_RESET, [$this, 'ajaxResetMemory']);
        add_action('wp_ajax_' . self::AJAX_SITEMAP, [$this, 'ajaxSitemap']);
        add_action('wp_ajax_' . self::AJAX_SCAN, [$this, 'ajaxScan']);
        add_action('wp_ajax_' . self::AJAX_PSI, [$this, 'ajaxPsi']);
        add_action('wp_ajax_' . self::AJAX_SETTING, [$this, 'ajaxSaveSetting']);
    }

    public function render(string $tabId): void
    {
        if ($tabId !== self::TAB_ID) {
            return;
        }

        echo '<div class="rhbp-perf-diag">';

        // Karte 1: Status (Health + Speicher).
        echo '<div class="rhbp-card rhbp-perf-diag__card">';
        echo '<h3 class="rhbp-perf-diag__title">' . esc_html__('Status', 'rh-performance') . '</h3>';
        $this->renderHealth();
        $this->renderMemory();
        echo '</div>';

        // Karte 2: Seiten-Scoring.
        $this->renderScoring();

        // Karte 3: Optimierung (aktive Maßnahmen).
        $this->renderOptimization();

        echo '</div>';
    }

    private function renderHealth(): void
    {
        echo '<div class="rhbp-perf-health">';
        foreach ((new ServerHealth())->rows() as $row) {
            $variant = match ($row['level']) {
                ServerHealth::LEVEL_OK => ' rhbp-pill--ok',
                ServerHealth::LEVEL_WARN => ' rhbp-pill--warn',
                default => '',
            };
            $title = $row['hint'] !== '' ? ' title="' . esc_attr($row['hint']) . '"' : '';

            echo '<span class="rhbp-pill' . esc_attr($variant) . '"' . $title . '>';
            echo '<span class="rhbp-pill__dot"></span>';
            echo '<span class="rhbp-perf-chip__k">' . esc_html($row['label']) . '</span> ' . esc_html($row['value']);
            echo '</span>';
        }
        echo '</div>';
    }

    private function renderMemory(): void
    {
        $mem = (new MemoryRecorder())->snapshot();

        echo '<div class="rhbp-perf-memline">';
        echo $this->switchControl(Settings::FIELD_RECORD_MEMORY, $mem['enabled'], __('Speicher-Verlauf aufzeichnen', 'rh-performance'));
        echo '<span class="rhbp-perf-memline__label">' . esc_html__('Speicher-Verlauf', 'rh-performance') . '</span>';

        if ($mem['samples'] === []) {
            $hint = $mem['enabled']
                ? __('läuft, erste Messung nach dem nächsten Seitenaufruf', 'rh-performance')
                : __('aus', 'rh-performance');
            echo '<span class="rhbp-perf-memline__hint">' . esc_html($hint) . '</span>';
            echo '</div>';
            return;
        }

        $limitLabel = $mem['limit'] > 0 ? Bytes::toMb($mem['limit']) : __('unbegrenzt', 'rh-performance');
        $usage = $mem['percent'] !== null ? $mem['percent'] . ' %' : Bytes::toMb($mem['peak']);

        echo '<span class="rhbp-perf-memline__txt">';
        printf(
            /* translators: 1: usage, 2: peak, 3: limit, 4: sample count. */
            esc_html__('%1$s · Peak %2$s von %3$s · %4$d Messungen', 'rh-performance'),
            '<strong>' . esc_html($usage) . '</strong>',
            esc_html(Bytes::toMb($mem['peak'])),
            esc_html($limitLabel),
            count($mem['samples'])
        );
        echo '</span>';
        $this->renderSparkline($mem['samples'], $mem['limit']);
        echo '<button type="button" class="rhbp-btn rhbp-btn--ghost rhbp-btn--icon" id="rhbp-perf-memreset" title="' . esc_attr__('Verlauf zurücksetzen', 'rh-performance') . '">&times;</button>';
        echo '</div>';
    }

    /**
     * @param list<array{t: int, peak: int, limit: int}> $samples
     */
    private function renderSparkline(array $samples, int $limit): void
    {
        $peaks = array_map(static fn (array $s): int => (int) $s['peak'], $samples);
        $count = count($peaks);
        if ($count < 2) {
            return;
        }

        $scale = $limit > 0 ? $limit : max($peaks);
        if ($scale <= 0) {
            return;
        }

        $points = [];
        foreach ($peaks as $i => $peak) {
            $x = round($i / ($count - 1) * 100, 2);
            $y = round(100 - min($peak / $scale, 1) * 100, 2);
            $points[] = $x . ',' . $y;
        }

        echo '<svg class="rhbp-perf-spark" viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="' . esc_attr__('Speicher-Verlauf', 'rh-performance') . '">';
        echo '<polyline points="' . esc_attr(implode(' ', $points)) . '" />';
        echo '</svg>';
    }

    private function renderScoring(): void
    {
        echo '<div class="rhbp-card rhbp-perf-diag__card">';

        echo '<div class="rhbp-perf-cardhead">';
        echo '<h3 class="rhbp-perf-diag__title">' . esc_html__('Seiten-Scoring', 'rh-performance') . '</h3>';
        echo '<button type="button" class="rhbp-btn rhbp-btn--ghost rhbp-btn--icon" data-rhbp-modal-open="' . esc_attr(self::PSI_MODAL_ID) . '" title="' . esc_attr__('PageSpeed-Insights einstellen', 'rh-performance') . '" aria-label="' . esc_attr__('PageSpeed-Insights einstellen', 'rh-performance') . '">' . $this->gearIcon() . '</button>';
        echo '</div>';

        echo '<p class="rhbp-perf-diag__intro">' . esc_html__('Misst die ganze Website oder eine einzelne Seite aus der Sitemap. Pro Seite Score, Ladezeit, Gewicht und Bremsen. Das echte Lighthouse-Detail von Google PageSpeed Insights gibt es pro Zeile (nur für öffentliche Live-URLs).', 'rh-performance') . '</p>';

        echo '<div class="rhbp-perf-test__bar">';
        echo '<select class="rhbp-perf-test__url" id="rhbp-perf-scan-target" disabled>';
        echo '<option>' . esc_html__('Seiten werden geladen…', 'rh-performance') . '</option>';
        echo '</select>';
        echo '<button type="button" class="rhbp-btn rhbp-btn--primary" id="rhbp-perf-scan" disabled>' . esc_html__('Scoring starten', 'rh-performance') . '</button>';
        echo '</div>';

        echo '<div class="rhbp-callout rhbp-callout--warn rhbp-perf-scan__error" id="rhbp-perf-scan-error" hidden></div>';
        echo '<div class="rhbp-perf-scan__note" id="rhbp-perf-scan-note" hidden></div>';
        echo '<div class="rhbp-perf-scan__rows" id="rhbp-perf-scan-rows"></div>';

        $this->renderPsiModal();

        echo '</div>';
    }

    private function renderPsiModal(): void
    {
        echo '<div class="rhbp-modal-backdrop" id="' . esc_attr(self::PSI_MODAL_ID) . '" data-rhbp-modal-backdrop>';
        echo '<div class="rhbp-modal" role="dialog" aria-modal="true" aria-label="' . esc_attr__('PageSpeed-Insights', 'rh-performance') . '">';

        echo '<div class="rhbp-modal__head">';
        echo '<div class="rhbp-modal__head-l">';
        echo '<h3 class="rhbp-modal__title">' . esc_html__('PageSpeed Insights', 'rh-performance') . '</h3>';
        echo '<p class="rhbp-modal__sub">' . esc_html__('API-Key für das echte Lighthouse-Detail.', 'rh-performance') . '</p>';
        echo '</div>';
        echo '<button type="button" class="rhbp-btn rhbp-btn--ghost rhbp-btn--icon" data-rhbp-modal-close aria-label="' . esc_attr__('Schließen', 'rh-performance') . '">&times;</button>';
        echo '</div>';

        echo '<div class="rhbp-modal__body">';
        echo '<div class="rhbp-field">';
        echo '<label class="rhbp-field__label" for="rhbp-perf-psikey-input">' . esc_html__('API-Key', 'rh-performance') . '</label>';
        echo '<input type="text" class="rhbp-perf-test__url" id="rhbp-perf-psikey-input" value="' . esc_attr(Settings::psiKey()) . '" autocomplete="off" spellcheck="false" />';
        echo '<p class="rhbp-field__desc">' . esc_html__('Optional. Ohne Key funktioniert das PSI-Detail auch, mit Key gibt es ein höheres Anfrage-Kontingent bei Google. Key gratis in der Google Cloud Console. Nur für öffentliche Live-URLs.', 'rh-performance') . '</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="rhbp-modal__foot">';
        echo '<button type="button" class="rhbp-btn rhbp-btn--ghost" data-rhbp-modal-close>' . esc_html__('Abbrechen', 'rh-performance') . '</button>';
        echo '<button type="button" class="rhbp-btn rhbp-btn--primary" id="rhbp-perf-psikey-save">' . esc_html__('Speichern', 'rh-performance') . '</button>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    private function renderOptimization(): void
    {
        echo '<div class="rhbp-card rhbp-perf-diag__card">';
        echo '<h3 class="rhbp-perf-diag__title">' . esc_html__('Optimierung', 'rh-performance') . '</h3>';

        echo '<div class="rhbp-perf-optrow">';
        echo $this->switchControl(Settings::FIELD_LCP_PRELOAD, Settings::lcpPreload(), __('LCP-Hintergrundbild vorladen', 'rh-performance'));
        echo '<div class="rhbp-perf-optrow__text">';
        echo '<strong>' . esc_html__('LCP-Hintergrundbild vorladen', 'rh-performance') . '</strong>';
        echo '<span>' . esc_html__('Preloadet das erste CSS-Hintergrundbild der Seite mit hoher Priorität. WordPress kann das bei <img> selbst, bei CSS-Hintergründen (z.B. Hero) nicht, das schließt diese Lücke.', 'rh-performance') . '</span>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Core-Switch, der seinen Wert per AJAX speichert (data-perf-toggle).
     */
    private function switchControl(string $field, bool $on, string $label): string
    {
        return sprintf(
            '<label class="rhbp-switch" title="%1$s"><input type="checkbox" data-perf-toggle="%2$s" value="1" %3$s aria-label="%1$s" /><span class="rhbp-switch__track" aria-hidden="true"></span></label>',
            esc_attr($label),
            esc_attr($field),
            checked($on, true, false)
        );
    }

    private function gearIcon(): string
    {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
    }

    public function enqueue(string $hook): void
    {
        unset($hook);
        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';
        if ($page !== SettingsPage::MENU_SLUG || $tab !== self::TAB_ID) {
            return;
        }

        wp_enqueue_style(
            'rh-performance-admin',
            RHPERF_PLUGIN_URL . 'assets/admin.css',
            ['rh-blueprint-settings'],
            (string) filemtime(RHPERF_PLUGIN_DIR . 'assets/admin.css')
        );

        wp_enqueue_script(
            'rh-performance-admin',
            RHPERF_PLUGIN_URL . 'assets/admin.js',
            [],
            (string) filemtime(RHPERF_PLUGIN_DIR . 'assets/admin.js'),
            true
        );

        wp_localize_script('rh-performance-admin', 'rhPerfDiag', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'memReset' => [
                'action' => self::AJAX_MEM_RESET,
                'nonce' => wp_create_nonce(self::NONCE_MEM_RESET),
            ],
            'setting' => [
                'action' => self::AJAX_SETTING,
                'nonce' => wp_create_nonce(self::NONCE_SETTING),
                'psiField' => Settings::FIELD_PSI_KEY,
            ],
            'scan' => [
                'sitemapAction' => self::AJAX_SITEMAP,
                'scanAction' => self::AJAX_SCAN,
                'psiAction' => self::AJAX_PSI,
                'nonce' => wp_create_nonce(self::NONCE_SCAN),
            ],
            'i18n' => [
                'wholeSite' => __('Ganze Website', 'rh-performance'),
                'scanRun' => __('Scoring starten', 'rh-performance'),
                'scanRunning' => __('Scanne…', 'rh-performance'),
                'scannedOf' => __('%1$d von %2$d Seiten gescannt.', 'rh-performance'),
                'unreachable' => __('nicht erreichbar', 'rh-performance'),
                'failed' => __('Fehlgeschlagen.', 'rh-performance'),
                'saved' => __('Gespeichert', 'rh-performance'),
                'save' => __('Speichern', 'rh-performance'),
                'yes' => __('ja', 'rh-performance'),
                'no' => __('nein', 'rh-performance'),
                'colPage' => __('Seite', 'rh-performance'),
                'colScore' => __('Score', 'rh-performance'),
                'colTime' => __('Zeit', 'rh-performance'),
                'colHtml' => __('HTML', 'rh-performance'),
                'colCache' => __('Cache', 'rh-performance'),
                'colCss' => __('CSS', 'rh-performance'),
                'colJs' => __('JS', 'rh-performance'),
                'colImg' => __('Bilder', 'rh-performance'),
                'colBlock' => __('Block', 'rh-performance'),
                'psi' => __('PSI', 'rh-performance'),
                'psiRunning' => __('…', 'rh-performance'),
                'psiScore' => __('Lighthouse-Score', 'rh-performance'),
                'psiField' => __('Felddaten', 'rh-performance'),
                'opportunities' => __('Größte Zeitfresser', 'rh-performance'),
                'noOpportunities' => __('Keine größeren Einsparpotenziale gefunden.', 'rh-performance'),
                'savingsMs' => __('%d ms', 'rh-performance'),
            ],
        ]);
    }

    public function ajaxResetMemory(): void
    {
        $this->guard(self::NONCE_MEM_RESET);
        (new MemoryRecorder())->reset();

        wp_send_json_success();
    }

    public function ajaxSitemap(): void
    {
        $this->guard(self::NONCE_SCAN);

        wp_send_json_success(['urls' => (new Sitemap())->urls()]);
    }

    public function ajaxScan(): void
    {
        $this->guard(self::NONCE_SCAN);

        $target = isset($_POST['target']) ? sanitize_text_field(wp_unslash((string) $_POST['target'])) : 'all';

        if ($target === 'all') {
            $urls = (new Sitemap())->urls();
        } else {
            $url = esc_url_raw($target);
            $urls = $url !== '' ? [$url] : [];
        }

        if ($urls === []) {
            wp_send_json_error(['message' => __('Keine Seiten zum Scannen gefunden.', 'rh-performance')]);
        }

        wp_send_json_success((new PageScan())->scan($urls));
    }

    public function ajaxPsi(): void
    {
        $this->guard(self::NONCE_SCAN);

        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash((string) $_POST['url'])) : '';
        $strategy = isset($_POST['strategy']) ? sanitize_key(wp_unslash((string) $_POST['strategy'])) : 'mobile';

        $result = (new PsiClient())->fetch($url, $strategy);

        if (($result['ok'] ?? false) !== true) {
            wp_send_json_error(['message' => $result['error'] ?? __('PSI-Abfrage fehlgeschlagen.', 'rh-performance')]);
        }

        wp_send_json_success($result);
    }

    public function ajaxSaveSetting(): void
    {
        $this->guard(self::NONCE_SETTING);

        $field = isset($_POST['field']) ? sanitize_key(wp_unslash((string) $_POST['field'])) : '';

        if (in_array($field, Settings::toggleFields(), true)) {
            $value = isset($_POST['value']) && (string) $_POST['value'] === '1';
            rhbp_update_setting(Settings::GROUP_ID, $field, $value);
            wp_send_json_success();
        }

        if ($field === Settings::FIELD_PSI_KEY) {
            $value = isset($_POST['value']) ? sanitize_text_field(wp_unslash((string) $_POST['value'])) : '';
            rhbp_update_setting(Settings::GROUP_ID, $field, $value);
            wp_send_json_success();
        }

        wp_send_json_error(['message' => __('Unbekannte Einstellung.', 'rh-performance')]);
    }

    private function guard(string $nonce): void
    {
        if (! current_user_can(SettingsPage::CAPABILITY)) {
            wp_send_json_error(['message' => __('Keine Berechtigung.', 'rh-performance')], 403);
        }

        check_ajax_referer($nonce, 'nonce');
    }
}
