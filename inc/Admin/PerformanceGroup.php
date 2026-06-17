<?php

declare(strict_types=1);

namespace RhPerformance\Admin;

use RhBlueprint\Core\Settings\GroupInterface;
use RhBlueprint\Core\Settings\SettingField;

/**
 * Settings-Gruppe für Performance.
 *
 * Default an: der LCP-Preload greift nur, wenn im Seiteninhalt wirklich ein
 * CSS-Hintergrundbild im ersten Block liegt, sonst passiert nichts. Reiner Gewinn.
 */
final class PerformanceGroup implements GroupInterface
{
    public const GROUP_ID = 'performance';

    public const FIELD_LCP_PRELOAD = 'lcp_preload';

    public function id(): string
    {
        return self::GROUP_ID;
    }

    public function tab(): string
    {
        return 'performance';
    }

    public function title(): string
    {
        return __('Performance', 'rh-performance');
    }

    public function description(): string
    {
        return __('Hilft dem Browser, das wichtigste Bild (LCP) früh zu laden.', 'rh-performance');
    }

    public function fields(): array
    {
        return [
            new SettingField(
                id: self::FIELD_LCP_PRELOAD,
                type: SettingField::TYPE_BOOLEAN,
                label: __('LCP-Hintergrundbild vorladen', 'rh-performance'),
                description: __('Preloadet das erste CSS-Hintergrundbild der Seite mit hoher Priorität. WordPress kann das bei <img> selbst, bei CSS-Hintergründen (z.B. Hero) nicht, das schließt diese Lücke.', 'rh-performance'),
                default: true,
                keywords: ['lcp', 'preload', 'performance', 'hero', 'bild', 'fetchpriority'],
            ),
        ];
    }
}
