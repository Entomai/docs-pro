<?php

namespace Botble\DocsPro\Package\PrivateUpdater;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PluginUpdateClient
{
    public function check(Collection $plugins, bool $force = false): array
    {
        $plugins = $plugins
            ->filter(fn (array $plugin): bool => filled($plugin['api_key'] ?? null))
            ->values();

        if ($plugins->isEmpty()) {
            return [];
        }

        $cacheKey = 'entomai_private_plugin_updates_'.sha1(json_encode(
            $plugins
                ->map(fn (array $plugin): array => Arr::only($plugin, ['path', 'product_id', 'server', 'version']))
                ->all()
        ));

        if ($force) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addMinutes(15), fn (): array => $this->requestUpdates($plugins));
    }

    protected function requestUpdates(Collection $plugins): array
    {
        $updates = [];

        $plugins
            ->groupBy(fn (array $plugin): string => sha1(implode('|', [
                $plugin['server'],
                $plugin['api_key'],
                $plugin['activation_url'],
            ])))
            ->each(function (Collection $group) use (&$updates): void {
                $bulkPayload = $this->checkBulk($group);

                if ($bulkPayload === null) {
                    $bulkPayload = [];

                    foreach ($group as $plugin) {
                        $bulkPayload[$plugin['product_id']] = $this->checkOne($plugin);
                    }
                }

                foreach ($group as $plugin) {
                    $payload = $bulkPayload[$plugin['product_id']] ?? null;
                    $update = $this->normalizeUpdatePayload($plugin, is_array($payload) ? $payload : []);

                    if ($update && $update['has_update']) {
                        $updates[$plugin['path']] = $update;
                    }
                }
            });

        return $updates;
    }

    protected function checkBulk(Collection $plugins): ?array
    {
        $first = $plugins->first();

        if (! $first) {
            return [];
        }

        $products = $plugins
            ->mapWithKeys(fn (array $plugin): array => [$plugin['product_id'] => $plugin['version']])
            ->all();

        try {
            $response = Http::withHeaders($this->headers($first))
                ->acceptJson()
                ->asJson()
                ->withoutVerifying()
                ->timeout(120)
                ->post($first['server'].'/api/external/update/check-bulk', [
                    'products' => $products,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $payload = $response->json() ?: [];

            return is_array($payload['data'] ?? null) ? $payload['data'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function checkOne(array $plugin): array
    {
        try {
            $response = Http::withHeaders($this->headers($plugin))
                ->acceptJson()
                ->asJson()
                ->withoutVerifying()
                ->timeout(120)
                ->post($plugin['server'].'/api/external/update/check', [
                    'product_id' => $plugin['product_id'],
                    'current_version' => $plugin['version'],
                ]);

            return $response->successful() ? ($response->json() ?: []) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    protected function normalizeUpdatePayload(array $plugin, array $payload): ?array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $version = $data['version'] ?? $data['latest_version'] ?? null;
        $hasUpdate = (bool) ($data['update_available'] ?? $data['has_update'] ?? false);

        if (! $hasUpdate && $version) {
            $hasUpdate = version_compare(
                $this->normalizeVersion((string) $version),
                $this->normalizeVersion($plugin['version']),
                '>'
            );
        }

        // Defensive: if server flagged has_update but version is not actually newer, correct it.
        if ($hasUpdate && $version) {
            $hasUpdate = version_compare(
                $this->normalizeVersion((string) $version),
                $this->normalizeVersion($plugin['version']),
                '>'
            );
        }

        return [
            'plugin' => $plugin['path'],
            'name' => $plugin['name'],
            'product_id' => $plugin['product_id'],
            'has_update' => $hasUpdate,
            'version' => $version,
            'latest_version' => $data['latest_version'] ?? $version,
            'current_version' => $plugin['version'],
            'update_id' => $data['update_id'] ?? $data['version_id'] ?? null,
            'released_at' => $data['released_at'] ?? $data['release_date'] ?? null,
            'summary' => $data['summary'] ?? null,
            'changelog' => $data['changelog'] ?? null,
            'message' => $data['message'] ?? $payload['message'] ?? null,
        ];
    }

    protected function headers(array $plugin): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-API-KEY' => $plugin['api_key'],
            'X-API-URL' => $plugin['activation_url'] ?: url('/'),
            'X-API-IP' => request()->ip() ?: '127.0.0.1',
            'X-API-LANGUAGE' => app()->getLocale(),
        ];
    }

    protected function normalizeVersion(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }
}
