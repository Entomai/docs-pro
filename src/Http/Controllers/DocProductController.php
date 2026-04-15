<?php

namespace Botble\DocsPro\Http\Controllers;

use Botble\Base\Events\CreatedContentEvent;
use Botble\Base\Events\UpdatedContentEvent;
use Botble\Base\Http\Actions\DeleteResourceAction;
use Botble\Base\Http\Controllers\BaseController;
use Botble\DocsPro\Forms\DocProductForm;
use Botble\DocsPro\Http\Requests\DocProductRequest;
use Botble\DocsPro\Models\DocProduct;
use Botble\DocsPro\Services\DocumentationManager;
use Botble\DocsPro\Tables\DocProductTable;
use Botble\Language\Facades\Language;

class DocProductController extends BaseController
{
    public function __construct(protected DocumentationManager $documentationManager)
    {
        $this->breadcrumb()->add(
            trans('plugins/docs-pro::docs-pro.products'),
            route('docs-pro.products.index')
        );
    }

    public function index(DocProductTable $table)
    {
        $this->pageTitle(trans('plugins/docs-pro::docs-pro.products'));

        return $table->renderTable();
    }

    public function create()
    {
        $this->pageTitle(trans('plugins/docs-pro::docs-pro.product_create'));

        return DocProductForm::create([
            'url' => route('docs-pro.products.store'),
        ])->renderForm();
    }

    public function store(DocProductRequest $request)
    {
        $product = $this->documentationManager->saveProduct($request->validated());

        if (
            is_plugin_active('language') &&
            ($referenceId = Language::getRefFrom()) &&
            ($sourceProduct = DocProduct::query()->find($referenceId))
        ) {
            $this->documentationManager->cloneTranslatedProductDocs($sourceProduct, $product);
        }

        CreatedContentEvent::dispatch(DocProduct::class, $request, $product);

        return $this->httpResponse()
            ->setPreviousUrl(route('docs-pro.products.index'))
            ->setNextUrl(route('docs-pro.products.edit', $product))
            ->setMessage(trans('core/base::notices.create_success_message'));
    }

    public function edit(DocProduct $product)
    {
        $this->pageTitle(trans('core/base::forms.edit_item', ['name' => $product->name]));

        return DocProductForm::createFromModel($product, [
            'url' => route('docs-pro.products.update', $product),
            'method' => 'PUT',
        ])->renderForm();
    }

    public function update(DocProduct $product, DocProductRequest $request)
    {
        $this->documentationManager->saveProduct($request->validated(), $product);
        UpdatedContentEvent::dispatch(DocProduct::class, $request, $product);

        return $this->httpResponse()
            ->setPreviousUrl(route('docs-pro.products.index'))
            ->setMessage(trans('core/base::notices.update_success_message'));
    }

    public function destroy(DocProduct $product)
    {
        return DeleteResourceAction::make($product);
    }
}
