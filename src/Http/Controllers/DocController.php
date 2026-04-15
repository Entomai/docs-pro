<?php

namespace Botble\DocsPro\Http\Controllers;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Http\Controllers\BaseController;
use Botble\DocsPro\Forms\DocForm;
use Botble\DocsPro\Http\Requests\DocRequest;
use Botble\DocsPro\Models\Doc;
use Botble\DocsPro\Models\DocProduct;
use Botble\DocsPro\Services\DocumentationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocController extends BaseController
{
    public function __construct(protected DocumentationManager $documentationManager) {}

    public function index(DocProduct $product, Request $request)
    {
        $this->breadcrumb()
            ->add(trans('plugins/docs-pro::docs-pro.products'), route('docs-pro.products.index'))
            ->add($product->name, route('docs-pro.products.edit', $product))
            ->add(trans('plugins/docs-pro::docs-pro.docs'));

        $this->pageTitle(trans('plugins/docs-pro::docs-pro.docs_for_product', ['name' => $product->name]));

        $documents = $this->getDocuments($product);
        $selectedDocument = $this->resolveSelectedDocument($documents, $request->integer('node'));

        return view('plugins/docs-pro::docs.editor', [
            'product' => $product,
            'selectedDocument' => $selectedDocument,
            'editorState' => $this->serializeEditorState($documents, $selectedDocument),
            'treeHtml' => $this->renderTree($product, $documents, $selectedDocument),
            'panelHtml' => $this->renderPanel($product, $selectedDocument),
        ]);
    }

    public function data(DocProduct $product, Request $request): JsonResponse
    {
        $documents = $this->getDocuments($product);
        $selectedDocument = $this->resolveSelectedDocument($documents, $request->integer('node'));

        return response()->json([
            'data' => [
                'editor_state' => $this->serializeEditorState($documents, $selectedDocument),
                'tree_html' => $this->renderTree($product, $documents, $selectedDocument),
                'panel_html' => $this->renderPanel($product, $selectedDocument),
                'selected_node' => $selectedDocument ? [
                    'id' => $selectedDocument->getKey(),
                    'type' => $selectedDocument->node_type,
                ] : null,
            ],
        ]);
    }

    public function create(DocProduct $product)
    {
        $this->breadcrumb()
            ->add(trans('plugins/docs-pro::docs-pro.products'), route('docs-pro.products.index'))
            ->add($product->name, route('docs-pro.products.edit', $product))
            ->add(trans('plugins/docs-pro::docs-pro.docs'), route('docs-pro.docs.index', $product))
            ->add(trans('plugins/docs-pro::docs-pro.doc_create'));

        $this->pageTitle(trans('plugins/docs-pro::docs-pro.doc_create'));

        return DocForm::create([
            'url' => route('docs-pro.docs.store', $product),
            'product' => $product,
        ])->renderForm();
    }

    public function store(DocProduct $product, DocRequest $request)
    {
        $document = $this->documentationManager->saveDocument($product, $request->validated());

        if ($request->boolean('editor_mode')) {
            return response()->json([
                'message' => trans('plugins/docs-pro::docs-pro.editor_node_created'),
                'data' => [
                    'id' => $document->getKey(),
                    'type' => $document->node_type,
                ],
            ]);
        }

        return $this->httpResponse()
            ->setPreviousUrl(route('docs-pro.docs.index', [
                'product' => $product->getKey(),
                'node' => $document->getKey(),
            ]))
            ->setNextUrl(route('docs-pro.docs.edit', [$product, $document]))
            ->setMessage(trans('core/base::notices.create_success_message'));
    }

    public function edit(DocProduct $product, Doc $doc)
    {
        $this->ensureBelongsToProduct($product, $doc);

        $this->breadcrumb()
            ->add(trans('plugins/docs-pro::docs-pro.products'), route('docs-pro.products.index'))
            ->add($product->name, route('docs-pro.products.edit', $product))
            ->add(trans('plugins/docs-pro::docs-pro.docs'), route('docs-pro.docs.index', $product))
            ->add($doc->name);

        $this->pageTitle(trans('core/base::forms.edit_item', ['name' => $doc->name]));

        return DocForm::createFromModel($doc, [
            'url' => route('docs-pro.docs.update', [$product, $doc]),
            'method' => 'PUT',
            'product' => $product,
        ])->renderForm();
    }

    public function update(DocProduct $product, Doc $doc, DocRequest $request)
    {
        $this->ensureBelongsToProduct($product, $doc);

        $document = $this->documentationManager->saveDocument($product, $request->validated(), $doc);

        if ($request->boolean('editor_mode')) {
            return response()->json([
                'message' => trans('plugins/docs-pro::docs-pro.editor_node_saved'),
                'data' => [
                    'id' => $document->getKey(),
                    'type' => $document->node_type,
                ],
            ]);
        }

        return $this->httpResponse()
            ->setPreviousUrl(route('docs-pro.docs.index', $product))
            ->setMessage(trans('core/base::notices.update_success_message'));
    }

    public function saveAll(DocProduct $product, Request $request): JsonResponse
    {
        $request->validate([
            'tree' => ['required', 'array'],
            'nodes' => ['required', 'array'],
            'selected_node_id' => ['nullable'],
        ]);

        $result = $this->documentationManager->saveEditorSnapshot(
            $product,
            $request->input('tree', []),
            $request->input('nodes', []),
            $request->input('selected_node_id')
        );

        return response()->json([
            'message' => trans('plugins/docs-pro::docs-pro.editor_all_saved'),
            'data' => [
                'editor_state' => $this->serializeEditorState($result['documents'], $result['selectedDocument']),
            ],
        ]);
    }

    public function preview(DocProduct $product, Request $request): JsonResponse
    {
        $request->validate([
            'markdown' => ['nullable', 'string'],
        ]);

        return response()->json([
            'data' => [
                'html' => $this->documentationManager->previewMarkdown((string) $request->input('markdown')),
            ],
        ]);
    }

    public function updateStructure(DocProduct $product, Request $request): JsonResponse
    {
        $request->validate([
            'tree' => ['required', 'array'],
        ]);

        $this->documentationManager->syncDocumentTree($product, $request->input('tree', []));

        return response()->json([
            'message' => trans('plugins/docs-pro::docs-pro.editor_structure_saved'),
        ]);
    }

    public function destroy(DocProduct $product, Doc $doc)
    {
        $this->ensureBelongsToProduct($product, $doc);

        $documentIds = $this->collectSubtreeIds($doc);

        Doc::query()
            ->where('product_id', $product->getKey())
            ->whereIn('id', $documentIds)
            ->delete();

        if (! Doc::query()->where('product_id', $product->getKey())->where('node_type', Doc::NODE_TYPE_DOC)->where('is_default', true)->exists()) {
            Doc::query()
                ->where('product_id', $product->getKey())
                ->where('node_type', Doc::NODE_TYPE_DOC)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->limit(1)
                ->update(['is_default' => true]);
        }

        return $this->httpResponse()
            ->setMessage(trans('core/base::notices.delete_success_message'));
    }

    protected function getDocuments(DocProduct $product): Collection
    {
        return Doc::query()
            ->where('product_id', $product->getKey())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function resolveSelectedDocument(Collection $documents, ?int $requestedNodeId = null): ?Doc
    {
        $requestedDocument = $requestedNodeId ? $documents->firstWhere('id', $requestedNodeId) : null;

        if ($requestedDocument && ! $requestedDocument->isSeparator()) {
            return $requestedDocument;
        }

        return $documents->firstWhere('is_default', true)
            ?: $documents->first(fn (Doc $document): bool => $document->isDoc())
            ?: $documents->first(fn (Doc $document): bool => $document->isTitle());
    }

    protected function renderTree(DocProduct $product, Collection $documents, ?Doc $selectedDocument): string
    {
        return view('plugins/docs-pro::docs.tree', [
            'documents' => $documents,
            'treeNodes' => $this->buildTree($documents),
            'product' => $product,
            'selectedDocument' => $selectedDocument,
        ])->render();
    }

    protected function renderPanel(DocProduct $product, ?Doc $selectedDocument): string
    {
        return view('plugins/docs-pro::docs.panel', [
            'product' => $product,
            'selectedDocument' => $selectedDocument,
            'statusOptions' => BaseStatusEnum::labels(),
        ])->render();
    }

    protected function ensureBelongsToProduct(DocProduct $product, Doc $doc): void
    {
        if ((int) $doc->product_id !== (int) $product->getKey()) {
            throw new NotFoundHttpException;
        }
    }

    protected function buildTree($documents, int|string|null $parentId = null): array
    {
        $grouped = $documents->groupBy(fn (Doc $document): int => (int) ($document->parent_id ?: 0));

        $mapNodes = function (int $currentParentId = 0) use (&$mapNodes, $grouped): array {
            $items = [];

            foreach ($grouped->get($currentParentId, collect()) as $document) {
                $children = $mapNodes((int) $document->getKey());

                $items[] = [
                    'document' => $document,
                    'children' => $document->isDoc() ? $children : [],
                ];

                if ($document->isTitle()) {
                    $items = [...$items, ...$children];
                }
            }

            return $items;
        };

        return $mapNodes((int) ($parentId ?: 0));
    }

    protected function serializeEditorState(Collection $documents, ?Doc $selectedDocument): array
    {
        return [
            'nodes' => $documents
                ->map(fn (Doc $document): array => [
                    'id' => (string) $document->getKey(),
                    'parent_id' => $document->parent_id ? (string) $document->parent_id : null,
                    'node_type' => $document->node_type,
                    'name' => $document->name,
                    'slug_path' => $document->slug_path,
                    'excerpt' => $document->excerpt,
                    'markdown_content' => $document->markdown_content,
                    'content' => $document->content,
                    'status' => $document->status?->getValue() ?: BaseStatusEnum::PUBLISHED,
                    'is_default' => (bool) $document->is_default,
                    'is_section' => (bool) $document->is_section,
                    'sort_order' => (int) $document->sort_order,
                ])
                ->values()
                ->all(),
            'selected_node_id' => $selectedDocument ? (string) $selectedDocument->getKey() : null,
            'status_options' => BaseStatusEnum::labels(),
            'labels' => [
                'save' => trans('core/base::forms.save'),
                'delete' => trans('core/base::tables.delete'),
                'form_name' => trans('plugins/docs-pro::docs-pro.form_name'),
                'form_status' => trans('plugins/docs-pro::docs-pro.form_status'),
                'form_excerpt' => trans('plugins/docs-pro::docs-pro.form_excerpt'),
                'form_content' => trans('plugins/docs-pro::docs-pro.form_content'),
                'form_markdown' => trans('plugins/docs-pro::docs-pro.form_markdown'),
                'form_doc_is_default' => trans('plugins/docs-pro::docs-pro.form_doc_is_default'),
                'form_is_section' => trans('plugins/docs-pro::docs-pro.form_is_section'),
                'node_type_doc' => trans('plugins/docs-pro::docs-pro.node_type_doc'),
                'node_type_title' => trans('plugins/docs-pro::docs-pro.node_type_title'),
                'node_type_separator' => trans('plugins/docs-pro::docs-pro.node_type_separator'),
                'editor_no_docs_title' => trans('plugins/docs-pro::docs-pro.editor_no_docs_title'),
                'editor_no_docs_description' => trans('plugins/docs-pro::docs-pro.editor_no_docs_description'),
                'editor_select_title' => trans('plugins/docs-pro::docs-pro.editor_select_title'),
                'editor_select_description' => trans('plugins/docs-pro::docs-pro.editor_select_description'),
                'editor_panel_title' => trans('plugins/docs-pro::docs-pro.editor_panel_title'),
                'editor_panel_description' => trans('plugins/docs-pro::docs-pro.editor_panel_description'),
                'editor_tab_content' => trans('plugins/docs-pro::docs-pro.editor_tab_content'),
                'editor_tab_preview' => trans('plugins/docs-pro::docs-pro.editor_tab_preview'),
                'editor_use_markdown' => trans('plugins/docs-pro::docs-pro.editor_use_markdown'),
                'editor_preview_title' => trans('plugins/docs-pro::docs-pro.editor_preview_title'),
                'editor_preview_empty' => trans('plugins/docs-pro::docs-pro.editor_preview_empty'),
                'editor_preview_doc_only' => trans('plugins/docs-pro::docs-pro.editor_preview_doc_only'),
                'editor_legacy_content_notice' => trans('plugins/docs-pro::docs-pro.editor_legacy_content_notice'),
                'editor_default_badge' => trans('plugins/docs-pro::docs-pro.editor_default_badge'),
                'editor_section_badge' => trans('plugins/docs-pro::docs-pro.editor_section_badge'),
            ],
        ];
    }

    protected function collectSubtreeIds(Doc $doc): array
    {
        $ids = [(int) $doc->getKey()];

        $children = Doc::query()
            ->where('parent_id', $doc->getKey())
            ->get();

        foreach ($children as $child) {
            $ids = array_merge($ids, $this->collectSubtreeIds($child));
        }

        return $ids;
    }
}
