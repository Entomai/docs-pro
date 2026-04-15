<div class="alert alert-secondary">
    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-start align-items-lg-center">
        <div>
            <div class="text-uppercase text-muted small mb-1">{{ trans('plugins/docs-pro::docs-pro.context_product') }}</div>
            <div class="fw-semibold">{{ $product->name }}</div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('docs-pro.docs.index', $product) }}">
                {{ trans('plugins/docs-pro::docs-pro.context_manage') }}
            </a>
            <a class="btn btn-outline-secondary" href="{{ route('docs-pro.import.create', $product) }}">
                {{ trans('plugins/docs-pro::docs-pro.context_import') }}
            </a>
            <a class="btn btn-outline-secondary" href="{{ route('docs-pro.import.export', $product) }}">
                {{ trans('plugins/docs-pro::docs-pro.context_export') }}
            </a>
            <a class="btn btn-outline-dark" href="{{ route('public.docs.show', ['productSlug' => $product->slug]) }}" target="_blank">
                {{ trans('plugins/docs-pro::docs-pro.context_portal') }}
            </a>
        </div>
    </div>
</div>
