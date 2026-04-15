<!DOCTYPE html>
<html class="dark" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        @if ($activeDocument && $activeProduct)
            {{ $activeDocument->name }} | {{ $activeProduct->name }}
        @elseif ($activeProduct)
            {{ $activeProduct->name }} | {{ trans('plugins/docs-pro::docs-pro.portal_brand') }}
        @else
            {{ trans('plugins/docs-pro::docs-pro.portal_brand') }}
        @endif
    </title>
    <link rel="stylesheet" href="{{ asset('vendor/core/plugins/docs-pro/css/fumadocs-core.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/core/plugins/docs-pro/css/docs-pro.css') }}">
</head>
<body class="docs-pro-portal-body">
    @if ($languageSwitcher)
        @php
            $currentLanguage = collect($languageSwitcher)->firstWhere('is_current', true);
        @endphp

        <div class="docs-pro-topbar">
            <div class="docs-pro-topbar-inner">
                <span class="docs-pro-topbar-label">{{ trans('plugins/docs-pro::docs-pro.portal_language_switcher') }}</span>

                <details class="docs-pro-language-selector">
                    <summary>
                        <span class="docs-pro-language-summary">
                            @if ($currentLanguage)
                                {!! language_flag($currentLanguage['flag'], $currentLanguage['name'], 14) !!}
                                <strong>{{ $currentLanguage['name'] }}</strong>
                            @endif
                        </span>

                        <svg class="docs-pro-chevron" viewBox="0 0 20 20" aria-hidden="true">
                            <path d="m6 8l4 4l4-4" />
                        </svg>
                    </summary>

                    <div class="docs-pro-language-menu">
                        @foreach ($languageSwitcher as $language)
                            <a
                                href="{{ $language['url'] }}"
                                class="{{ $language['is_current'] ? 'is-active' : '' }}"
                            >
                                <span>
                                    {!! language_flag($language['flag'], $language['name'], 14) !!}
                                    <strong>{{ $language['name'] }}</strong>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </details>
            </div>
        </div>
    @endif

    <div
        class="docs-pro-app {{ $activeProduct ? '' : 'is-empty' }} {{ $languageSwitcher ? 'has-topbar' : '' }}"
        data-docs-portal
    >
        <button
            type="button"
            class="docs-pro-backdrop"
            data-docs-dismiss
            aria-label="{{ trans('plugins/docs-pro::docs-pro.portal_close_panels') }}"
        ></button>

        <header class="docs-pro-mobile-bar">
            <a class="docs-pro-brand" href="{{ $indexUrl }}">
                <span class="docs-pro-brand-mark" aria-hidden="true"></span>
                <span class="docs-pro-brand-copy">
                    <strong>{{ trans('plugins/docs-pro::docs-pro.portal_brand') }}</strong>
                    <small>/docs</small>
                </span>
            </a>

            @if ($activeProduct)
                <div class="docs-pro-mobile-actions">
                    <button type="button" class="docs-pro-mobile-button" data-docs-sidebar-toggle>
                        <svg viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M3 5.75h14M3 10h14M3 14.25h10" />
                        </svg>
                        <span>{{ trans('plugins/docs-pro::docs-pro.portal_open_navigation') }}</span>
                    </button>

                    @if ($activeDocument)
                        <button type="button" class="docs-pro-mobile-button" data-docs-toc-toggle>
                            <svg viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M5 5.75h10M5 10h10M5 14.25h6" />
                            </svg>
                            <span>{{ trans('plugins/docs-pro::docs-pro.portal_on_this_page') }}</span>
                        </button>
                    @endif
                </div>
            @endif
        </header>

        @if (! $activeProduct)
            <main class="docs-pro-main docs-pro-main-full">
                <section class="docs-pro-empty-state">
                    <a class="docs-pro-brand docs-pro-brand-inline" href="{{ $indexUrl }}">
                        <span class="docs-pro-brand-mark" aria-hidden="true"></span>
                        <span class="docs-pro-brand-copy">
                            <strong>{{ trans('plugins/docs-pro::docs-pro.portal_brand') }}</strong>
                            <small>/docs</small>
                        </span>
                    </a>

                    <h1>{{ trans('plugins/docs-pro::docs-pro.portal_empty_title') }}</h1>
                    <p>{{ trans('plugins/docs-pro::docs-pro.portal_empty_description') }}</p>
                </section>
            </main>
        @else
            <aside class="docs-pro-sidebar" id="nd-sidebar">
                <div class="docs-pro-sidebar-panel">
                    <a class="docs-pro-brand" href="{{ $indexUrl }}">
                        <span class="docs-pro-brand-mark" aria-hidden="true"></span>
                        <span class="docs-pro-brand-copy">
                            <strong>{{ trans('plugins/docs-pro::docs-pro.portal_brand') }}</strong>
                            <small>/docs</small>
                        </span>
                    </a>

                    <label class="docs-pro-search">
                        <span class="docs-pro-search-icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20">
                                <path d="M8.5 3.75a4.75 4.75 0 1 0 0 9.5a4.75 4.75 0 0 0 0-9.5Zm0 0L16 16" />
                            </svg>
                        </span>
                        <input
                            type="search"
                            data-docs-search
                            placeholder="{{ trans('plugins/docs-pro::docs-pro.portal_search_placeholder') }}"
                            aria-label="{{ trans('plugins/docs-pro::docs-pro.portal_search_placeholder') }}"
                        >
                        <kbd>Ctrl K</kbd>
                    </label>

                    <details class="docs-pro-product-selector">
                        <summary>
                            <span class="docs-pro-product-summary-copy">
                                <strong>{{ $activeProduct->label }}</strong>
                            </span>

                            <svg class="docs-pro-chevron" viewBox="0 0 20 20" aria-hidden="true">
                                <path d="m6 8l4 4l4-4" />
                            </svg>
                        </summary>

                        <div class="docs-pro-product-menu">
                            @foreach ($products as $product)
                                <a
                                    href="{{ $productUrls[$product->getKey()] ?? $indexUrl }}"
                                    class="{{ $activeProduct->getKey() === $product->getKey() ? 'is-active' : '' }}"
                                >
                                    <span>
                                        <strong>{{ $product->label }}</strong>

                                        @if ($product->description)
                                            <small>{{ \Illuminate\Support\Str::limit($product->description, 72) }}</small>
                                        @endif
                                    </span>

                                    @if ($activeProduct->getKey() === $product->getKey())
                                        <svg viewBox="0 0 20 20" aria-hidden="true">
                                            <path d="m4.75 10.5l3.1 3.1l7.4-7.2" />
                                        </svg>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </details>

                    <nav
                        class="docs-pro-sidebar-nav"
                        aria-label="{{ trans('plugins/docs-pro::docs-pro.portal_navigation') }}"
                        data-docs-nav-root
                    >
                        @include('plugins/docs-pro::portal.nav-item', [
                            'nodes' => $navigationTree,
                            'activeTrail' => $activeTrail,
                        ])
                    </nav>

                    <p class="docs-pro-search-empty" data-docs-search-empty hidden>
                        {{ trans('plugins/docs-pro::docs-pro.portal_search_empty') }}
                    </p>
                </div>
            </aside>

            <main class="docs-pro-main">
                @if (! $activeDocument)
                    <section class="docs-pro-empty-state">
                        <h1>{{ trans('plugins/docs-pro::docs-pro.portal_no_document_title') }}</h1>
                        <p>{{ trans('plugins/docs-pro::docs-pro.portal_no_document_description') }}</p>
                    </section>
                @else
                    <article class="docs-pro-article">
                        <div class="docs-pro-article-meta">
                            <span class="docs-pro-eyebrow">{{ $activeProduct->label }}</span>

                            @if ($activeDocument->slug_path)
                                <code>{{ $activeDocument->slug_path }}</code>
                            @endif
                        </div>

                        <header class="docs-pro-article-head">
                            <h1>{{ $activeDocument->name }}</h1>

                            @if ($activeDocument->excerpt)
                                <p class="docs-pro-lead">{{ $activeDocument->excerpt }}</p>
                            @endif

                            <div class="docs-pro-article-actions">
                                @if (filled($activeDocument->markdown_content))
                                    <button
                                        type="button"
                                        class="docs-pro-action-button"
                                        data-docs-copy="markdown"
                                        data-copied-label="{{ trans('plugins/docs-pro::docs-pro.portal_copied') }}"
                                    >
                                        <svg viewBox="0 0 20 20" aria-hidden="true">
                                            <path d="M6.75 6.25h6.5M6.75 10h6.5M6.75 13.75h4.25M5.5 3.75h7.25a1.75 1.75 0 0 1 1.75 1.75v9a1.75 1.75 0 0 1-1.75 1.75H5.5a1.75 1.75 0 0 1-1.75-1.75v-9A1.75 1.75 0 0 1 5.5 3.75Z" />
                                        </svg>
                                        <span data-docs-copy-label>{{ trans('plugins/docs-pro::docs-pro.portal_copy_markdown') }}</span>
                                    </button>
                                @endif

                                <button
                                    type="button"
                                    class="docs-pro-action-button"
                                    data-docs-copy="url"
                                    data-copied-label="{{ trans('plugins/docs-pro::docs-pro.portal_copied') }}"
                                >
                                    <svg viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M7.5 10a3.25 3.25 0 0 1 3.25-3.25h2.5A3.25 3.25 0 1 1 13.25 13h-1.5M12.5 10a3.25 3.25 0 0 1-3.25 3.25h-2.5A3.25 3.25 0 1 1 6.75 7h1.5" />
                                    </svg>
                                    <span data-docs-copy-label>{{ trans('plugins/docs-pro::docs-pro.portal_copy_link') }}</span>
                                </button>
                            </div>
                        </header>

                        @if ($renderedContent)
                            <div class="prose docs-pro-prose" data-docs-content>
                                {!! BaseHelper::clean($renderedContent) !!}
                            </div>
                        @else
                            <p class="docs-pro-muted">{{ trans('plugins/docs-pro::docs-pro.portal_no_document_description') }}</p>
                        @endif
                    </article>

                    @if ($previousDocument || $nextDocument)
                        <nav class="docs-pro-pagination" aria-label="Docs pagination">
                            @if ($previousDocument && $previousUrl)
                                <a href="{{ $previousUrl }}">
                                    <small>{{ trans('plugins/docs-pro::docs-pro.portal_previous') }}</small>
                                    <strong>{{ $previousDocument->name }}</strong>
                                </a>
                            @else
                                <span class="docs-pro-pagination-placeholder" aria-hidden="true"></span>
                            @endif

                            @if ($nextDocument && $nextUrl)
                                <a href="{{ $nextUrl }}" class="is-next">
                                    <small>{{ trans('plugins/docs-pro::docs-pro.portal_next') }}</small>
                                    <strong>{{ $nextDocument->name }}</strong>
                                </a>
                            @endif
                        </nav>
                    @endif
                @endif
            </main>

            <aside class="docs-pro-toc" id="nd-toc" {{ $activeDocument ? '' : 'hidden' }}>
                <div class="docs-pro-toc-card" data-docs-toc-panel>
                    <div class="docs-pro-toc-heading">
                        <svg viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M5 5.75h10M5 10h10M5 14.25h6" />
                        </svg>
                        <span>{{ trans('plugins/docs-pro::docs-pro.portal_on_this_page') }}</span>
                    </div>

                    <ol class="docs-pro-toc-list" data-docs-toc-list></ol>

                    <p class="docs-pro-toc-empty" data-docs-toc-empty>
                        {{ trans('plugins/docs-pro::docs-pro.portal_on_this_page_empty') }}
                    </p>
                </div>
            </aside>
        @endif
    </div>

    @if ($activeDocument)
        <script type="application/json" id="docs-pro-page-data">
            @json([
                'markdown' => $activeDocument->markdown_content,
                'url' => url()->current(),
            ])
        </script>
    @endif

    <script src="{{ asset('vendor/core/plugins/docs-pro/js/docs-portal.js') }}"></script>
</body>
</html>
