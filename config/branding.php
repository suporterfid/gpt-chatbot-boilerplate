<?php
/**
 * Branding Configuration
 *
 * This file manages application branding settings that can be customized
 * via environment variables without code changes.
 */

if (!function_exists('getEnvValue')) {
    /**
     * Get environment variable value
     * Checks both $_ENV and getenv() for compatibility
     */
    function getEnvValue(string $key) {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);

        return $value === false ? null : $value;
    }
}

return [
    /**
     * Brand Name
     * The main application/product name displayed throughout the UI
     * Default: "Assistant Chat Boilerplate"
     */
    'brand_name' => getEnvValue('BRAND_NAME') ?: 'Assistant Chat Boilerplate',

    /**
     * Logo URL
     * URL to the brand logo image (can be absolute URL or relative path)
     * Leave empty if no logo is used
     * Default: empty
     */
    'logo_url' => getEnvValue('LOGO_URL') ?: '',

    /**
     * Powered By Label
     * Text label shown in footers or attribution sections
     * Default: "Powered by"
     */
    'powered_by_label' => getEnvValue('POWERED_BY_LABEL') ?: 'Powered by',
];
