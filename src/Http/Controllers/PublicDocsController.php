<?php

namespace Botble\DocsPro\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\DocsPro\Models\DocProduct;
use Botble\DocsPro\Services\DocsPortalService;
use Botble\DocsPro\Services\DocumentationManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicDocsController extends BaseController
{
    public function __construct(
        protected DocsPortalService $portalService,
        protected DocumentationManager $documentationManager
    ) {}

    public function index()
    {
        return view('plugins/docs-pro::portal.show', $this->portalService->getIndexData());
    }

    public function show(string $productSlug, ?string $path = null)
    {
        return view('plugins/docs-pro::portal.show', $this->portalService->getProductData($productSlug, $path));
    }

    public function asset(string $productSlug, string $path)
    {
        $product = DocProduct::query()
            ->published()
            ->where('slug', $productSlug)
            ->first();

        if (! $product) {
            throw new NotFoundHttpException;
        }

        return $this->documentationManager->assetResponse($product, $path);
    }
}
