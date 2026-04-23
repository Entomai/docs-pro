<?php

namespace Botble\DocsPro\Package;

use Botble\Base\Facades\AdminHelper;
use Botble\Base\Facades\DashboardMenu;
use Botble\Base\Facades\PanelSectionManager;
use Botble\Base\PanelSections\PanelSectionItem;
use Botble\Base\Supports\DashboardMenuItem;
use Botble\Base\Supports\ServiceProvider;
use Botble\PluginManagement\Events\RenderingPluginListingPage;
use Botble\PluginManagement\Services\PluginService;
use Botble\Setting\PanelSections\SettingOthersPanelSection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Botble\DocsPro\Package\Http\Controllers\EntomaiPluginsController;
use Botble\DocsPro\Package\PrivateUpdater\PluginUpdateClient;
use Botble\DocsPro\Package\PrivateUpdater\PluginUpdateInstaller;
use Botble\DocsPro\Package\PrivateUpdater\PluginUpdateRegistry;
use Throwable;

/**
 * Copy this entire Package/ folder into your plugin's src/Package/.
 * Replace every occurrence of "Botble\DocsPro" with your plugin namespace,
 * e.g. Botble\TranslationPro -> becomes Botble\TranslationPro\Package\...
 * Then in your main ServiceProvider boot():
 *
 *   $this->app->register(PackageServiceProvider::class);
 *
 * And pass your plugin config slug to loadForPlugin():
 *
 *   PackageServiceProvider::loadForPlugin('translation-pro');
 */
class PackageServiceProvider extends ServiceProvider
{
    protected static string $pluginSlug = '';

    protected static bool $fastMenuRegistered = false;

    public static function loadForPlugin(string $pluginSlug): void
    {
        static::$pluginSlug = $pluginSlug;
    }

    public function boot(): void
    {
        $this->app['events']->listen(RenderingPluginListingPage::class, function (): void {
            add_filter('core_layout_after_content', [$this, 'renderUpdaterScript'], 120);
        });

        if (! Route::has('entomai.plugins.index')) {
            AdminHelper::registerRoutes(function (): void {
                Route::group([
                    'prefix' => 'settings/entomai-plugins',
                    'as' => 'entomai.plugins.',
                    'permission' => 'plugins.index',
                ], function (): void {
                    Route::get('/', [EntomaiPluginsController::class, 'index'])->name('index');
                    Route::get('/catalog', [EntomaiPluginsController::class, 'catalog'])->name('catalog');
                    Route::get('/catalog/{id}', [EntomaiPluginsController::class, 'catalogProduct'])->name('catalog-product')->where('id', '[0-9]+');
                    });
            });
        }

        $this->app->booted(function (): void {
            if (! Route::has('entomai.plugins.index')) {
                return;
            }

            PanelSectionManager::default()->beforeRendering(function (): void {
                PanelSectionManager::registerItem(
                    SettingOthersPanelSection::class,
                    fn () => PanelSectionItem::make('entomai-plugins')
                        ->setTitle('Entomai Plugins')
                        ->withIcon('ti ti-lock')
                        ->withDescription('License management & updates for your private Entomai plugins')
                        ->withPriority(500)
                        ->withRoute('entomai.plugins.index')
                        ->withPermission('plugins.index')
                );
            });

            DashboardMenu::default()->beforeRetrieving(function (): void {
                DashboardMenu::make()
                    ->registerItem(
                        DashboardMenuItem::make()
                            ->id('cms-entomai-plugins')
                            ->priority(9999)
                            ->parentId('cms-core-settings')
                            ->name('Entomai Plugins')
                            ->icon('ti ti-lock')
                            ->route('entomai.plugins.index')
                            ->permissions('plugins.index')
                    );
            });
        });

        if (defined('ADMIN_TOOLS_FILTER_UPDATE_ITEMS')) {
            add_filter(ADMIN_TOOLS_FILTER_UPDATE_ITEMS, [$this, 'addAdminToolsUpdateItems'], 20);
        }

        if (defined('ADMIN_TOOLS_FILTER_INSTALL_UPDATE_ITEM')) {
            add_filter(ADMIN_TOOLS_FILTER_INSTALL_UPDATE_ITEM, [$this, 'handleAdminToolsInstallUpdate'], 20, 2);
        }

        if (defined('ADMIN_TOOLS_FILTER_FAST_MENU_ITEMS') && ! static::$fastMenuRegistered) {
            static::$fastMenuRegistered = true;
            add_filter(ADMIN_TOOLS_FILTER_FAST_MENU_ITEMS, [$this, 'addFastMenuItem'], 5);
        }
    }

    public function renderUpdaterScript(?string $html): string
    {
        if (! Route::is('plugins.index') || ! Route::has('entomai.private-updater.check')) {
            return $html ?: '';
        }

        $view = view()->file(__DIR__.'/resources/views/private-updater/script.blade.php', [
            'checkUrl' => route('entomai.private-updater.check'),
            'updateUrlTemplate' => route('entomai.private-updater.update', ['plugin' => '__plugin__']),
        ])->render();

        return ($html ?: '').$view;
    }

    public function addAdminToolsUpdateItems(array $items): array
    {
        try {
            $existingKeys = array_column($items, 'key');

            $registry = new PluginUpdateRegistry();
            $client = new PluginUpdateClient();
            $updates = $client->check($registry->plugins());

            foreach (is_array($updates) ? $updates : [] as $plugin => $update) {
                if (! ($update['has_update'] ?? false)) {
                    continue;
                }

                $key = sprintf('plugin:entomai-private:%s', $plugin);

                if (in_array($key, $existingKeys, true)) {
                    continue;
                }

                $existingKeys[] = $key;

                $items[] = [
                    'key' => $key,
                    'type' => 'plugin',
                    'source' => 'entomai_private',
                    'source_label' => defined('ADMIN_TOOLS_FILTER_UPDATE_ITEMS')
                        ? trans('plugins/admin-tools::admin-tools.update_source_entomai_private')
                        : 'Entomai Private',
                    'slug' => $plugin,
                    'product_id' => (string) ($update['product_id'] ?? ''),
                    'update_id' => (string) ($update['update_id'] ?? ''),
                    'name' => (string) ($update['name'] ?? Str::headline($plugin)),
                    'current_version' => (string) ($update['current_version'] ?? ''),
                    'latest_version' => (string) ($update['version'] ?? $update['latest_version'] ?? ''),
                    'summary' => $update['summary'] ?? $update['message'] ?? null,
                    'released_at' => $update['released_at'] ?? null,
                    'icon' => 'ti ti-lock-open',
                    'url' => Route::has('plugins.index') ? route('plugins.index') : null,
                    'payload' => $update,
                ];
            }
        } catch (Throwable) {
            // Silently fail â€” never break the admin header for an update check
        }

        return $items;
    }

    public function addFastMenuItem(array $items): array
    {
        if (! Route::has('entomai.plugins.index')) {
            return $items;
        }

        // Deduplicate: multiple Package copies each register this filter
        foreach ($items as $existing) {
            if (($existing['id'] ?? '') === 'admin-tools-fast-menu-entomai-plugins') {
                return $items;
            }
        }

        // Insert after the Plugins group if present, otherwise append
        $pluginsIndex = null;

        foreach ($items as $i => $item) {
            if (($item['id'] ?? '') === 'admin-tools-fast-menu-plugins') {
                $pluginsIndex = $i;
                break;
            }
        }

        $entomaiItem = [
            'id' => 'admin-tools-fast-menu-entomai-plugins',
            'label' => 'Entomai Plugins',
            'url' => route('entomai.plugins.index'),
            'icon' => 'ti ti-lock',
            'permission' => 'plugins.index',
        ];

        if ($pluginsIndex !== null) {
            array_splice($items, $pluginsIndex + 1, 0, [$entomaiItem]);
        } else {
            $items[] = $entomaiItem;
        }

        return $items;
    }

    public function handleAdminToolsInstallUpdate(mixed $result, array $item): mixed
    {
        if ($result !== null || ($item['source'] ?? '') !== 'entomai_private') {
            return $result;
        }

        try {
            $user = auth()->guard()->user();

            if (! $user || ! $user->hasPermission('plugins.index')) {
                return ['error' => true, 'message' => trans('plugins/admin-tools::admin-tools.update_permission_denied')];
            }

            $plugin = (string) ($item['slug'] ?? '');
            $registry = new PluginUpdateRegistry();
            $pluginData = $registry->find($plugin);

            if (! $pluginData) {
                return [
                    'error' => true,
                    'message' => trans('plugins/admin-tools::admin-tools.update_item_unavailable', ['item' => $plugin]),
                ];
            }

            $response = (new PluginUpdateInstaller())->install($pluginData, [
                'version' => $item['latest_version'] ?? null,
                'latest_version' => $item['latest_version'] ?? null,
                'update_id' => $item['update_id'] ?? null,
            ], app(PluginService::class));

            return $response->getData(true);
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}

