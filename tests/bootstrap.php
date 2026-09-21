<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

// Configuration resolution for standalone integration testing:
// 1. CONFIG_FILE env var pointing to a YAML file
// 2. Default config/config.yaml (if it exists)
// 3. TYPESAFE_API_KEY environment variable

$configFile = getenv('CONFIG_FILE') ?: __DIR__ . '/../config/config.yaml';

if (file_exists($configFile)) {
    $GLOBALS['app_config'] = Yaml::parseFile($configFile);
} else {
    $GLOBALS['app_config'] = [
        'typesafe_api_key' => getenv('TYPESAFE_API_KEY') ?: null,
        'typesafe_base_url' => getenv('TYPESAFE_BASE_URL') ?: 'https://api.typesafe.ai/v1/',
        'typesafe_model' => getenv('TYPESAFE_MODEL') ?: 'jev-latest',
    ];
}

/**
 * Get configuration value helper for tests.
 */
function app_config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['app_config'] ?? [];
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? $default;
}