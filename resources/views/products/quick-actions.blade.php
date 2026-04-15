<div class="alert alert-info mb-0">
    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-start align-items-lg-center">
        <div>
            <h4 class="mb-1">{{ trans('plugins/docs-pro::docs-pro.quick_actions_title') }}</h4>
            <p class="mb-0 text-muted">{{ trans('plugins/docs-pro::docs-pro.quick_actions_description') }}</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" href="{{ route('docs-pro.docs.index', $product) }}">
                {{ trans('plugins/docs-pro::docs-pro.manage_docs') }}
            </a>
            <a class="btn btn-outline-secondary" href="{{ route('docs-pro.import.create', $product) }}">
                {{ trans('plugins/docs-pro::docs-pro.import_title') }}
            </a>
            <a class="btn btn-outline-secondary" href="{{ route('docs-pro.import.export', $product) }}">
                {{ trans('plugins/docs-pro::docs-pro.export_title') }}
            </a>
            <a class="btn btn-outline-dark" href="{{ route('public.docs.show', ['productSlug' => $product->slug]) }}" target="_blank">
                {{ trans('plugins/docs-pro::docs-pro.open_portal') }}
            </a>
        </div>
    </div>
</div>
