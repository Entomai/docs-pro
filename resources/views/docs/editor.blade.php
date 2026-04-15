@extends(BaseHelper::getAdminMasterLayoutTemplate())

@push('header')
    <link rel="stylesheet" href="{{ asset('vendor/core/core/base/libraries/jquery-nestable/jquery.nestable.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/core/plugins/docs-pro/css/docs-editor.css') }}">
@endpush

@push('footer')
    <script src="{{ asset('vendor/core/core/base/libraries/jquery-nestable/jquery.nestable.min.js') }}"></script>
    <script src="{{ asset('vendor/core/plugins/docs-pro/js/docs-editor.js') }}"></script>
@endpush

@section('content')
    <div
        class="docs-pro-editor-page"
        data-docs-editor
        data-create-url="{{ route('docs-pro.docs.store', $product) }}"
        data-save-all-url="{{ route('docs-pro.docs.save-all', $product) }}"
        data-structure-url="{{ route('docs-pro.docs.structure', $product) }}"
        data-preview-url="{{ route('docs-pro.docs.preview', $product) }}"
        data-fetch-url="{{ route('docs-pro.docs.data', $product) }}"
        data-index-url="{{ route('docs-pro.docs.index', $product) }}"
        data-current-node="{{ $selectedDocument?->getKey() }}"
        data-current-node-type="{{ $selectedDocument?->node_type }}"
        data-preview-empty="{{ trans('plugins/docs-pro::docs-pro.editor_preview_empty') }}"
        data-preview-doc-only="{{ trans('plugins/docs-pro::docs-pro.editor_preview_doc_only') }}"
        data-delete-confirm="{{ trans('plugins/docs-pro::docs-pro.editor_delete_confirm') }}"
        data-unsaved-confirm="{{ trans('plugins/docs-pro::docs-pro.editor_unsaved_changes_confirm') }}"
        data-structure-save-error="{{ trans('plugins/docs-pro::docs-pro.editor_structure_save_error') }}"
    >
        <div class="row row-cards">
            <div class="col-xl-3">
                <div class="card docs-pro-editor-card docs-pro-editor-structure-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title mb-1">{{ trans('plugins/docs-pro::docs-pro.editor_structure_title') }}</h3>
                            <p class="text-muted mb-0">{{ trans('plugins/docs-pro::docs-pro.editor_structure_description') }}</p>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="alert alert-info bg-white text-info mb-4">
                            {{ trans('plugins/docs-pro::docs-pro.editor_drag_drop') }}
                        </div>

                        <div class="docs-pro-editor-toolbar mb-4">
                            <button type="button" class="btn btn-primary" data-docs-create-node="doc">
                                {{ trans('plugins/docs-pro::docs-pro.editor_add_doc') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-docs-create-node="title">
                                {{ trans('plugins/docs-pro::docs-pro.editor_add_title') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-docs-create-node="separator">
                                {{ trans('plugins/docs-pro::docs-pro.editor_add_separator') }}
                            </button>
                            <button type="button" class="btn btn-success" data-docs-save-all>
                                {{ trans('core/base::forms.save') }}
                            </button>
                        </div>

                        <div id="docs-pro-editor-tree-container" class="docs-pro-editor-tree-shell">
                            {!! $treeHtml !!}
                        </div>
                    </div>

                    <div class="card-footer d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('docs-pro.products.edit', $product) }}">
                            {{ trans('core/base::forms.edit') }} {{ trans('plugins/docs-pro::docs-pro.context_product') }}
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

            <div class="col-xl-9">
                <div id="docs-pro-editor-panel-container">
                    {!! $panelHtml !!}
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="docs-pro-editor-state">
        @json($editorState)
    </script>
@endsection
