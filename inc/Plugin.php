<?php

declare(strict_types=1);

namespace RhPerformance;

use RhBlueprint\Core\Core;
use RhBlueprint\Core\Settings\SettingsPage;
use RhPerformance\Admin\PerformanceGroup;

/**
 * Bootstrap von rh-performance. Hängt am Core-Hook `rh-blueprint/core/booted`. Braucht nur den Core.
 */
final class Plugin
{
    public static function boot(): void
    {
        if (class_exists(UpdateChecker::class)) {
            (new UpdateChecker())->boot();
        }

        add_action('rh-blueprint/core/booted', [self::class, 'onCoreBooted']);
    }

    public static function onCoreBooted(Core $core): void
    {
        $core->settings()->registerTab('performance', __('Performance', 'rh-performance'), 80);
        $core->settings()->registerGroup(new PerformanceGroup());

        (new Performance())->boot();

        add_filter('rh-blueprint/dashboard/quick_links', static function (array $links): array {
            $links[] = [
                'label' => __('Performance', 'rh-performance'),
                'url' => admin_url('admin.php?page=' . SettingsPage::MENU_SLUG . '&tab=performance'),
                'icon' => 'performance',
            ];
            return $links;
        });
    }
}
