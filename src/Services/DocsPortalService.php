<?php

namespace Botble\DocsPro\Services;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\DocsPro\Models\Doc;
use Botble\DocsPro\Models\DocProduct;
use Botble\Language\Facades\Language;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocsPortalService
{
    public function __construct(protected DocumentationManager $documentationManager) {}

    public function getIndexData(): array
    {
        $products = $this->getPublishedProducts();
        $activeProduct = $products->first();

        if (! $activeProduct) {
            return $this->emptyState();
        }

        return $this->buildProductData($activeProduct);
    }

    public function getProductData(string $productSlug, ?string $requestedPath = null): array
    {
        $products = $this->getPublishedProducts();
        $activeProduct = $products->firstWhere('slug', $productSlug);

        if (! $activeProduct) {
            throw new NotFoundHttpException;
        }

        return $this->buildProductData($activeProduct, $products, $requestedPath);
    }

    protected function getPublishedProducts(): Collection
    {
        if (! $this->supportsMultipleLanguages()) {
            return $this->makePublishedProductsQuery()->get();
        }

        $currentLanguageProducts = $this->filterProductsByLanguage(
            $this->makePublishedProductsQuery(),
            Language::getCurrentLocaleCode()
        )->get();

        if ($currentLanguageProducts->isNotEmpty()) {
            return $currentLanguageProducts;
        }

        return $this->filterProductsByLanguage(
            $this->makePublishedProductsQuery(),
            Language::getDefaultLocaleCode(),
            true
        )->get();
    }

    protected function buildProductData(DocProduct $product, ?Collection $products = null, ?string $requestedPath = null): array
    {
        $products ??= $this->getPublishedProducts();

        $documents = Doc::query()
            ->where('product_id', $product->getKey())
            ->where(function ($query): void {
                $query
                    ->where('status', BaseStatusEnum::PUBLISHED)
                    ->orWhereIn('node_type', [
                        Doc::NODE_TYPE_TITLE,
                        Doc::NODE_TYPE_SEPARATOR,
                    ]);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $flatDocuments = $this->sortHierarchically($documents);
        $routableDocuments = $flatDocuments->filter(fn (Doc $document): bool => $document->isDoc())->values();
        $activeDocument = $requestedPath
            ? $routableDocuments->firstWhere('slug_path', trim($requestedPath, '/'))
            : $this->resolveDefaultDocument($routableDocuments);

        if ($requestedPath && ! $activeDocument) {
            throw new NotFoundHttpException;
        }

        $tree = $this->buildTree($flatDocuments, $product, $activeDocument);
        $activeTrail = $activeDocument ? $this->collectAncestorIds($activeDocument, $documents->keyBy('id')) : [];
        $navigation = $this->resolvePreviousAndNext($routableDocuments, $activeDocument);

        return [
            'products' => $products,
            'activeProduct' => $product,
            'documents' => $flatDocuments,
            'navigationTree' => $tree,
            'activeDocument' => $activeDocument,
            'renderedContent' => $activeDocument
                ? $this->documentationManager->renderDocumentContent($product, $activeDocument)
                : null,
            'activeTrail' => $activeTrail,
            'previousDocument' => $navigation['previous'],
            'nextDocument' => $navigation['next'],
            'previousUrl' => $navigation['previous']
                ? $this->localizedProductUrl($product, $navigation['previous']->slug_path)
                : null,
            'nextUrl' => $navigation['next']
                ? $this->localizedProductUrl($product, $navigation['next']->slug_path)
                : null,
            'indexUrl' => $this->localizedIndexUrl(),
            'productUrls' => $products->mapWithKeys(fn (DocProduct $item): array => [
                $item->getKey() => $this->localizedProductUrl($item),
            ]),
            'languageSwitcher' => $this->buildLanguageSwitcher($product, $activeDocument),
        ];
    }

    protected function emptyState(): array
    {
        return [
            'products' => collect(),
            'activeProduct' => null,
            'documents' => collect(),
            'navigationTree' => [],
            'activeDocument' => null,
            'renderedContent' => null,
            'activeTrail' => [],
            'previousDocument' => null,
            'nextDocument' => null,
            'previousUrl' => null,
            'nextUrl' => null,
            'indexUrl' => $this->localizedIndexUrl(),
            'productUrls' => collect(),
            'languageSwitcher' => $this->buildLanguageSwitcher(),
        ];
    }

    protected function resolveDefaultDocument(Collection $documents): ?Doc
    {
        return $documents->firstWhere('is_default', true) ?: $documents->first();
    }

    protected function sortHierarchically(Collection $documents): Collection
    {
        $grouped = $documents
            ->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
            ->groupBy(fn (Doc $doc): int => (int) ($doc->parent_id ?: 0));

        $sorted = collect();

        $addChildren = function (int $parentId = 0) use (&$addChildren, $grouped, $sorted): void {
            foreach ($grouped->get($parentId, collect()) as $document) {
                $sorted->push($document);
                $addChildren((int) $document->getKey());
            }
        };

        $addChildren();

        return $sorted;
    }

    protected function buildTree(Collection $documents, DocProduct $product, ?Doc $activeDocument = null): array
    {
        $childrenMap = $documents->groupBy(fn (Doc $doc): int => (int) ($doc->parent_id ?: 0));

        $mapNode = function (int $parentId = 0) use (&$mapNode, $childrenMap, $product, $activeDocument): array {
            $items = [];

            foreach ($childrenMap->get($parentId, collect()) as $document) {
                $children = $mapNode((int) $document->getKey());

                $items[] = [
                    'document' => $document,
                    'url' => $document->isDoc()
                        ? $this->localizedProductUrl(
                            $product,
                            $document->slug_path !== '' ? $document->slug_path : null
                        )
                        : null,
                    'is_active' => $activeDocument?->getKey() === $document->getKey(),
                    'children' => $document->isDoc() ? $children : [],
                ];

                if ($document->isTitle()) {
                    $items = [...$items, ...$children];
                }
            }

            return $items;
        };

        return $mapNode();
    }

    protected function collectAncestorIds(Doc $document, Collection $documentsById): array
    {
        $ids = [(int) $document->getKey()];
        $currentParentId = $document->parent_id;

        while ($currentParentId) {
            $ids[] = (int) $currentParentId;
            $currentParentId = $documentsById->get($currentParentId)?->parent_id;
        }

        return $ids;
    }

    protected function resolvePreviousAndNext(Collection $documents, ?Doc $activeDocument): array
    {
        if (! $activeDocument) {
            return [
                'previous' => null,
                'next' => null,
            ];
        }

        $index = $documents->search(fn (Doc $document): bool => $document->getKey() === $activeDocument->getKey());

        if ($index === false) {
            return [
                'previous' => null,
                'next' => null,
            ];
        }

        return [
            'previous' => $documents->get($index - 1),
            'next' => $documents->get($index + 1),
        ];
    }

    protected function makePublishedProductsQuery(): Builder
    {
        return DocProduct::query()
            ->published()
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    protected function filterProductsByLanguage(Builder $query, ?string $languageCode, bool $includeUntranslated = false): Builder
    {
        if (! $languageCode) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($languageCode, $includeUntranslated): void {
            $builder->whereHas('languageMetas', function (Builder $relationQuery) use ($languageCode): void {
                $relationQuery->where('lang_meta_code', $languageCode);
            });

            if ($includeUntranslated) {
                $builder->orWhereDoesntHave('languageMetas');
            }
        });
    }

    protected function supportsMultipleLanguages(): bool
    {
        return is_plugin_active('language') && count(Language::getSupportedLocales()) > 1;
    }

    protected function localizedIndexUrl(?string $locale = null): string
    {
        return $this->localizeUrl(route('public.docs.index'), $locale);
    }

    protected function localizedProductUrl(DocProduct $product, ?string $path = null, ?string $locale = null): string
    {
        return $this->localizeUrl(route('public.docs.show', [
            'productSlug' => $product->slug,
            'path' => $path ?: null,
        ]), $locale);
    }

    protected function localizeUrl(string $url, ?string $locale = null): string
    {
        if (! $this->supportsMultipleLanguages()) {
            return $url;
        }

        $locale ??= Language::getCurrentLocale();

        return Language::getLocalizedURL($locale, $url, [], false) ?: $url;
    }

    protected function buildLanguageSwitcher(?DocProduct $activeProduct = null, ?Doc $activeDocument = null): array
    {
        if (! $this->supportsMultipleLanguages()) {
            return [];
        }

        $currentLocale = Language::getCurrentLocale();
        $currentLanguageCode = Language::getCurrentLocaleCode();

        return collect(Language::getSupportedLocales())
            ->map(function (array $properties, string $locale) use ($activeProduct, $activeDocument, $currentLocale, $currentLanguageCode): array {
                $targetUrl = $this->localizedIndexUrl($locale);

                if ($activeProduct) {
                    $targetProduct = $this->resolveTranslatedProduct($activeProduct, $properties['lang_code']);

                    if (! $targetProduct && $properties['lang_code'] === $currentLanguageCode) {
                        $targetProduct = $activeProduct;
                    }

                    if ($targetProduct) {
                        $targetDocument = $activeDocument
                            ? $this->resolveTranslatedDocument($targetProduct, $activeDocument)
                            : null;

                        $targetUrl = $this->localizedProductUrl(
                            $targetProduct,
                            $targetDocument?->slug_path,
                            $locale
                        );
                    }
                }

                return [
                    'locale' => $locale,
                    'lang_code' => $properties['lang_code'],
                    'name' => $properties['lang_name'],
                    'flag' => $properties['lang_flag'],
                    'is_current' => $locale === $currentLocale,
                    'url' => $targetUrl,
                ];
            })
            ->values()
            ->all();
    }

    protected function resolveTranslatedProduct(DocProduct $product, string $targetLanguageCode): ?DocProduct
    {
        $origin = $product->languageMetas()
            ->value('lang_meta_origin');

        if (! $origin) {
            return null;
        }

        return DocProduct::query()
            ->published()
            ->whereKey(
                DocProduct::query()
                    ->select('docs_pros.id')
                    ->join('language_meta', function ($join): void {
                        $join
                            ->on('language_meta.reference_id', '=', 'docs_pros.id')
                            ->where('language_meta.reference_type', '=', DocProduct::class);
                    })
                    ->where('language_meta.lang_meta_origin', $origin)
                    ->where('language_meta.lang_meta_code', $targetLanguageCode)
                    ->value('docs_pros.id')
            )
            ->first();
    }

    protected function resolveTranslatedDocument(DocProduct $targetProduct, Doc $activeDocument): ?Doc
    {
        $query = Doc::query()
            ->where('product_id', $targetProduct->getKey())
            ->where('node_type', Doc::NODE_TYPE_DOC)
            ->where('status', BaseStatusEnum::PUBLISHED);

        if ($activeDocument->source_path) {
            $translated = (clone $query)
                ->where('source_path', $activeDocument->source_path)
                ->first();

            if ($translated) {
                return $translated;
            }
        }

        if ($activeDocument->slug_path) {
            $translated = (clone $query)
                ->where('slug_path', $activeDocument->slug_path)
                ->first();

            if ($translated) {
                return $translated;
            }
        }

        return $this->resolveDefaultDocument(
            $query
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }
}
