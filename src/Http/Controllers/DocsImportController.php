<?php

namespace Botble\DocsPro\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\DocsPro\Http\Requests\DocsImportRequest;
use Botble\DocsPro\Models\DocProduct;
use Botble\DocsPro\Services\DocumentationManager;

class DocsImportController extends BaseController
{
    public function __construct(protected DocumentationManager $documentationManager) {}

    public function create(DocProduct $product)
    {
        $this->breadcrumb()
            ->add(trans('plugins/docs-pro::docs-pro.products'), route('docs-pro.products.index'))
            ->add($product->name, route('docs-pro.products.edit', $product))
            ->add(trans('plugins/docs-pro::docs-pro.import_title'));

        $this->pageTitle(trans('plugins/docs-pro::docs-pro.import_title'));

        return view('plugins/docs-pro::products.import', compact('product'));
    }

    public function store(DocProduct $product, DocsImportRequest $request)
    {
        $result = $this->documentationManager->importArchive(
            $product,
            $request->file('archive'),
            $request->boolean('replace_existing', true)
        );

        return $this->httpResponse()
            ->setPreviousUrl(route('docs-pro.docs.index', $product))
            ->setNextUrl(route('docs-pro.docs.index', $product))
            ->setMessage(trans('plugins/docs-pro::docs-pro.import_success', $result));
    }

    public function export(DocProduct $product)
    {
        $archive = $this->documentationManager->exportArchive($product);

        return response()
            ->download($archive['path'], $archive['filename'])
            ->deleteFileAfterSend(true);
    }
}
