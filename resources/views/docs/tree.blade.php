@if ($documents->isEmpty())
    <div class="docs-pro-editor-empty">
        <h4 class="mb-2">{{ trans('plugins/docs-pro::docs-pro.editor_no_docs_title') }}</h4>
        <p class="text-muted mb-0">{{ trans('plugins/docs-pro::docs-pro.editor_no_docs_description') }}</p>
    </div>
@else
    <div class="dd docs-pro-editor-tree" id="docs-pro-editor-tree">
        <ol class="dd-list">
            @include('plugins/docs-pro::docs.tree-node', [
                'nodes' => $treeNodes,
                'product' => $product,
                'selectedDocument' => $selectedDocument,
            ])
        </ol>
    </div>
@endif
