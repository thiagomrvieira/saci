<?php

namespace ThiagoVieira\Saci;

class SaciConfig
{
    /**
     * Get the configuration value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return config("saci.{$key}", $default);
    }

    /**
     * Check if Saci is enabled.
     */
    public static function isEnabled(): bool
    {
        return self::get('enabled', true);
    }

    /**
     * Check if auto-registration is enabled.
     */
    public static function isAutoRegistrationEnabled(): bool
    {
        return self::get('auto_register_middleware', true);
    }

    /**
     * Get allowed environments.
     */
    public static function getAllowedEnvironments(): array
    {
        return self::get('environments', ['local']);
    }

    /**
     * Get hidden data fields.
     */
    public static function getHiddenFields(): array
    {
        return self::get('hide_data_fields', [
            'password',
            'token',
            'secret',
            'api_key',
            'credentials'
        ]);
    }

    /**
     * Get UI settings.
     */
    public static function getUISettings(): array
    {
        return self::get('ui', [
            'position' => 'bottom',
            'theme' => 'dark',
            'max_height' => '30vh'
        ]);
    }

    /**
     * Get UI position.
     */
    public static function getUIPosition(): string
    {
        return self::getUISettings()['position'] ?? 'bottom';
    }

    /**
     * Get UI theme.
     */
    public static function getUITheme(): string
    {
        return self::getUISettings()['theme'] ?? 'dark';
    }

    /**
     * Get UI max height.
     */
    public static function getUIMaxHeight(): string
    {
        return self::getUISettings()['max_height'] ?? '30vh';
    }
}