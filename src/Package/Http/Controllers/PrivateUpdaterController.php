<?php

namespace Botble\DocsPro\Package\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\DocsPro\Package\PrivateUpdater\PluginUpdateClient;
use Botble\DocsPro\Package\PrivateUpdater\PluginUpdateInstaller;
use Botble\DocsPro\Package\PrivateUpdater\PluginUpdateRegistry;
use Botble\PluginManagement\Services\PluginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivateUpdaterController extends BaseController
{
    public function check(
        Request $request,
        PluginUpdateRegistry $registry,
        PluginUpdateClient $client
    ): JsonResponse {
        $updates = $client->check($registry->plugins(), $request->boolean('force'));

        return response()->json([
            'error' => false,
            'message' => 'Private plugin update check completed.',
            'data' => $updates,
            'checked_at' => now()->toDateTimeString(),
        ]);
    }

    public function update(
        string $plugin,
        Request $request,
        PluginUpdateRegistry $registry,
        PluginUpdateClient $client,
        PluginUpdateInstaller $installer,
        PluginService $pluginService
    ): JsonResponse {
        $pluginData = $registry->find($plugin);

        if (! $pluginData) {
            return response()->json([
                'error' => true,
                'message' => 'This plugin is not registered for Entomai private updates.',
            ], 404);
        }

        $update = [
            'version' => $request->input('version'),
            'latest_version' => $request->input('latest_version'),
            'update_id' => $request->input('update_id'),
        ];

        if (! filled($update['version'])) {
            $updates = $client->check(collect([$pluginData]), true);
            $update = $updates[$plugin] ?? $update;
        }

        return $installer->install($pluginData, $update, $pluginService);
    }
}
