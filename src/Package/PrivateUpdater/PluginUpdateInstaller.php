<?php

namespace Botble\DocsPro\Package\PrivateUpdater;

use Botble\Base\Facades\BaseHelper;
use Botble\Base\Supports\Zipper;
use Botble\PluginManagement\Services\PluginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class PluginUpdateInstaller
{
    public function install(array $plugin, array $update, PluginService $pluginService): JsonResponse
    {
        $version = (string) ($update['version'] ?? $update['latest_version'] ?? '');

        if ($version === '') {
            return response()->json(['error' => true, 'message' => 'The update response did not include a version.'], 422);
        }

        if (! filled($plugin['license_data'] ?? null)) {
            return response()->json([
                'error' => true,
                'message' => sprintf('Activate %s before installing private updates.', $plugin['name']),
            ], 422);
        }

        if (! ($plugin['product_identity_valid'] ?? true)) {
            return response()->json([
                'error' => true,
                'message' => sprintf('The configured product id does not match %s.', $plugin['name']),
            ], 422);
        }

        try {
            return $pluginService->updatePlugin($plugin['path'], function () use ($plugin, $update, $version, $pluginService): JsonResponse {
                $workPath = storage_path('app/entomai-plugin-updater/'.Str::slug($plugin['path']).'/'.now()->format('YmdHis'));
                $zipPath = $this->downloadPackage($plugin, $update, $version, $workPath);
                $extractPath = $workPath.'/extract';

                $this->validateZip($zipPath);

                if (! (new Zipper)->extract($zipPath, $extractPath)) {
                    throw new RuntimeException('Could not extract the update package.');
                }

                $packageRoot = $this->findPackageRoot($extractPath, $plugin, $version);
                $backupPath = $this->backupPlugin($plugin['path']);

                try {
                    $this->replacePlugin($plugin['path'], $packageRoot);
                } catch (Throwable $e) {
                    $this->restoreBackup($plugin['path'], $backupPath);

                    throw $e;
                }

                $pluginService->runMigrations($plugin['path']);
                $published = $pluginService->publishAssets($plugin['path']);

                if ($published['error']) {
                    throw new RuntimeException($published['message']);
                }

                $pluginService->publishTranslations($plugin['path']);

                $prefix = $plugin['license_prefix'] ?? '';
                if ($prefix) {
                    setting()->forceDelete([
                        "{$prefix}_last_update_available",
                        "{$prefix}_last_update_version",
                        "{$prefix}_last_update_id",
                        "{$prefix}_last_update_checked_at",
                    ]);
                    setting()->load(true);
                }

                return response()->json([
                    'error' => false,
                    'message' => sprintf('%s was updated to version %s.', $plugin['name'], $version),
                    'reload' => true,
                    'data' => [
                        'plugin' => $plugin['path'],
                        'version' => $version,
                        'update_id' => $update['update_id'] ?? null,
                    ],
                ]);
            });
        } catch (Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }
    }

    protected function downloadPackage(array $plugin, array $update, string $version, string $workPath): string
    {
        File::ensureDirectoryExists($workPath, 0775);

        $zipPath = $workPath.'/'.$plugin['path'].'-'.$this->normalizeVersion($version).'.zip';
        $downloadIdentifier = (string) ($update['update_id'] ?? '') ?: $version;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-API-KEY' => $plugin['api_key'],
            'X-API-URL' => $plugin['activation_url'] ?: url('/'),
            'X-API-IP' => request()->ip() ?: '127.0.0.1',
            'X-API-LANGUAGE' => app()->getLocale(),
        ])
            ->acceptJson()
            ->asJson()
            ->withoutVerifying()
            ->timeout(300)
            ->sink($zipPath)
            ->post($plugin['server'].'/api/external/update/'.rawurlencode($downloadIdentifier).'/download/main', [
                'product_id' => $plugin['product_id'],
                'license_data' => $plugin['license_data'],
                'client_name' => $plugin['client_name'] ?: null,
                'version' => $version,
                'update_id' => $update['update_id'] ?? null,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                $response->json('message') ?: sprintf('Download failed. HTTP %s.', $response->status())
            );
        }

        if (! File::isFile($zipPath) || File::size($zipPath) === 0) {
            throw new RuntimeException('The update package download was empty.');
        }

        return $zipPath;
    }

    protected function validateZip(string $zipPath): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The ZipArchive PHP extension is required.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The update package is not a valid ZIP file.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));

            if (
                str_starts_with($name, '/')
                || preg_match('/^[A-Za-z]:\//', $name)
                || str_contains($name, '../')
                || str_contains($name, '/..')
            ) {
                $zip->close();

                throw new RuntimeException('The update package contains unsafe file paths.');
            }
        }

        $zip->close();
    }

    protected function findPackageRoot(string $extractPath, array $plugin, string $expectedVersion): string
    {
        $candidates = [];

        if (File::isFile($extractPath.'/plugin.json')) {
            $candidates[] = $extractPath;
        }

        foreach (File::directories($extractPath) as $directory) {
            if (File::isFile($directory.'/plugin.json')) {
                $candidates[] = $directory;
            }
        }

        foreach ($candidates as $candidate) {
            $manifest = BaseHelper::getFileData($candidate.'/plugin.json');

            if ($this->packageMatchesPlugin($manifest, $plugin, $expectedVersion)) {
                return $candidate;
            }
        }

        throw new RuntimeException('The update package does not contain a matching plugin.json file.');
    }

    protected function packageMatchesPlugin(array $manifest, array $plugin, string $expectedVersion): bool
    {
        foreach (['manifest_id' => 'id', 'namespace' => 'namespace', 'provider' => 'provider'] as $pluginKey => $manifestKey) {
            if (
                filled($plugin[$pluginKey] ?? null)
                && (
                    ! filled($manifest[$manifestKey] ?? null)
                    || $plugin[$pluginKey] !== $manifest[$manifestKey]
                )
            ) {
                return false;
            }
        }

        $expectedProductId = (string) ($plugin['expected_product_id'] ?? $plugin['manifest_product_id'] ?? $plugin['product_id'] ?? '');
        $manifestProductId = (string) Arr::get($manifest, 'entomai_updater.product_id', '');

        if (
            $expectedProductId !== ''
            && ($manifestProductId === '' || ! hash_equals($expectedProductId, $manifestProductId))
        ) {
            return false;
        }

        $newVersion = (string) Arr::get($manifest, 'version', '');

        if ($newVersion === '') {
            throw new RuntimeException('The update package plugin.json does not include a version.');
        }

        if (version_compare($this->normalizeVersion($newVersion), $this->normalizeVersion($plugin['version']), '<')) {
            throw new RuntimeException('The update package version is older than the installed version.');
        }

        if (version_compare($this->normalizeVersion($newVersion), $this->normalizeVersion($expectedVersion), '<')) {
            throw new RuntimeException('The update package version is older than the requested version.');
        }

        return true;
    }

    protected function backupPlugin(string $plugin): string
    {
        $backupPath = storage_path('app/entomai-plugin-updater/backups/'.Str::slug($plugin).'-'.now()->format('YmdHis'));

        File::ensureDirectoryExists(dirname($backupPath), 0775);
        File::copyDirectory(plugin_path($plugin), $backupPath);

        return $backupPath;
    }

    protected function replacePlugin(string $plugin, string $packageRoot): void
    {
        $destination = plugin_path($plugin);

        if (File::isDirectory($destination) && ! File::deleteDirectory($destination)) {
            throw new RuntimeException('Could not remove the current plugin directory before installing the update.');
        }

        File::ensureDirectoryExists($destination, 0775);

        if (! File::copyDirectory($packageRoot, $destination)) {
            throw new RuntimeException('Could not copy the update package into the plugin directory.');
        }
    }

    protected function restoreBackup(string $plugin, string $backupPath): void
    {
        $destination = plugin_path($plugin);

        if (File::isDirectory($destination)) {
            File::deleteDirectory($destination);
        }

        File::ensureDirectoryExists($destination, 0775);
        File::copyDirectory($backupPath, $destination);
    }

    protected function normalizeVersion(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }
}
