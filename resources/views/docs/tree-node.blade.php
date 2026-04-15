@foreach ($nodes as $node)
    @php
        /** @var \Botble\DocsPro\Models\Doc $document */
        $document = $node['document'];
        $isSelected = $selectedDocument && (int) $selectedDocument->getKey() === (int) $document->getKey();
        $typeLabel = match ($document->node_type) {
            \Botble\DocsPro\Models\Doc::NODE_TYPE_TITLE => trans('plugins/docs-pro::docs-pro.node_type_title'),
            \Botble\DocsPro\Models\Doc::NODE_TYPE_SEPARATOR => trans('plugins/docs-pro::docs-pro.node_type_separator'),
            default => trans('plugins/docs-pro::docs-pro.node_type_doc'),
        };
    @endphp

    <li class="dd-item docs-pro-editor-tree-item {{ $isSelected ? 'is-selected' : '' }}" data-id="{{ $document->getKey() }}">
        <div class="docs-pro-editor-tree-row">
            <div class="dd-handle docs-pro-editor-tree-handle" title="Drag">
                <span class="docs-pro-editor-tree-grip" aria-hidden="true"></span>
            </div>

            @if ($document->isSeparator())
                <div class="docs-pro-editor-tree-static">
                    <span class="docs-pro-editor-tree-copy">
                        <span class="docs-pro-editor-tree-separator" aria-hidden="true"></span>
                    </span>

                    <span class="docs-pro-editor-tree-badges">
                        <span class="badge bg-secondary-lt">{{ $typeLabel }}</span>
                    </span>
                </div>

                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger docs-pro-editor-tree-action"
                    title="{{ trans('core/base::tables.delete') }}"
                    data-docs-delete-node
                    data-delete-url="{{ route('docs-pro.docs.destroy', [$product, $document]) }}"
                    data-node-id="{{ $document->getKey() }}"
                >
                    &times;
                </button>
            @else
                <button
                    type="button"
                    class="docs-pro-editor-tree-button"
                    data-docs-node-id="{{ $document->getKey() }}"
                >
                    <span class="docs-pro-editor-tree-copy">
                        <span class="docs-pro-editor-tree-title">{{ $document->menuTitle() }}</span>
                    </span>

                    <span class="docs-pro-editor-tree-badges">
                        <span class="badge bg-secondary-lt">{{ $typeLabel }}</span>

                        @if ($document->is_default)
                            <span class="badge bg-primary-lt">{{ trans('plugins/docs-pro::docs-pro.editor_default_badge') }}</span>
                        @endif
                    </span>
                </button>
            @endif
        </div>

        @if ($node['children'] !== [])
            <ol class="dd-list">
                @include('plugins/docs-pro::docs.tree-node', [
                    'nodes' => $node['children'],
                    'product' => $product,
                    'selectedDocument' => $selectedDocument,
                ])
            </ol>
        @endif
    </li>
@endforeach
