<?php

namespace Botble\DocsPro\Package;

use Botble\Base\Facades\BaseHelper;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class LicenseClient
{
    protected string $apiUrl;

    protected string $apiKey;

    protected string $productId;

    protected string $pluginName;

    protected string $pluginPrefix;

    protected string $version;

    public function __construct(
        string $pluginPrefix,
        string $productId,
        string $apiUrl,
        string $apiKey,
        string $pluginName
    ) {
        $this->pluginPrefix = $pluginPrefix;
        $this->productId = $productId;
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        $this->pluginName = $pluginName;
        $this->version = $this->resolvePluginVersion();
    }

    protected function resolvePluginVersion(): string
    {
        $dirName = str_replace('_', '-', $this->pluginPrefix);
        $path = plugin_path("{$dirName}/plugin.json");

        if (File::exists($path)) {
            $data = BaseHelper::getFileData($path);

            return $data['version'] ?? '1.0.0';
        }

        return '1.0.0';
    }

    public function getCurrentVersion(): string
    {
        return $this->version;
    }

    protected function getHeaders(): array
    {
        $activationUrl = trim((string) setting("{$this->pluginPrefix}_client_activation_url", '')) ?: url('/');

        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-API-KEY' => $this->apiKey,
            'X-API-URL' => $activationUrl,
            'X-API-IP' => request()->ip() ?: '127.0.0.1',
            'X-API-LANGUAGE' => app()->getLocale(),
        ];
    }

    protected function clientName(): ?string
    {
        $name = trim((string) setting("{$this->pluginPrefix}_client_name", ''));

        return $name !== '' ? $name : null;
    }

    public function verifyLicense(): bool
    {
        $token = setting("{$this->pluginPrefix}_client_token");

        if (! $token) {
            return false;
        }

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->apiUrl}/api/external/license/verify", [
                    'product_id' => $this->productId,
                    'license_data' => $token,
                    'client_name' => $this->clientName(),
                ]);

            if ($response->successful()) {
                return $response->json('status') === true
                    && (
                        $response->json('is_active') === true
                        || $response->json('data.is_active') === true
                    );
            }
        } catch (\Exception $e) {
            info("[Entomai] Failed to verify license for {$this->pluginName}", ['error' => $e->getMessage()]);
        }

        return false;
    }

    public function deactivateLicense(): bool
    {
        $token = setting("{$this->pluginPrefix}_client_token");

        if (! $token) {
            return true;
        }

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->apiUrl}/api/external/license/deactivate", [
                    'product_id' => $this->productId,
                    'license_data' => $token,
                    'client_name' => $this->clientName(),
                ]);

            if ($response->successful() && $response->json('status') === true) {
                setting()->forceDelete([
                    "{$this->pluginPrefix}_client_token",
                    "{$this->pluginPrefix}_client_license_code",
                ]);
                setting()->load(true);

                return true;
            }
        } catch (\Exception $e) {
            info("[Entomai] Failed to deactivate license for {$this->pluginName}", ['error' => $e->getMessage()]);
        }

        return false;
    }

    public function checkForUpdate(): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->apiUrl}/api/external/update/check", [
                    'product_id' => $this->productId,
                    'current_version' => $this->version,
                ]);

            if ($response->successful()) {
                $payload = $response->json() ?: [];
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
                $latestVersion = $data['version'] ?? $data['latest_version'] ?? null;
                $hasUpdate = (bool) ($data['update_available'] ?? $data['has_update'] ?? false);

                if (! $hasUpdate && $latestVersion) {
                    $hasUpdate = version_compare(
                        $this->normalizeVersion((string) $latestVersion),
                        $this->normalizeVersion($this->version),
                        '>'
                    );
                }

                return [
                    'success' => true,
                    'has_update' => $hasUpdate,
                    'version' => $latestVersion,
                    'latest_version' => $latestVersion,
                    'current_version' => $this->version,
                    'update_id' => $data['update_id'] ?? $data['version_id'] ?? null,
                    'released_at' => $data['released_at'] ?? $data['release_date'] ?? null,
                    'summary' => $data['summary'] ?? null,
                    'changelog' => $data['changelog'] ?? '',
                    'message' => $data['message'] ?? $payload['message'] ?? null,
                ];
            }

            return [
                'success' => false,
                'has_update' => false,
                'current_version' => $this->version,
                'http_status' => $response->status(),
                'message' => $response->json('message')
                    ?: sprintf('Update check failed with HTTP %s.', $response->status()),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'has_update' => false,
                'current_version' => $this->version,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function normalizeVersion(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }
}
