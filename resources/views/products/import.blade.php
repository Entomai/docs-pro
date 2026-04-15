@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
    <div class="row row-cards">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-1">{{ trans('plugins/docs-pro::docs-pro.import_title') }}</h3>
                        <p class="text-muted mb-0">{{ trans('plugins/docs-pro::docs-pro.import_intro') }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('docs-pro.import.store', $product) }}" enctype="multipart/form-data">
                    @csrf

                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="archive">{{ trans('plugins/docs-pro::docs-pro.import_archive') }}</label>
                            <input id="archive" name="archive" type="file" accept=".zip" class="form-control" required>
                            @error('archive')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="replace_existing" value="1" checked>
                            <span class="form-check-label">
                                {{ trans('plugins/docs-pro::docs-pro.import_replace_existing') }}
                            </span>
                        </label>
                        <div class="form-text">{{ trans('plugins/docs-pro::docs-pro.import_replace_existing_helper') }}</div>
                    </div>

                    <div class="card-footer d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <div class="text-muted">
                            @if ($product->source_archive_name)
                                {{ trans('plugins/docs-pro::docs-pro.import_latest_archive') }}:
                                <span class="fw-semibold">{{ $product->source_archive_name }}</span>
                            @endif
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-outline-secondary" href="{{ route('docs-pro.docs.index', $product) }}">
                                {{ trans('plugins/docs-pro::docs-pro.back_to_docs') }}
                            </a>
                            <a class="btn btn-outline-secondary" href="{{ route('docs-pro.import.export', $product) }}">
                                {{ trans('plugins/docs-pro::docs-pro.export_title') }}
                            </a>
                            <button type="submit" class="btn btn-primary">
                                {{ trans('plugins/docs-pro::docs-pro.import_action') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ trans('plugins/docs-pro::docs-pro.context_product') }}</h3>
                </div>
                <div class="card-body">
                    <div class="fw-semibold">{{ $product->name }}</div>
                    <div class="text-muted small">{{ $product->slug }}</div>

                    @if ($product->description)
                        <p class="mt-3 mb-0 text-muted">{{ $product->description }}</p>
                    @endif
                </div>
                <div class="card-footer d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('docs-pro.products.edit', $product) }}">
                        {{ trans('core/base::forms.edit') }}
                    </a>
                    <a class="btn btn-outline-dark" href="{{ route('public.docs.show', ['productSlug' => $product->slug]) }}" target="_blank">
                        {{ trans('plugins/docs-pro::docs-pro.open_portal') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
