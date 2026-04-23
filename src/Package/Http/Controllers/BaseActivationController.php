<?php

namespace Botble\DocsPro\Package\Http\Controllers;

use Botble\Base\Facades\BaseHelper;
use Botble\Base\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Botble\DocsPro\Package\LicenseClient;

abstract class BaseActivationController extends BaseController
{
    // ── Adapt these four in your plugin's ClientActivationController ─────────
    protected string $pluginPrefix = '';    // snake_case, e.g. 'translation_pro'
    protected string $settingsRoute = '';   // e.g. 'translation-pro.settings'
    protected string $callbackRoute = '';   // e.g. 'translation-pro.client-activation.callback'
    protected string $translationPath = ''; // e.g. 'plugins/translation-pro::translation-pro'
    // ─────────────────────────────────────────────────────────────────────────

    protected function getProductId(): string
    {
        return (string) $this->resolveUpdaterValue('product_id', 'product_id', '');
    }

    protected function getLicenseServer(): string
    {
        return (string) $this->resolveUpdaterValue('server', 'master_server_url', 'https://entomai.com');
    }

    protected function getApiKey(): string
    {
        return (string) $this->resolveUpdaterValue('api_key', 'api_key', '');
    }

    protected function getPluginName(): string
    {
        return ucwords(str_replace('_', ' ', $this->pluginPrefix));
    }

    /**
     * Resolves a value with this priority:
     *   1. config/env at plugins.{slug}.general.{configKey} (env override, no _config key needed)
     *   2. Direct value in the entomai_updater block of plugin.json
     *   3. $default
     */
    protected function resolveUpdaterValue(string $valueKey, string $configKey, mixed $default = null): mixed
    {
        $slug = str_replace('_', '-', $this->pluginPrefix);

        $fromConfig = config("plugins.{$slug}.general.{$configKey}");
        if ($fromConfig) {
            return $fromConfig;
        }

        $direct = Arr::get($this->readUpdaterBlock(), $valueKey);
        if ($direct) {
            return $direct;
        }

        return $default;
    }

    protected function readUpdaterBlock(): array
    {
        $slug = str_replace('_', '-', $this->pluginPrefix);
        $path = plugin_path("{$slug}/plugin.json");

        if (! File::exists($path)) {
            return [];
        }

        return Arr::get(BaseHelper::getFileData($path), 'entomai_updater', []);
    }

    protected function getLicenseClient(): LicenseClient
    {
        return new LicenseClient(
            $this->pluginPrefix,
            $this->getProductId(),
            $this->getLicenseServer(),
            $this->getApiKey(),
            $this->getPluginName()
        );
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function start(Request $request)
    {
        $state = Str::random(40);
        $stateKey = $this->pluginPrefix.'_client_activation_state';
        $urlKey = $this->pluginPrefix.'_client_activation_url';

        session([$stateKey => $state, $urlKey => URL::to('/')]);

        $url = sprintf(
            '%s/license/activate?product_id=%s&redirect_uri=%s&state=%s',
            rtrim($this->getLicenseServer(), '/'),
            urlencode($this->getProductId()),
            urlencode(URL::route($this->callbackRoute)),
            urlencode($state)
        );

        if ($this->wantsJson($request)) {
            return response()->json(['success' => true, 'redirect_url' => $url]);
        }

        return redirect()->away($url);
    }

    public function callback(Request $request)
    {
        $status = $request->query('status');
        $state = $request->query('state');
        $token = $request->query('token');
        $licenseCode = $request->query('license_code');
        $clientEmail = $request->query('client_email');
        $clientName = $request->query('client_name');

        $stateKey = $this->pluginPrefix.'_client_activation_state';
        $urlKey = $this->pluginPrefix.'_client_activation_url';
        $sessionState = session($stateKey);

        if (! $state || ! $sessionState || $state !== $sessionState) {
            session()->forget([$stateKey, $urlKey]);

            return redirect()
                ->route($this->settingsRoute)
                ->with('error_msg', trans($this->translationPath.'.activation_error_state_mismatch'));
        }

        session()->forget($stateKey);

        if ($status !== 'success' || ! $token) {
            session()->forget($urlKey);

            return redirect()
                ->route($this->settingsRoute)
                ->with('error_msg', trans($this->translationPath.'.activation_error_failed'));
        }

        $settings = setting()
            ->set($this->pluginPrefix.'_client_token', $token)
            ->set($this->pluginPrefix.'_client_license_code', $licenseCode)
            ->set($this->pluginPrefix.'_client_activation_url', session($urlKey, URL::to('/')));

        if ($clientEmail) {
            $settings->set($this->pluginPrefix.'_client_email', $clientEmail);
        }

        if ($clientName) {
            $settings->set($this->pluginPrefix.'_client_name', $clientName);
        }

        $settings->save();
        session()->forget($urlKey);

        return redirect()
            ->route($this->settingsRoute)
            ->with('success_msg', trans($this->translationPath.'.activation_success'));
    }

    public function verify(Request $request)
    {
        if ($this->getLicenseClient()->verifyLicense()) {
            $message = 'Your license is verified and active.';

            if ($this->wantsJson($request)) {
                return $this->activationJson(true, $message, ['activated' => true]);
            }

            return redirect()->route($this->settingsRoute)->with('success_msg', $message);
        }

        $message = 'Your license could not be verified. It may have expired or been deactivated remotely.';

        if ($this->wantsJson($request)) {
            return $this->activationJson(false, $message, ['activated' => true], 422);
        }

        return redirect()->route($this->settingsRoute)->with('error_msg', $message);
    }

    public function deactivate(Request $request)
    {
        if ($this->getLicenseClient()->deactivateLicense()) {
            $this->clearLocalLicenseData();

            $message = 'License deactivated successfully.';

            if ($this->wantsJson($request)) {
                return $this->activationJson(true, $message, ['activated' => false, 'reload' => true]);
            }

            return redirect()->route($this->settingsRoute)->with('success_msg', $message);
        }

        $message = 'Could not deactivate the license on the server. The local license was kept so you can retry.';

        if ($this->wantsJson($request)) {
            return $this->activationJson(false, $message, ['activated' => true, 'reload' => false], 422);
        }

        return redirect()->route($this->settingsRoute)->with('error_msg', $message);
    }

    public function forceDeactivate(Request $request)
    {
        $this->clearLocalLicenseData();
        $message = 'Local license data was removed. You can activate a new license now.';

        if ($this->wantsJson($request)) {
            return $this->activationJson(true, $message, ['activated' => false, 'reload' => true]);
        }

        return redirect()->route($this->settingsRoute)->with('success_msg', $message);
    }

    public function checkUpdate(Request $request)
    {
        $client = $this->getLicenseClient();
        $update = $client->checkForUpdate();

        if (($update['success'] ?? true) === false) {
            $message = $update['message'] ?? 'Could not check for updates.';

            if ($this->wantsJson($request)) {
                return $this->activationJson(false, $message, [
                    'update' => $this->formatUpdatePayload($update, $client),
                ], 422);
            }

            return redirect()->route($this->settingsRoute)->with('error_msg', $message);
        }

        $this->storeUpdateState($update);

        if ($update['has_update']) {
            $version = $update['version'] ?? null;
            $message = $version ? "An update to version {$version} is available!" : 'An update is available!';

            if ($this->wantsJson($request)) {
                return $this->activationJson(true, $message, [
                    'update' => $this->formatUpdatePayload($update, $client),
                ]);
            }

            return redirect()->route($this->settingsRoute)->with('success_msg', $message);
        }

        $message = 'You are running the latest version.';

        if ($this->wantsJson($request)) {
            return $this->activationJson(true, $message, [
                'update' => $this->formatUpdatePayload($update, $client),
            ]);
        }

        return redirect()->route($this->settingsRoute)->with('success_msg', $message);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function activationJson(bool $success, string $message, array $data = [], int $status = 200): JsonResponse
    {
        return response()->json(['success' => $success, 'message' => $message, ...$data], $status);
    }

    protected function wantsJson(Request $request): bool
    {
        return $request->ajax() || $request->expectsJson() || $request->wantsJson();
    }

    protected function storeUpdateState(array $update): void
    {
        $p = $this->pluginPrefix;

        setting()
            ->set("{$p}_last_update_available", (bool) ($update['has_update'] ?? false))
            ->set("{$p}_last_update_version", (string) ($update['version'] ?? $update['latest_version'] ?? ''))
            ->set("{$p}_last_update_id", (string) ($update['update_id'] ?? ''))
            ->set("{$p}_last_update_checked_at", now()->toDateTimeString())
            ->save();
    }

    protected function clearLocalLicenseData(): void
    {
        $p = $this->pluginPrefix;

        setting()->forceDelete([
            "{$p}_client_token",
            "{$p}_client_license_code",
            "{$p}_client_email",
            "{$p}_client_name",
            "{$p}_client_activation_url",
            "{$p}_last_update_available",
            "{$p}_last_update_version",
            "{$p}_last_update_id",
            "{$p}_last_update_checked_at",
        ]);

        setting()->load(true);
    }

    protected function formatUpdatePayload(array $update, LicenseClient $client): array
    {
        $hasUpdate = (bool) ($update['has_update'] ?? false);
        $version = $update['version'] ?? $update['latest_version'] ?? null;
        $currentVersion = $update['current_version'] ?? $client->getCurrentVersion();

        // Prevent stale true state: the available version must be strictly newer.
        if ($hasUpdate && $version && $currentVersion) {
            $hasUpdate = version_compare(
                ltrim(trim((string) $version), 'vV'),
                ltrim(trim((string) $currentVersion), 'vV'),
                '>'
            );
        }

        return [
            'has_update' => $hasUpdate,
            'version' => $version,
            'latest_version' => $update['latest_version'] ?? $version,
            'current_version' => $currentVersion,
            'update_id' => $update['update_id'] ?? null,
            'released_at' => $update['released_at'] ?? null,
            'summary' => $update['summary'] ?? null,
            'changelog' => $update['changelog'] ?? null,
        ];
    }
}
