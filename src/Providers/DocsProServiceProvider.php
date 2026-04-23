<?php

namespace Botble\DocsPro\Providers;

use Botble\Base\Facades\DashboardMenu;
use Botble\Base\Supports\ServiceProvider;
use Botble\Base\Traits\LoadAndPublishDataTrait;
use Botble\DocsPro\Models\DocProduct;
use Botble\DocsPro\Package\PackageServiceProvider;
use Botble\DocsPro\Services\DocsPortalService;
use Botble\DocsPro\Services\DocumentationManager;
use Botble\Language\Facades\Language;

class DocsProServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app->singleton(DocumentationManager::class);
        $this->app->singleton(DocsPortalService::class);
    }

    public function boot(): void
    {
        $this->app->register(PackageServiceProvider::class);

        $this
            ->setNamespace('plugins/docs-pro')
            ->loadHelpers()
            ->loadAndPublishConfigurations(['permissions'])
            ->loadAndPublishTranslations()
            ->loadRoutes()
            ->loadAndPublishViews()
            ->publishAssets()
            ->loadMigrations();

        if (defined('LANGUAGE_MODULE_SCREEN_NAME') && defined('LANGUAGE_ADVANCED_MODULE_SCREEN_NAME')) {
            Language::registerModule(DocProduct::class);
        }

        DashboardMenu::default()->beforeRetrieving(function (): void {
            DashboardMenu::make()
                ->registerItem([
                    'id' => 'cms-plugins-docs-pro',
                    'priority' => 180,
                    'name' => 'plugins/docs-pro::docs-pro.menu_name',
                    'icon' => 'ti ti-book-2',
                    'url' => fn () => route('docs-pro.products.index'),
                    'permissions' => [
                        'docs-pro.products.index',
                        'docs-pro.docs.index',
                        'docs-pro.import',
                        'docs-pro.export',
                    ],
                ])
                ->registerItem([
                    'id' => 'cms-plugins-docs-pro-products',
                    'priority' => 1,
                    'parent_id' => 'cms-plugins-docs-pro',
                    'name' => 'plugins/docs-pro::docs-pro.products',
                    'icon' => 'ti ti-package',
                    'url' => fn () => route('docs-pro.products.index'),
                    'permissions' => ['docs-pro.products.index'],
                ]);
        });
    }
}
