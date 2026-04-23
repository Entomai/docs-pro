@php
    $widgetActivated = filled($widgetLicenseToken ?? '');
    $widgetHasUpdate = $widgetHasUpdate ?? false;
    $widgetVersion   = $widgetVersion ?? '';
    $widgetName      = $widgetName ?? 'Plugin';
    $widgetManageUrl = $widgetManageUrl ?? (Route::has('entomai.plugins.index') ? route('entomai.plugins.index') : null);
@endphp

<div @class([
    'alert mb-4 py-2 px-3',
    'alert-success' => $widgetActivated,
    'alert-warning' => ! $widgetActivated,
])>
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-2 flex-grow-1">
            <x-core::icon
                :name="$widgetActivated ? 'ti ti-lock-open' : 'ti ti-lock'"
                style="font-size: 1.2rem;"
            />
            <div>
                <span class="fw-semibold">{{ $widgetName }}</span>
                <span class="text-muted small ms-2">
                    v{{ $widgetVersion }}
                    &mdash;
                    {{ $widgetActivated ? 'Licensed' : 'No license' }}
                </span>
                @if ($widgetHasUpdate)
                    <span class="badge bg-success-lt text-success ms-2 small">
                        <x-core::icon name="ti ti-arrow-up-circle" class="me-1" />
                        Update available
                    </span>
                @endif
            </div>
        </div>

        @if ($widgetManageUrl)
            <a href="{{ $widgetManageUrl }}" class="btn btn-sm btn-ghost-secondary flex-shrink-0">
                <x-core::icon name="ti ti-settings" class="me-1" />
                Manage License
            </a>
        @endif
    </div>
</div>
