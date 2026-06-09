<?php

namespace Botble\DocsPro\Package\PrivateUpdater;

use Botble\Base\Facades\BaseHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PluginUpdateRegistry
{
    public function plugins(): Collection
    {
        return collect(BaseHelper::scanFolder(plugin_path()))
            ->map(fn (string $path): ?array => $this->pluginFromPath($path))
            ->filter()
            ->values();
    }

    public function find(string $path): ?array
    {
        return $this->plugins()
            ->first(fn (array $plugin): bool => $plugin['path'] === $path);
    }

    protected function pluginFromPath(string $path): ?array
    {
        $pluginPath = plugin_path($path);
        $pluginJson = $pluginPath.'/plugin.json';

        if (! File::isDirectory($pluginPath) || ! File::isFile($pluginJson)) {
            return null;
        }

        $manifest = BaseHelper::getFileData($pluginJson);
        $updater = Arr::get($manifest, 'entomai_updater');

        if (! is_array($updater) || Arr::get($updater, 'enabled', true) === false) {
            return null;
        }

        $licensePrefix = (string) Arr::get($updater, 'license_prefix', str_replace('-', '_', $path));

        $isFree = (bool) Arr::get($updater, 'is_free', false);
        $manifestProductId = (string) Arr::get($updater, 'product_id', '');
        $expectedProductId = (string) (
            config("plugins.$path.general.expected_product_id")
            ?: Arr::get($updater, 'expected_product_id', $manifestProductId)
        );

        $productId = config("plugins.$path.general.product_id")
            ?: Arr::get($updater, 'product_id');

        $server = config("plugins.$path.general.master_server_url")
            ?: Arr::get($updater, 'server', $manifest['url'] ?? null);

        $apiKey = config("plugins.$path.general.api_key")
            ?: Arr::get($updater, 'api_key');

        // Free plugins don't need product_id/server/api_key — they have no license or update server
        if (! $isFree && (! $productId || ! $server)) {
            return null;
        }

        $productIdentityValid = $isFree
            || $expectedProductId === ''
            || ((string) $productId !== '' && hash_equals($expectedProductId, (string) $productId));

        $licenseData = (string) setting($licensePrefix.'_client_token', '');
        $activatedProductId = (string) setting($licensePrefix.'_client_product_id', '');

        if (
            ! $productIdentityValid
            || ($activatedProductId !== '' && ! hash_equals($expectedProductId ?: (string) $productId, $activatedProductId))
        ) {
            $licenseData = '';
        }

        return [
            'path' => $path,
            'manifest_id' => (string) Arr::get($manifest, 'id', ''),
            'name' => (string) Arr::get($manifest, 'name', Str::headline($path)),
            'description' => (string) Arr::get($manifest, 'description', ''),
            'author' => (string) Arr::get($manifest, 'author', ''),
            'author_url' => (string) Arr::get($manifest, 'url', ''),
            'namespace' => (string) Arr::get($manifest, 'namespace', ''),
            'provider' => (string) Arr::get($manifest, 'provider', ''),
            'version' => (string) Arr::get($manifest, 'version', '0.0.0'),
            'is_free' => $isFree,
            'is_active_in_cms' => in_array($path, get_active_plugins()),
            'product_identity_valid' => $productIdentityValid,
            'manifest_product_id' => $manifestProductId,
            'expected_product_id' => $expectedProductId,
            'product_id' => (string) ($productId ?? ''),
            'server' => rtrim((string) ($server ?? ''), '/'),
            'api_key' => (string) ($apiKey ?? ''),
            'license_prefix' => $licensePrefix,
            'license_data' => $licenseData,
            'client_name' => (string) setting($licensePrefix.'_client_name', ''),
            'activation_url' => (string) setting($licensePrefix.'_client_activation_url', url('/')),
            'support_url' => (string) Arr::get($updater, 'support_url', ''),
            'documentation_url' => (string) Arr::get($updater, 'documentation_url', ''),
        ];
    }
}
