<?php

declare(strict_types=1);

namespace RhPerformance;

/**
 * Single Source of Truth für die Performance-Settings. Die Werte werden nicht
 * mehr über ein generisches GroupInterface-Formular gepflegt, sondern
 * kontextuell im Diagnose-Panel (Switches + PSI-Modal) und per AJAX gespeichert.
 * Diese Klasse hält Keys, Defaults und typsichere Leser an einer Stelle.
 */
final class Settings
{
    public const GROUP_ID = 'performance';

    public const FIELD_LCP_PRELOAD = 'lcp_preload';

    public const FIELD_RECORD_MEMORY = 'record_memory';

    public const FIELD_PSI_KEY = 'psi_api_key';

    public static function lcpPreload(): bool
    {
        return (bool) rhbp_setting(self::GROUP_ID, self::FIELD_LCP_PRELOAD, true);
    }

    public static function recordMemory(): bool
    {
        return (bool) rhbp_setting(self::GROUP_ID, self::FIELD_RECORD_MEMORY, false);
    }

    public static function psiKey(): string
    {
        return trim((string) rhbp_setting(self::GROUP_ID, self::FIELD_PSI_KEY, ''));
    }

    /**
     * Boolean-Felder, die per Switch-Toggle gespeichert werden dürfen (Whitelist).
     *
     * @return list<string>
     */
    public static function toggleFields(): array
    {
        return [self::FIELD_LCP_PRELOAD, self::FIELD_RECORD_MEMORY];
    }
}
