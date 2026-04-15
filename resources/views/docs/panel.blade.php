@if (! $selectedDocument)
    <div class="card docs-pro-editor-card">
        <div class="card-body">
            <div class="docs-pro-editor-empty">
                <h3 class="mb-2">{{ trans('plugins/docs-pro::docs-pro.editor_select_title') }}</h3>
                <p class="text-muted mb-0">{{ trans('plugins/docs-pro::docs-pro.editor_select_description') }}</p>
            </div>
        </div>
    </div>
@elseif ($selectedDocument->isTitle())
    <div class="card docs-pro-editor-card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-1">{{ trans('plugins/docs-pro::docs-pro.editor_panel_title') }}</h3>
                <p class="text-muted mb-0">{{ trans('plugins/docs-pro::docs-pro.editor_preview_doc_only') }}</p>
            </div>
        </div>

        <div class="card-body">
            <form
                id="docs-pro-editor-form"
                action="{{ route('docs-pro.docs.update', [$product, $selectedDocument]) }}"
                data-delete-url="{{ route('docs-pro.docs.destroy', [$product, $selectedDocument]) }}"
            >
                @csrf
                @method('PUT')

                <input type="hidden" name="editor_mode" value="1">
                <input type="hidden" name="node_type" value="{{ \Botble\DocsPro\Models\Doc::NODE_TYPE_TITLE }}" data-docs-node-type>

                <div class="mb-4">
                    <label for="docs-pro-title-name" class="form-label">{{ trans('plugins/docs-pro::docs-pro.form_name') }}</label>
                    <input
                        id="docs-pro-title-name"
                        type="text"
                        class="form-control"
                        name="name"
                        value="{{ $selectedDocument->name }}"
                        maxlength="255"
                        required
                    >
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" data-docs-save-button>
                        {{ trans('core/base::forms.save') }}
                    </button>
                    <button type="button" class="btn btn-outline-danger" data-docs-delete-node data-delete-url="{{ route('docs-pro.docs.destroy', [$product, $selectedDocument]) }}" data-node-id="{{ $selectedDocument->getKey() }}">
                        {{ trans('core/base::tables.delete') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@else
    <div class="row row-cards">
        <div class="col-12">
            <div class="card docs-pro-editor-card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-1">{{ trans('plugins/docs-pro::docs-pro.editor_panel_title') }}</h3>
                        <p class="text-muted mb-0">{{ trans('plugins/docs-pro::docs-pro.editor_panel_description') }}</p>
                    </div>
                </div>

                <div class="card-body">
                    <form
                        id="docs-pro-editor-form"
                        action="{{ route('docs-pro.docs.update', [$product, $selectedDocument]) }}"
                        data-delete-url="{{ route('docs-pro.docs.destroy', [$product, $selectedDocument]) }}"
                    >
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="editor_mode" value="1">
                        <input type="hidden" name="node_type" value="{{ \Botble\DocsPro\Models\Doc::NODE_TYPE_DOC }}" data-docs-node-type>

                        <div class="row g-3">
                            <div class="col-lg-8">
                                <label for="docs-pro-doc-name" class="form-label">{{ trans('plugins/docs-pro::docs-pro.form_name') }}</label>
                                <input
                                    id="docs-pro-doc-name"
                                    type="text"
                                    class="form-control"
                                    name="name"
                                    value="{{ $selectedDocument->name }}"
                                    maxlength="255"
                                    required
                                >
                            </div>

                            <div class="col-lg-4">
                                <label for="docs-pro-doc-status" class="form-label">{{ trans('plugins/docs-pro::docs-pro.form_status') }}</label>
                                <select id="docs-pro-doc-status" class="form-select" name="status">
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($selectedDocument->status?->getValue() === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="docs-pro-doc-excerpt" class="form-label">{{ trans('plugins/docs-pro::docs-pro.form_excerpt') }}</label>
                                <textarea id="docs-pro-doc-excerpt" class="form-control" name="excerpt" rows="3">{{ $selectedDocument->excerpt }}</textarea>
                            </div>

                            <div class="col-md-6">
                                <input type="hidden" name="is_default" value="0">
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_default" value="1" @checked($selectedDocument->is_default)>
                                    <span class="form-check-label">{{ trans('plugins/docs-pro::docs-pro.form_doc_is_default') }}</span>
                                </label>
                            </div>

                            <div class="col-md-6">
                                <input type="hidden" name="is_section" value="0">
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_section" value="1" @checked($selectedDocument->is_section)>
                                    <span class="form-check-label">{{ trans('plugins/docs-pro::docs-pro.form_is_section') }}</span>
                                </label>
                            </div>

                            <div class="col-12">
                                <label for="docs-pro-doc-markdown" class="form-label">{{ trans('plugins/docs-pro::docs-pro.form_markdown') }}</label>
                                <textarea
                                    id="docs-pro-doc-markdown"
                                    class="form-control docs-pro-editor-textarea"
                                    name="markdown_content"
                                    rows="24"
                                    style="min-height: 42rem; height: 70vh;"
                                    data-docs-markdown-input
                                >{{ $selectedDocument->markdown_content }}</textarea>
                            </div>
                        </div>

                        @if ($selectedDocument->markdown_content === null && $selectedDocument->content)
                            <div class="alert alert-warning mt-4 mb-0 d-none" data-docs-legacy-alert>
                                {{ trans('plugins/docs-pro::docs-pro.editor_legacy_content_notice') }}
                            </div>
                        @endif

                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <button type="submit" class="btn btn-primary" data-docs-save-button>
                                {{ trans('core/base::forms.save') }}
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-docs-delete-node data-delete-url="{{ route('docs-pro.docs.destroy', [$product, $selectedDocument]) }}" data-node-id="{{ $selectedDocument->getKey() }}">
                                {{ trans('core/base::tables.delete') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card docs-pro-editor-card">
                <div class="card-header">
                    <h3 class="card-title mb-0">{{ trans('plugins/docs-pro::docs-pro.editor_preview_title') }}</h3>
                </div>

                <div class="card-body">
                    <div class="docs-pro-editor-preview-wrap">
                        <div class="docs-pro-editor-preview" data-docs-preview>
                            {!! $selectedDocument->content ?: '<p class="text-muted mb-0">' . e(trans('plugins/docs-pro::docs-pro.editor_preview_empty')) . '</p>' !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
