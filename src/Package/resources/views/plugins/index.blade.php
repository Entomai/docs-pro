@extends(BaseHelper::getAdminMasterLayoutTemplate())

@push('header')
<style>
    .entomai-plugin-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .entomai-plugin-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0,0,0,.08);
    }
    .entomai-catalog-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .entomai-catalog-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,.10);
    }
    .entomai-catalog-screenshot {
        width: 100%;
        height: 160px;
        object-fit: cover;
        border-bottom: 1px solid var(--bb-border-color);
        display: block;
    }
    .entomai-catalog-no-screenshot {
        height: 80px;
        background: linear-gradient(135deg, rgba(var(--bb-primary-rgb),.07) 0%, rgba(var(--bb-primary-rgb),.03) 100%);
        border-bottom: 1px solid var(--bb-border-color);
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>
@endpush

@section('content')
    {{-- Page header --}}
    <div class="page-header d-print-none mb-3">
        <div class="container-xl">
            <div class="row align-items-center">
                <div class="col">
                    <div class="d-flex align-items-center gap-3">
                        <span class="avatar avatar-md rounded bg-primary-lt">
                            <x-core::icon name="ti ti-lock" class="text-primary" style="font-size: 1.3rem;" />
                        </span>
                        <div>
                            <h2 class="page-title mb-0">Entomai Plugins</h2>
                            <p class="text-secondary small mb-0">License management &amp; updates for your private plugins</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-xl">
        {{-- Tabs --}}
        <ul class="nav nav-tabs mb-4" id="entomaiTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-installed" data-bs-toggle="tab" data-bs-target="#panel-installed" type="button" role="tab">
                    <x-core::icon name="ti ti-puzzle" class="me-1" />
                    Installed
                    <span class="badge bg-primary-lt text-primary ms-1">{{ count($cards) }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-more" data-bs-toggle="tab" data-bs-target="#panel-more" type="button" role="tab">
                    <x-core::icon name="ti ti-shopping-bag" class="me-1" />
                    More Plugins
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-docs" data-bs-toggle="tab" data-bs-target="#panel-docs" type="button" role="tab">
                    <x-core::icon name="ti ti-book" class="me-1" />
                    Documentation
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-support" data-bs-toggle="tab" data-bs-target="#panel-support" type="button" role="tab">
                    <x-core::icon name="ti ti-lifebuoy" class="me-1" />
                    Support
                </button>
            </li>
        </ul>

        <div class="tab-content" id="entomaiTabContent">

            {{-- ─── INSTALLED ─────────────────────────────────────────────── --}}
            <div class="tab-pane fade show active" id="panel-installed" role="tabpanel">
                @if (count($cards) === 0)
                    <x-core::empty-state
                        title="No Entomai plugins found"
                        subtitle="No plugins with an Entomai license system have been detected."
                        icon="ti ti-puzzle"
                    />
                @else
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4" id="entomai-plugins-list">
                        @foreach ($cards as $card)
                            @php $cardId = 'entomai-card-' . $card['path']; @endphp
                            <div class="col" id="{{ $cardId }}">
                                <x-core::card class="h-100 entomai-plugin-card">

                                    {{-- Hero: screenshot or gradient placeholder --}}
                                    <div class="position-relative">
                                        @if (!empty($card['screenshot_url']))
                                            <img
                                                src="{{ $card['screenshot_url'] }}"
                                                alt="{{ $card['name'] }}"
                                                class="card-img-top"
                                                style="height:130px;object-fit:cover;border-bottom:1px solid var(--bb-border-color);"
                                            >
                                        @else
                                            <div
                                                class="card-img-top d-flex align-items-center justify-content-center"
                                                style="height:130px; background: linear-gradient(135deg, rgba(var(--bb-primary-rgb),.07) 0%, rgba(var(--bb-primary-rgb),.03) 100%); border-bottom: 1px solid var(--bb-border-color);"
                                            >
                                                <div class="avatar avatar-xl rounded" style="background: rgba(var(--bb-primary-rgb),.1);">
                                                    <x-core::icon name="ti ti-puzzle" class="text-primary" style="font-size: 2rem;" />
                                                </div>
                                            </div>
                                        @endif

                                        {{-- License badge --}}
                                        <div class="position-absolute top-0 end-0 m-2">
                                            @if ($card['is_free'] ?? false)
                                                <span class="badge bg-blue-lt text-blue">
                                                    <x-core::icon name="ti ti-gift" class="me-1" />
                                                    Free
                                                </span>
                                            @else
                                                <span
                                                    @class(['badge', 'bg-green-lt text-green' => $card['activated'], 'bg-secondary-lt text-secondary' => ! $card['activated']])
                                                    data-entomai-license-badge="{{ $card['path'] }}"
                                                >
                                                    <x-core::icon :name="$card['activated'] ? 'ti ti-lock-open' : 'ti ti-lock'" class="me-1" />
                                                    {{ $card['activated'] ? 'Licensed' : 'Unlicensed' }}
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Update badge --}}
                                        <div
                                            class="position-absolute top-0 start-0 m-2"
                                            data-entomai-update-badge="{{ $card['path'] }}"
                                            @style(['display:none' => ! $card['has_update']])
                                        >
                                            <span class="badge bg-success-lt text-success">
                                                <x-core::icon name="ti ti-arrow-up-circle" class="me-1" />
                                                {{ $card['update_version'] }}
                                            </span>
                                        </div>
                                    </div>

                                    <x-core::card.body class="d-flex flex-column">
                                        <div class="mb-3">
                                            <h4 class="card-title mb-1">
                                                <span class="text-truncate d-block">{{ $card['name'] }}</span>
                                            </h4>
                                            @if ($card['description'])
                                                <p class="text-secondary small mb-0"
                                                   style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.5em;"
                                                >{{ $card['description'] }}</p>
                                            @endif
                                        </div>

                                        <div class="mt-auto pt-3 border-top">
                                            <div class="d-flex flex-wrap gap-2 text-secondary small mb-2">
                                                @if ($card['author'])
                                                    <div class="d-flex align-items-center gap-1">
                                                        <x-core::icon name="ti ti-user" class="text-muted" />
                                                        @if ($card['author_url'])
                                                            <a href="{{ $card['author_url'] }}" target="_blank" class="text-reset text-decoration-none">{{ $card['author'] }}</a>
                                                        @else
                                                            <span>{{ $card['author'] }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                                <div class="d-flex align-items-center gap-1 ms-auto">
                                                    <x-core::icon name="ti ti-tag" class="text-muted" />
                                                    <span data-entomai-version="{{ $card['path'] }}">v{{ $card['version'] }}</span>
                                                </div>
                                            </div>

                                            @if ($card['activated'])
                                                @if ($card['client_email'])
                                                    <div class="d-flex align-items-center gap-1 text-secondary small mb-1">
                                                        <x-core::icon name="ti ti-mail" class="text-muted flex-shrink-0" />
                                                        <span class="text-truncate">{{ $card['client_email'] }}</span>
                                                    </div>
                                                @endif
                                                @if ($card['masked_license_code'])
                                                    <div class="d-flex align-items-center gap-1 text-secondary small">
                                                        <x-core::icon name="ti ti-key" class="text-muted flex-shrink-0" />
                                                        <span class="font-monospace">{{ $card['masked_license_code'] }}</span>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </x-core::card.body>

                                    <x-core::card.footer>
                                        <div class="d-flex flex-column gap-2">
                                            @if (! ($card['is_free'] ?? false))
                                                {{-- Primary action row --}}
                                                <div class="btn-list justify-content-center">
                                                    @if ($card['activated'])
                                                        @if ($card['has_update'] && ($card['routes']['install'] ?? null))
                                                            <button type="button"
                                                                class="btn btn-success btn-sm entomai-action-btn"
                                                                data-action="update"
                                                                data-url="{{ $card['routes']['install'] }}"
                                                                data-version="{{ $card['update_version'] }}"
                                                                data-update-id="{{ $card['update_id'] }}"
                                                                data-card="#{{ $cardId }}"
                                                            >
                                                                <x-core::icon name="ti ti-arrow-up-circle" class="me-1" />
                                                                Update to {{ $card['update_version'] }}
                                                            </button>
                                                        @elseif ($card['routes']['check-update'] ?? null)
                                                            <button type="button"
                                                                class="btn btn-info btn-sm entomai-action-btn"
                                                                data-action="check-update"
                                                                data-url="{{ $card['routes']['check-update'] }}"
                                                                data-card="#{{ $cardId }}"
                                                            >
                                                                <x-core::icon name="ti ti-refresh" class="me-1" />
                                                                Check Update
                                                            </button>
                                                        @endif

                                                                                                                @if (! ($card['is_active_in_cms'] ?? false))
                                                            <button type="button"
                                                                class="btn btn-primary btn-sm entomai-action-btn"
                                                                data-action="activate-cms"
                                                                data-url="{{ route('plugins.change.status', ['name' => $card['path']]) }}"
                                                                data-card="#{{ $cardId }}"
                                                            >
                                                                <x-core::icon name="ti ti-check" class="me-1" />
                                                                Activate
                                                            </button>
                                                        @endif
                                                        @if ($card['routes']['deactivate'] ?? null)
                                                            <button type="button"
                                                                class="btn btn-warning btn-sm entomai-action-btn"
                                                                data-action="deactivate"
                                                                data-url="{{ $card['routes']['deactivate'] }}"
                                                                data-card="#{{ $cardId }}"
                                                            >
                                                                <x-core::icon name="ti ti-player-pause" class="me-1" />
                                                                Deactivate
                                                            </button>
                                                        @endif
                                                    @else
                                                                                                                @if (! ($card['is_active_in_cms'] ?? false))
                                                            <button type="button"
                                                                class="btn btn-primary btn-sm entomai-action-btn"
                                                                data-action="activate-cms"
                                                                data-url="{{ route('plugins.change.status', ['name' => $card['path']]) }}"
                                                                data-card="#{{ $cardId }}"
                                                            >
                                                                <x-core::icon name="ti ti-check" class="me-1" />
                                                                Activate
                                                            </button>
                                                        @endif
                                                        @if ($card['routes']['start'] ?? null)
                                                            <a href="{{ $card['routes']['start'] }}"
                                                                class="btn btn-primary btn-sm"
                                                            >
                                                                <x-core::icon name="ti ti-lock-open" class="me-1" />
                                                                Activate License
                                                            </a>
                                                        @endif
                                                    @endif
                                                </div>

                                                {{-- Secondary actions --}}
                                                @if ($card['activated'])
                                                    <div class="btn-list justify-content-center">
                                                        @if ($card['routes']['verify'] ?? null)
                                                            <button type="button"
                                                                class="btn btn-secondary btn-sm entomai-action-btn"
                                                                data-action="verify"
                                                                data-url="{{ $card['routes']['verify'] }}"
                                                                data-card="#{{ $cardId }}"
                                                            >
                                                                <x-core::icon name="ti ti-shield-check" class="me-1" />
                                                                Verify
                                                            </button>
                                                        @endif
                                                        @if ($card['routes']['force-deactivate'] ?? null)
                                                            <button type="button"
                                                                class="btn btn-danger btn-sm entomai-action-btn"
                                                                data-action="force-deactivate"
                                                                data-url="{{ $card['routes']['force-deactivate'] }}"
                                                                data-card="#{{ $cardId }}"
                                                                data-confirm="Remove local license data? You can re-activate later."
                                                            >
                                                                <x-core::icon name="ti ti-trash" class="me-1" />
                                                                Force Remove
                                                            </button>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endif

                                            {{-- Docs + Support --}}
                                            @if ($card['documentation_url'] || $card['support_url'])
                                                <div class="btn-list justify-content-center @if(! ($card['is_free'] ?? false)) border-top pt-2 mt-1 @endif">
                                                    @if ($card['documentation_url'])
                                                        <a href="{{ $card['documentation_url'] }}" target="_blank" class="btn btn-ghost-secondary btn-sm">
                                                            <x-core::icon name="ti ti-book" class="me-1" />Docs
                                                        </a>
                                                    @endif
                                                    @if ($card['support_url'])
                                                        <a href="{{ $card['support_url'] }}" target="_blank" class="btn btn-ghost-secondary btn-sm">
                                                            <x-core::icon name="ti ti-lifebuoy" class="me-1" />Support
                                                        </a>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Per-card process log (paid plugins only) --}}
                                        @if (! ($card['is_free'] ?? false))
                                            <div class="mt-2" data-entomai-log-wrap="{{ $card['path'] }}" style="display:none;">
                                                <div
                                                    class="border rounded bg-white p-2 small text-muted font-monospace"
                                                    style="max-height:110px;overflow:auto;"
                                                    data-entomai-log="{{ $card['path'] }}"
                                                ></div>
                                                <div class="mt-1 small fw-semibold" data-entomai-msg="{{ $card['path'] }}"></div>
                                            </div>
                                        @endif
                                    </x-core::card.footer>
                                </x-core::card>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ─── MORE PLUGINS ──────────────────────────────────────────── --}}
            <div class="tab-pane fade" id="panel-more" role="tabpanel" data-catalog-url="{{ $catalogProxyUrl ?? '' }}">
                @if (blank($catalogProxyUrl ?? null))
                    <x-core::empty-state
                        title="No catalog server configured"
                        subtitle="Install and activate a licensed Entomai plugin to enable the catalog."
                        icon="ti ti-shopping-bag"
                    />
                @else
                    {{-- Loading state --}}
                    <div id="catalog-loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-secondary mt-2 small">Loading catalog…</p>
                    </div>

                    {{-- Error state --}}
                    <div id="catalog-error" class="d-none">
                        <x-core::empty-state
                            title="Could not load catalog"
                            subtitle="Check your connection to the Entomai license server."
                            icon="ti ti-wifi-off"
                        />
                    </div>

                    {{-- Catalog grid (populated by JS) --}}
                    <div id="catalog-grid" class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4 d-none"></div>
                @endif
            </div>

            {{-- ─── DOCUMENTATION ─────────────────────────────────────────── --}}
            <div class="tab-pane fade" id="panel-docs" role="tabpanel">
                @php $docsCards = array_filter($cards, fn($c) => filled($c['documentation_url'])); @endphp
                @if (empty($docsCards))
                    <x-core::empty-state
                        title="No documentation links"
                        subtitle="Add a documentation_url to your plugin's entomai_updater block in plugin.json."
                        icon="ti ti-book"
                    />
                @else
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
                        @foreach ($docsCards as $card)
                            <div class="col">
                                <x-core::card>
                                    <x-core::card.body class="d-flex align-items-center gap-3">
                                        <span class="avatar rounded bg-primary-lt">
                                            <x-core::icon name="ti ti-book" class="text-primary" />
                                        </span>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="fw-semibold text-truncate">{{ $card['name'] }}</div>
                                            <div class="text-secondary small text-truncate">{{ $card['documentation_url'] }}</div>
                                        </div>
                                        <a href="{{ $card['documentation_url'] }}" target="_blank" class="btn btn-sm btn-primary flex-shrink-0">
                                            <x-core::icon name="ti ti-external-link" class="me-1" />Open
                                        </a>
                                    </x-core::card.body>
                                </x-core::card>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ─── SUPPORT ────────────────────────────────────────────────── --}}
            <div class="tab-pane fade" id="panel-support" role="tabpanel">
                @php $supportCards = array_filter($cards, fn($c) => filled($c['support_url'])); @endphp
                @if (empty($supportCards))
                    <x-core::empty-state
                        title="No support links"
                        subtitle="Add a support_url to your plugin's entomai_updater block in plugin.json."
                        icon="ti ti-lifebuoy"
                    />
                @else
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
                        @foreach ($supportCards as $card)
                            <div class="col">
                                <x-core::card>
                                    <x-core::card.body class="d-flex align-items-center gap-3">
                                        <span class="avatar rounded bg-orange-lt">
                                            <x-core::icon name="ti ti-lifebuoy" class="text-orange" />
                                        </span>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="fw-semibold text-truncate">{{ $card['name'] }}</div>
                                            <div class="text-secondary small text-truncate">{{ $card['support_url'] }}</div>
                                        </div>
                                        <a href="{{ $card['support_url'] }}" target="_blank" class="btn btn-sm btn-warning flex-shrink-0">
                                            <x-core::icon name="ti ti-external-link" class="me-1" />Open
                                        </a>
                                    </x-core::card.body>
                                </x-core::card>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>{{-- /tab-content --}}
    </div>

    {{-- Plugin details modal --}}
    <div class="modal fade" id="entomai-plugin-details-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="entomai-modal-title">Plugin Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="entomai-modal-body">
                    <div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>
                </div>
                <div class="modal-footer" id="entomai-modal-footer"></div>
            </div>
        </div>
    </div>
@stop

@push('footer')
<script>
(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const installBaseUrl = @json(Route::has('entomai.private-updater.update') ? route('entomai.private-updater.update', ['plugin' => '__PLUGIN__']) : null);
    const marketplaceBaseUrl = @json(Route::has('plugins.new') ? route('plugins.new') : null);
    const catalogProductBaseUrl = @json($catalogProductBaseUrl ?? null);

    const appendLog = function (path, text) {
        const wrap = document.querySelector('[data-entomai-log-wrap="' + path + '"]');
        const log  = document.querySelector('[data-entomai-log="' + path + '"]');
        if (!wrap || !log || !text) return;
        wrap.style.display = '';
        const entry = document.createElement('div');
        entry.textContent = '[' + new Date().toLocaleTimeString() + '] ' + text;
        log.appendChild(entry);
        log.scrollTop = log.scrollHeight;
    };

    const setMsg = function (path, text, success) {
        const el = document.querySelector('[data-entomai-msg="' + path + '"]');
        if (!el) return;
        el.textContent = text || '';
        el.className = 'mt-1 small fw-semibold ' + (success ? 'text-success' : 'text-danger');
    };

    const setBusy = function (btn, busy) {
        if (!btn) return;
        if (busy) {
            btn.dataset.origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.classList.add('disabled');
        } else {
            if (btn.dataset.origHtml) btn.innerHTML = btn.dataset.origHtml;
            btn.disabled = false;
            btn.classList.remove('disabled');
        }
    };

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.entomai-action-btn');
        if (!btn) return;

        const action   = btn.dataset.action;
        const url      = btn.dataset.url;
        const cardSel  = btn.dataset.card;
        const cardEl   = cardSel ? document.querySelector(cardSel) : null;
        const path     = cardEl ? cardEl.id.replace('entomai-card-', '') : '';

        if (!action || !url) return;
        e.preventDefault();

        if (btn.dataset.confirm && !window.confirm(btn.dataset.confirm)) return;

        appendLog(path, btn.textContent.trim() + '…');
        setBusy(btn, true);
        setMsg(path, '', true);

        try {
            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            };
            let body = null;

            if (action === 'update') {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify({
                    version:   btn.dataset.version   || '',
                    update_id: btn.dataset.updateId  || '',
                });
            }

            let method = 'POST';
            if (action === 'activate-cms') {
                method = 'PUT';
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify({ name: path });
            }

            const response = await fetch(url, { method: method, credentials: 'same-origin', headers, body });
            const data = await response.json().catch(() => ({ error: true, message: 'Invalid JSON response.' }));

            (data.log || []).forEach(line => appendLog(path, line));
            appendLog(path, data.message || (data.error || data.success === false ? 'Failed.' : 'Done.'));
            setMsg(path, data.message || '', !(data.error || data.success === false));

            if (data.redirect_url) { window.location.href = data.redirect_url; return; }

            if (action === 'check-update' && data.update) {
                const update     = data.update;
                const updateBadge = document.querySelector('[data-entomai-update-badge="' + path + '"]');

                if (update.has_update && update.version) {
                    if (updateBadge) {
                        updateBadge.style.display = '';
                        updateBadge.innerHTML = '<span class="badge bg-success-lt text-success"><i class="ti ti-arrow-up-circle me-1"></i>' + update.version + '</span>';
                    }
                    if (installBaseUrl) {
                        const installUrl = installBaseUrl.replace('__PLUGIN__', encodeURIComponent(path));
                        btn.dataset.action    = 'update';
                        btn.dataset.url       = installUrl;
                        btn.dataset.version   = update.version;
                        btn.dataset.updateId  = update.update_id || '';
                        btn.className         = btn.className.replace('btn-info', 'btn-success');
                        btn.innerHTML         = '<i class="ti ti-arrow-up-circle me-1"></i>Update to ' + update.version;
                        btn.dataset.origHtml  = btn.innerHTML;
                    }
                } else if (updateBadge) {
                    updateBadge.style.display = 'none';
                }
            }

            if (data.reload) setTimeout(() => window.location.reload(), 900);
            if (!data.error && action === 'update') setTimeout(() => window.location.reload(), 2000);

        } catch (err) {
            appendLog(path, err.message || 'Request failed.');
            setMsg(path, err.message || 'Request failed.', false);
        } finally {
            setBusy(btn, false);
        }
    });

    // ── Catalog (More Plugins tab) ────────────────────────────────────────
    const catalogPanel = document.getElementById('panel-more');
    const catalogUrl   = catalogPanel ? catalogPanel.dataset.catalogUrl : null;

    if (catalogUrl) {
        const loadCatalog = async function () {
            try {
                const res  = await fetch(catalogUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                const data = await res.json().catch(() => null);
                const items = (data && Array.isArray(data.data)) ? data.data : [];

                document.getElementById('catalog-loading')?.classList.add('d-none');

                if (items.length === 0) {
                    document.getElementById('catalog-error')?.classList.remove('d-none');
                    return;
                }

                const grid = document.getElementById('catalog-grid');
                if (!grid) return;

                grid.classList.remove('d-none');
                grid.innerHTML = items.map(function (item) {
                    // Screenshot header: use first screenshot, or icon fallback
                    const screenshots = Array.isArray(item.screenshots) ? item.screenshots : [];
                    let headerHtml;
                    if (screenshots.length > 0) {
                        headerHtml = '<img src="' + escHtml(screenshots[0]) + '" alt="" class="entomai-catalog-screenshot">';
                    } else if (item.icon_url) {
                        headerHtml = '<div class="entomai-catalog-no-screenshot"><img src="' + escHtml(item.icon_url) + '" alt="" style="width:56px;height:56px;object-fit:contain;border-radius:10px;"></div>';
                    } else {
                        headerHtml = '<div class="entomai-catalog-no-screenshot"><span class="avatar avatar-lg rounded bg-primary-lt"><i class="ti ti-puzzle text-primary" style="font-size:1.8rem;"></i></span></div>';
                    }

                    const badgeHtml = item.is_free
                        ? '<span class="badge bg-green-lt text-green"><i class="ti ti-gift me-1"></i>Free</span>'
                        : '<span class="badge bg-primary-lt text-primary">' + (item.currency || 'USD') + ' ' + parseFloat(item.price || 0).toFixed(2) + '</span>';

                    let actionHtml = '';
                    if (item.is_marketplace_free && item.marketplace_search_query && marketplaceBaseUrl) {
                        const mktUrl = marketplaceBaseUrl + '?q=' + encodeURIComponent(item.marketplace_search_query);
                        actionHtml = '<a href="' + escHtml(mktUrl) + '" class="btn btn-success btn-sm"><i class="ti ti-download me-1"></i>Download</a>';
                    } else if (item.is_free && item.download_url) {
                        actionHtml = '<a href="' + escHtml(item.download_url) + '" target="_blank" class="btn btn-success btn-sm"><i class="ti ti-download me-1"></i>Download</a>';
                    } else if (!item.is_free && item.buy_url) {
                        actionHtml = '<a href="' + escHtml(item.buy_url) + '" target="_blank" class="btn btn-primary btn-sm"><i class="ti ti-shopping-cart me-1"></i>Buy</a>';
                    }

                    let detailsBtn = '';
                    if (catalogProductBaseUrl) {
                        const detailUrl = catalogProductBaseUrl.replace('__ID__', item.id);
                        detailsBtn = '<button type="button" class="btn btn-ghost-secondary btn-sm entomai-details-btn" data-details-url="' + escHtml(detailUrl) + '" data-item-name="' + escHtml(item.name) + '"><i class="ti ti-info-circle me-1"></i>Details</button>';
                    }

                    const tagsHtml = Array.isArray(item.tags) && item.tags.length
                        ? '<div class="d-flex flex-wrap gap-1 mt-2">' + item.tags.map(t => '<span class="badge bg-secondary-lt text-secondary">' + escHtml(t) + '</span>').join('') + '</div>'
                        : '';

                    return '<div class="col">'
                        + '<div class="card h-100 entomai-catalog-card overflow-hidden">'
                        + headerHtml
                        + '<div class="card-body d-flex flex-column gap-2">'
                        + '<div class="d-flex align-items-center gap-2">'
                        + (item.icon_url && screenshots.length > 0 ? '<img src="' + escHtml(item.icon_url) + '" alt="" style="width:32px;height:32px;object-fit:contain;border-radius:6px;flex-shrink:0;">' : '')
                        + '<div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">' + escHtml(item.name) + '</div>'
                        + '<div class="text-secondary small text-truncate">' + escHtml(item.tagline || '') + '</div></div>'
                        + badgeHtml + '</div>'
                        + (item.description ? '<p class="text-secondary small mb-0" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">' + escHtml(item.description) + '</p>' : '')
                        + tagsHtml
                        + '</div>'
                        + '<div class="card-footer d-flex gap-2 justify-content-end">'
                        + detailsBtn
                        + actionHtml
                        + '</div>'
                        + '</div></div>';
                }).join('');
            } catch (e) {
                document.getElementById('catalog-loading')?.classList.add('d-none');
                document.getElementById('catalog-error')?.classList.remove('d-none');
            }
        };

        // Load when the tab is first shown
        const moreTab = document.getElementById('tab-more');
        if (moreTab) {
            moreTab.addEventListener('shown.bs.tab', function onFirstShow () {
                moreTab.removeEventListener('shown.bs.tab', onFirstShow);
                loadCatalog();
            });
        }
    }

    // ── Plugin details modal ──────────────────────────────────────────────
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.entomai-details-btn');
        if (!btn) return;

        const detailsUrl = btn.dataset.detailsUrl;
        const itemName   = btn.dataset.itemName || 'Plugin Details';
        if (!detailsUrl) return;

        const modalEl   = document.getElementById('entomai-plugin-details-modal');
        const titleEl   = document.getElementById('entomai-modal-title');
        const bodyEl    = document.getElementById('entomai-modal-body');
        const footerEl  = document.getElementById('entomai-modal-footer');
        if (!modalEl) return;

        titleEl.textContent = itemName;
        bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
        footerEl.innerHTML = '';

        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) modal = new bootstrap.Modal(modalEl);
        modal.show();

        try {
            const res  = await fetch(detailsUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
            const data = await res.json().catch(() => null);
            const item = data && data.data ? data.data : null;

            if (!item) {
                bodyEl.innerHTML = '<div class="alert alert-danger">Could not load plugin details.</div>';
                return;
            }

            const screenshots = Array.isArray(item.screenshots) ? item.screenshots : [];

            let screenshotsHtml = '';
            if (screenshots.length === 1) {
                screenshotsHtml = '<img src="' + escHtml(screenshots[0]) + '" alt="" class="img-fluid rounded mb-3" style="width:100%;object-fit:cover;max-height:300px;">';
            } else if (screenshots.length > 1) {
                const id = 'entomai-carousel-' + item.id;
                const slides = screenshots.map((s, i) =>
                    '<div class="carousel-item' + (i === 0 ? ' active' : '') + '">'
                    + '<img src="' + escHtml(s) + '" class="d-block w-100" style="max-height:300px;object-fit:cover;" alt="Screenshot ' + (i+1) + '">'
                    + '</div>'
                ).join('');
                const indicators = screenshots.map((_, i) =>
                    '<button type="button" data-bs-target="#' + id + '" data-bs-slide-to="' + i + '"' + (i === 0 ? ' class="active"' : '') + '></button>'
                ).join('');
                screenshotsHtml = '<div id="' + id + '" class="carousel slide mb-3" data-bs-ride="false">'
                    + '<div class="carousel-indicators">' + indicators + '</div>'
                    + '<div class="carousel-inner rounded">' + slides + '</div>'
                    + '<button class="carousel-control-prev" type="button" data-bs-target="#' + id + '" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>'
                    + '<button class="carousel-control-next" type="button" data-bs-target="#' + id + '" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>'
                    + '</div>';
            }

            const tagsHtml = Array.isArray(item.tags) && item.tags.length
                ? '<div class="d-flex flex-wrap gap-1 mb-3">' + item.tags.map(t => '<span class="badge bg-secondary-lt text-secondary">' + escHtml(t) + '</span>').join('') + '</div>'
                : '';

            const priceBadge = item.is_free
                ? '<span class="badge bg-green-lt text-green fs-6 mb-3"><i class="ti ti-gift me-1"></i>Free</span>'
                : '<span class="badge bg-primary-lt text-primary fs-6 mb-3">' + (item.currency || 'USD') + ' ' + parseFloat(item.price || 0).toFixed(2) + '</span>';

            const descHtml = item.description
                ? '<div class="mb-3">' + item.description + '</div>'
                : '';

            bodyEl.innerHTML = screenshotsHtml
                + '<div class="d-flex align-items-center gap-3 mb-3">'
                + (item.icon_url ? '<img src="' + escHtml(item.icon_url) + '" alt="" style="width:48px;height:48px;object-fit:contain;border-radius:10px;">' : '')
                + '<div><h4 class="mb-0">' + escHtml(item.name) + '</h4>'
                + (item.tagline ? '<div class="text-secondary">' + escHtml(item.tagline) + '</div>' : '')
                + '</div></div>'
                + priceBadge
                + tagsHtml
                + descHtml;

            let footerAction = '';
            if (item.is_marketplace_free && item.marketplace_search_query && marketplaceBaseUrl) {
                const mktUrl = marketplaceBaseUrl + '?q=' + encodeURIComponent(item.marketplace_search_query);
                footerAction = '<a href="' + escHtml(mktUrl) + '" class="btn btn-success"><i class="ti ti-download me-1"></i>Download from Marketplace</a>';
            } else if (item.is_free && item.download_url) {
                footerAction = '<a href="' + escHtml(item.download_url) + '" target="_blank" class="btn btn-success"><i class="ti ti-download me-1"></i>Download</a>';
            } else if (!item.is_free && item.buy_url) {
                footerAction = '<a href="' + escHtml(item.buy_url) + '" target="_blank" class="btn btn-primary"><i class="ti ti-shopping-cart me-1"></i>Buy Now</a>';
            }
            footerEl.innerHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>' + footerAction;

        } catch (err) {
            bodyEl.innerHTML = '<div class="alert alert-danger">' + escHtml(err.message || 'Request failed.') + '</div>';
        }
    });

    function escHtml (str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>
@endpush
