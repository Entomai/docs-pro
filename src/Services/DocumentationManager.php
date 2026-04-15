<?php

namespace Botble\DocsPro\Services;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Facades\BaseHelper;
use Botble\DocsPro\Models\Doc;
use Botble\DocsPro\Models\DocProduct;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ZipArchive;

class DocumentationManager
{
    public function saveProduct(array $attributes, ?DocProduct $product = null): DocProduct
    {
        $product ??= new DocProduct;

        $name = trim((string) Arr::get($attributes, 'name'));
        $slug = $this->makeUniqueProductSlug(
            trim((string) Arr::get($attributes, 'slug')) ?: $name,
            $product->getKey()
        );

        $product->fill([
            'name' => $name,
            'slug' => $slug,
            'menu_label' => $this->nullableString(Arr::get($attributes, 'menu_label')),
            'description' => $this->nullableString(Arr::get($attributes, 'description')),
            'sort_order' => max(0, (int) Arr::get($attributes, 'sort_order', $product->sort_order ?? 0)),
            'is_default' => (bool) Arr::get($attributes, 'is_default', false),
            'status' => Arr::get($attributes, 'status', BaseStatusEnum::PUBLISHED),
        ]);

        $product->save();

        if ($product->is_default) {
            DocProduct::query()
                ->whereKeyNot($product->getKey())
                ->update(['is_default' => false]);
        } elseif (! DocProduct::query()->where('is_default', true)->exists()) {
            $product->forceFill(['is_default' => true])->saveQuietly();
        }

        return $product->fresh();
    }

    public function saveDocument(DocProduct $product, array $attributes, ?Doc $document = null): Doc
    {
        $document ??= new Doc;

        $parent = array_key_exists('parent_id', $attributes)
            ? $this->resolveParentDocument($product, Arr::get($attributes, 'parent_id'), $document)
            : ($document->exists ? $document->parent : null);
        $name = trim((string) Arr::get($attributes, 'name'));
        $nodeType = Arr::get($attributes, 'node_type', $document->node_type ?: Doc::NODE_TYPE_DOC);
        $slug = $this->makeUniqueDocumentSlug(
            $product,
            $parent?->getKey(),
            trim((string) Arr::get($attributes, 'slug')) ?: $name,
            $document->getKey()
        );
        $markdown = array_key_exists('markdown_content', $attributes)
            ? $this->nullableString(Arr::get($attributes, 'markdown_content'))
            : $document->markdown_content;
        $menuTitle = array_key_exists('menu_title', $attributes)
            ? $this->nullableString(Arr::get($attributes, 'menu_title'))
            : $name;
        $content = array_key_exists('markdown_content', $attributes)
            ? ($markdown !== null
                ? $this->renderMarkdown($markdown)
                : ($document->exists && $document->markdown_content === null ? $document->content : null))
            : $this->nullableString(Arr::get($attributes, 'content', $document->content));
        $excerpt = array_key_exists('excerpt', $attributes)
            ? $this->nullableString(Arr::get($attributes, 'excerpt'))
            : $document->excerpt;

        if ($excerpt === null && $content) {
            $excerpt = $this->makeExcerpt($content);
        }

        $originalParentId = $document->parent_id;
        $originalSlugPath = $document->slug_path;
        $slugPath = $this->makeUniqueDocumentPath(
            $product->getKey(),
            $parent?->slug_path ? $parent->slug_path.'/'.$slug : $slug,
            $document->getKey()
        );

        $document->fill([
            'product_id' => $product->getKey(),
            'parent_id' => $parent?->getKey(),
            'name' => $name,
            'slug' => $slug,
            'slug_path' => $slugPath,
            'menu_title' => $menuTitle ?: $name,
            'excerpt' => $excerpt,
            'markdown_content' => $markdown,
            'content' => $content,
            'source_path' => $this->nullableString(Arr::get($attributes, 'source_path', $document->source_path)),
            'sort_order' => max(0, (int) Arr::get($attributes, 'sort_order', $document->sort_order ?? 0)),
            'node_type' => $nodeType,
            'is_section' => $nodeType === Doc::NODE_TYPE_DOC && (bool) Arr::get($attributes, 'is_section', $document->is_section ?? false),
            'is_default' => $nodeType === Doc::NODE_TYPE_DOC && (bool) Arr::get($attributes, 'is_default', $document->is_default ?? false),
            'status' => Arr::get($attributes, 'status', $document->status?->getValue() ?: BaseStatusEnum::PUBLISHED),
        ]);

        $document->save();

        $this->ensureStableSourcePath($document);

        if ($document->isDoc() && $document->is_default) {
            Doc::query()
                ->where('product_id', $product->getKey())
                ->where('node_type', Doc::NODE_TYPE_DOC)
                ->whereKeyNot($document->getKey())
                ->update(['is_default' => false]);
        }

        $this->ensureDefaultDocument($product);

        if (! $document->isDoc() && $document->is_default) {
            $document->forceFill(['is_default' => false])->saveQuietly();
        }

        if ($originalParentId !== $document->parent_id || $originalSlugPath !== $slugPath) {
            $this->syncDescendantPaths($document);
        }

        return $document->fresh(['parent', 'product']);
    }

    public function cloneTranslatedProductDocs(DocProduct $sourceProduct, DocProduct $targetProduct): void
    {
        if (
            $sourceProduct->getKey() === $targetProduct->getKey() ||
            $targetProduct->docs()->exists()
        ) {
            return;
        }

        DB::transaction(function () use ($sourceProduct, $targetProduct): void {
            $documents = Doc::query()
                ->where('product_id', $sourceProduct->getKey())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            if ($documents->isEmpty()) {
                return;
            }

            $childrenByParent = $documents->groupBy(fn (Doc $document): string => (string) ($document->parent_id ?: 0));

            $cloneNodes = function (int|string|null $sourceParentId = null, ?Doc $targetParent = null) use (&$cloneNodes, $childrenByParent, $targetProduct): void {
                $sourceChildren = $childrenByParent->get((string) ($sourceParentId ?: 0), collect())->values();

                foreach ($sourceChildren as $sourceDocument) {
                    $sharedSourcePath = $this->ensureStableSourcePath($sourceDocument);

                    $attributes = [
                        'parent_id' => $targetParent?->getKey(),
                        'name' => $sourceDocument->name,
                        'slug' => $sourceDocument->slug,
                        'menu_title' => $sourceDocument->menu_title,
                        'excerpt' => $sourceDocument->excerpt,
                        'source_path' => $sharedSourcePath,
                        'sort_order' => $sourceDocument->sort_order,
                        'node_type' => $sourceDocument->node_type,
                        'is_section' => $sourceDocument->is_section,
                        'is_default' => $sourceDocument->is_default,
                        'status' => $sourceDocument->status?->getValue() ?: BaseStatusEnum::PUBLISHED,
                    ];

                    if ($sourceDocument->markdown_content !== null) {
                        $attributes['markdown_content'] = $sourceDocument->markdown_content;
                    } elseif ($sourceDocument->content) {
                        $attributes['content'] = $sourceDocument->content;
                    }

                    $clonedDocument = $this->saveDocument($targetProduct, $attributes);

                    $cloneNodes($sourceDocument->getKey(), $clonedDocument);
                }
            };

            $cloneNodes();
            $this->ensureDefaultDocument($targetProduct);
            $this->cloneProductAssets($sourceProduct, $targetProduct);
        });
    }

    public function importArchive(DocProduct $product, UploadedFile $archive, bool $replaceExisting = true): array
    {
        $archiveData = $this->collectArchiveEntries($archive);
        $entries = $archiveData['entries'];
        $metadata = $archiveData['metadata'];
        $assetEntries = array_values(array_filter($entries, fn (array $entry): bool => $entry['type'] === 'asset'));

        if ($entries === []) {
            throw ValidationException::withMessages([
                'archive' => trans('plugins/docs-pro::docs-pro.import_error_empty_archive'),
            ]);
        }

        return DB::transaction(function () use ($archive, $entries, $metadata, $product, $replaceExisting, $assetEntries): array {
            $hasExistingDefault = ! $replaceExisting && Doc::query()
                ->where('product_id', $product->getKey())
                ->where('node_type', Doc::NODE_TYPE_DOC)
                ->where('is_default', true)
                ->exists();

            if ($replaceExisting) {
                $product->docs()->delete();
            }

            $this->storeImportedAssets($product, $assetEntries, $replaceExisting);

            $tree = is_array(Arr::get($metadata, 'tree'))
                ? $this->buildImportTreeFromMetadata($entries, $metadata)
                : $this->buildImportTree($entries);
            $tree = $this->normalizeImportedTree($tree);

            if ($tree === []) {
                throw ValidationException::withMessages([
                    'archive' => trans('plugins/docs-pro::docs-pro.import_error_empty_archive'),
                ]);
            }

            $existingDocuments = Doc::query()
                ->where('product_id', $product->getKey())
                ->get()
                ->filter(fn (Doc $document): bool => filled($document->source_path))
                ->keyBy(fn (Doc $document): string => (string) $document->source_path);

            $counts = [
                'pages' => 0,
                'titles' => 0,
                'separators' => 0,
            ];

            $firstImportedDoc = null;
            $preferredDefault = null;

            $persistNodes = function (array $nodes, ?Doc $parent = null) use (
                &$persistNodes,
                $product,
                &$existingDocuments,
                &$counts,
                &$firstImportedDoc,
                &$preferredDefault
            ): void {
                foreach (array_values($nodes) as $index => $node) {
                    $existingDocument = $existingDocuments->get($node['source_path']);

                    $attributes = [
                        'parent_id' => $parent?->getKey(),
                        'name' => $node['name'],
                        'slug' => $node['slug_seed'],
                        'menu_title' => $node['node_type'] === Doc::NODE_TYPE_SEPARATOR ? null : $node['name'],
                        'excerpt' => null,
                        'source_path' => $node['source_path'],
                        'sort_order' => $index,
                        'node_type' => $node['node_type'],
                        'status' => BaseStatusEnum::PUBLISHED,
                    ];

                    if ($node['node_type'] === Doc::NODE_TYPE_DOC) {
                        $attributes['markdown_content'] = $node['markdown_content'];
                        $attributes['status'] = $this->resolveImportedStatus($node['status'] ?? null);
                        $attributes['is_section'] = false;
                        $attributes['is_default'] = false;
                    } else {
                        $attributes['content'] = null;
                    }

                    $document = $this->saveDocument($product, $attributes, $existingDocument);

                    $existingDocuments->put((string) $document->source_path, $document);

                    if ($document->isDoc()) {
                        $counts['pages']++;
                        $firstImportedDoc ??= $document;

                        if (! empty($node['is_default'])) {
                            $preferredDefault = $document;
                        }
                    } elseif ($document->isTitle()) {
                        $counts['titles']++;
                    } else {
                        $counts['separators']++;
                    }

                    if ($node['children'] !== [] && ! $document->isSeparator()) {
                        $persistNodes($node['children'], $document);
                    }
                }
            };

            $persistNodes($tree);

            $preferredDefault ??= ! $hasExistingDefault || $replaceExisting
                ? $firstImportedDoc
                : null;

            if ($preferredDefault instanceof Doc) {
                $this->saveDocument($product, [
                    'parent_id' => $preferredDefault->parent_id,
                    'name' => $preferredDefault->name,
                    'slug' => $preferredDefault->slug,
                    'menu_title' => $preferredDefault->menu_title,
                    'excerpt' => $preferredDefault->excerpt,
                    'markdown_content' => $preferredDefault->markdown_content,
                    'source_path' => $preferredDefault->source_path,
                    'sort_order' => $preferredDefault->sort_order,
                    'node_type' => $preferredDefault->node_type,
                    'is_section' => $preferredDefault->is_section,
                    'is_default' => true,
                    'status' => $preferredDefault->status->getValue(),
                ], $preferredDefault);
            } else {
                $this->ensureDefaultDocument($product);
            }

            $product->forceFill([
                'source_archive_name' => $archive->getClientOriginalName(),
            ])->saveQuietly();

            return [
                'products' => 1,
                'sections' => $counts['titles'],
                'titles' => $counts['titles'],
                'separators' => $counts['separators'],
                'pages' => $counts['pages'],
            ];
        });
    }

    public function exportArchive(DocProduct $product): array
    {
        $documents = Doc::query()
            ->where('product_id', $product->getKey())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $childrenByParent = $documents->groupBy(fn (Doc $document): string => (string) ($document->parent_id ?: 0));
        $temporaryPath = tempnam(sys_get_temp_dir(), 'docs-pro-');

        if (! is_string($temporaryPath) || $temporaryPath === '') {
            throw new \RuntimeException('Unable to create a temporary export archive.');
        }

        $zip = new ZipArchive;
        $result = $zip->open($temporaryPath, ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new \RuntimeException('Unable to create the export ZIP archive.');
        }

        $manifest = [
            'format' => 'docs-pro-export',
            'version' => 1,
            'product' => [
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'default_doc' => null,
            'documents' => [],
            'tree' => [],
        ];

        $manifest['tree'] = $this->appendExportNodes($zip, $childrenByParent, null, '', $manifest);
        unset($manifest['exported_assets']);

        $zip->addFromString(
            '.docs-pro.json',
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $zip->close();

        return [
            'path' => $temporaryPath,
            'filename' => (Str::slug($product->slug ?: $product->name ?: 'docs') ?: 'docs').'-docs-export.zip',
        ];
    }

    public function renderDocumentContent(DocProduct $product, Doc $document): string
    {
        if ($document->markdown_content !== null) {
            return $this->replaceRelativeAssetUrls(
                $this->renderMarkdown($document->markdown_content),
                $product,
                $document
            );
        }

        return (string) $document->content;
    }

    public function assetResponse(DocProduct $product, string $path)
    {
        $normalizedPath = $this->normalizeRelativePath($path);

        if (! $normalizedPath) {
            throw new NotFoundHttpException;
        }

        $storagePath = $this->productAssetDirectory($product).'/'.$normalizedPath;

        if (! Storage::disk('local')->exists($storagePath)) {
            throw new NotFoundHttpException;
        }

        return response()->file(Storage::disk('local')->path($storagePath));
    }

    protected function resolveParentDocument(DocProduct $product, mixed $parentId, ?Doc $document = null): ?Doc
    {
        if (! $parentId) {
            return null;
        }

        $parent = Doc::query()
            ->where('product_id', $product->getKey())
            ->whereKey($parentId)
            ->first();

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => trans('plugins/docs-pro::docs-pro.parent_invalid'),
            ]);
        }

        // Removed title validation block to allow nesting
        if ($document && $document->exists) {
            if ((int) $parent->getKey() === (int) $document->getKey()) {
                throw ValidationException::withMessages([
                    'parent_id' => trans('plugins/docs-pro::docs-pro.parent_invalid'),
                ]);
            }

            if ($this->isDescendantOf($parent, $document)) {
                throw ValidationException::withMessages([
                    'parent_id' => trans('plugins/docs-pro::docs-pro.parent_cycle'),
                ]);
            }
        }

        return $parent;
    }

    protected function isDescendantOf(Doc $candidateParent, Doc $document): bool
    {
        $currentParentId = $candidateParent->parent_id;

        if ((int) $candidateParent->getKey() === (int) $document->getKey()) {
            return true;
        }

        while ($currentParentId) {
            if ((int) $currentParentId === (int) $document->getKey()) {
                return true;
            }

            $currentParentId = Doc::query()
                ->whereKey($currentParentId)
                ->value('parent_id');
        }

        return false;
    }

    protected function refreshDocumentPath(Doc $document): void
    {
        $document->loadMissing('parent');

        $parentPath = $document->parent?->slug_path;
        $basePath = $parentPath ? $parentPath.'/'.$document->slug : $document->slug;

        $document->forceFill([
            'slug_path' => $this->makeUniqueDocumentPath($document->product_id, $basePath, $document->getKey()),
        ])->saveQuietly();
    }

    protected function syncDescendantPaths(Doc $document): void
    {
        $children = Doc::query()
            ->where('parent_id', $document->getKey())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($children as $child) {
            $this->refreshDocumentPath($child);
            $this->syncDescendantPaths($child);
        }
    }

    protected function makeUniqueProductSlug(string $value, int|string|null $ignoreId = null): string
    {
        $baseSlug = Str::slug($value);

        if ($baseSlug === '') {
            $baseSlug = 'product';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            DocProduct::query()
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function makeUniqueDocumentSlug(DocProduct $product, int|string|null $parentId, string $value, int|string|null $ignoreId = null): string
    {
        $baseSlug = Str::slug($value);

        if ($baseSlug === '') {
            $baseSlug = 'doc';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            Doc::query()
                ->where('product_id', $product->getKey())
                ->where('parent_id', $parentId)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function makeUniqueDocumentPath(int|string $productId, string $path, int|string|null $ignoreId = null): string
    {
        $basePath = trim($path, '/');

        if ($basePath === '') {
            $basePath = 'doc';
        }

        $pathCandidate = $basePath;
        $counter = 2;

        while (
            Doc::query()
                ->where('product_id', $productId)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->where('slug_path', $pathCandidate)
                ->exists()
        ) {
            $segments = explode('/', $basePath);
            $lastSegment = array_pop($segments);
            $lastSegment = ($lastSegment ?: 'doc').'-'.$counter;
            $pathCandidate = $segments !== []
                ? implode('/', $segments).'/'.$lastSegment
                : $lastSegment;
            $counter++;
        }

        return $pathCandidate;
    }

    protected function collectArchiveEntries(UploadedFile $archive): array
    {
        $zip = new ZipArchive;
        $result = $zip->open($archive->getRealPath());

        if ($result !== true) {
            throw ValidationException::withMessages([
                'archive' => trans('plugins/docs-pro::docs-pro.import_error_invalid_zip'),
            ]);
        }

        $items = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);

            if (! is_string($entryName)) {
                continue;
            }

            $normalizedPath = $this->normalizeZipEntryPath($entryName);

            if (! $normalizedPath) {
                continue;
            }

            if ($this->isExportMetadataPath($normalizedPath)) {
                $contents = $zip->getFromIndex($index);

                if (is_string($contents)) {
                    $items[] = [
                        'path' => $normalizedPath,
                        'type' => 'metadata',
                        'content' => $contents,
                    ];
                }

                continue;
            }

            if ($this->shouldIgnoreZipPath($normalizedPath)) {
                continue;
            }

            $isDirectory = str_ends_with(str_replace('\\', '/', $entryName), '/');

            if ($isDirectory) {
                $items[] = [
                    'path' => $normalizedPath,
                    'type' => 'directory',
                ];

                continue;
            }

            $contents = $zip->getFromIndex($index);

            if (! is_string($contents)) {
                continue;
            }

            if (str_ends_with(strtolower($normalizedPath), '.md')) {
                $items[] = [
                    'path' => $normalizedPath,
                    'type' => 'markdown',
                    'content' => $contents,
                ];

                continue;
            }

            $items[] = [
                'path' => $normalizedPath,
                'type' => 'asset',
                'content' => $contents,
            ];
        }

        $zip->close();

        if ($items === []) {
            return [
                'entries' => [],
                'metadata' => [],
            ];
        }

        $items = $this->stripCommonArchiveRoot($items);
        $metadata = [];
        $entries = [];

        foreach ($items as $item) {
            if ($item['type'] === 'metadata') {
                if ($item['path'] === '.docs-pro.json') {
                    $metadata = $this->parseArchiveMetadata((string) $item['content']);
                }

                continue;
            }

            $item['directories'] = array_values(array_filter(explode('/', trim(dirname($item['path']), '.\\/'))));
            $item['filename'] = basename($item['path']);
            $entries[] = $item;
        }

        return [
            'entries' => $entries,
            'metadata' => $metadata,
        ];
    }

    protected function normalizeZipEntryPath(string $entryName): ?string
    {
        $path = trim(str_replace('\\', '/', $entryName), '/');

        if ($path === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), fn (string $segment): bool => $segment !== ''));

        return $segments === [] ? null : implode('/', $segments);
    }

    protected function shouldIgnoreZipPath(string $path): bool
    {
        $segments = explode('/', $path);

        if ($segments === []) {
            return true;
        }

        if (in_array($segments[0], ['__MACOSX', '.git'], true)) {
            return true;
        }

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return true;
            }

            if (str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }

    protected function isExportMetadataPath(string $path): bool
    {
        return strcasecmp(basename($path), '.docs-pro.json') === 0;
    }

    protected function parseArchiveMetadata(string $contents): array
    {
        $decoded = json_decode($contents, true);

        if (! is_array($decoded) || Arr::get($decoded, 'format') !== 'docs-pro-export') {
            return [];
        }

        return [
            'default_doc' => is_string(Arr::get($decoded, 'default_doc'))
                ? trim((string) Arr::get($decoded, 'default_doc'))
                : null,
            'documents' => is_array(Arr::get($decoded, 'documents'))
                ? Arr::get($decoded, 'documents')
                : [],
            'tree' => is_array(Arr::get($decoded, 'tree'))
                ? Arr::get($decoded, 'tree')
                : [],
        ];
    }

    protected function stripCommonArchiveRoot(array $items): array
    {
        $pathSegments = array_map(fn (array $item): array => explode('/', $item['path']), $items);
        $commonRoot = $pathSegments[0][0] ?? null;

        if (! $commonRoot) {
            return $items;
        }

        $hasNestedEntries = false;

        foreach ($pathSegments as $segments) {
            if (($segments[0] ?? null) !== $commonRoot) {
                return $items;
            }

            if (count($segments) >= 2) {
                $hasNestedEntries = true;
            }
        }

        if (! $hasNestedEntries) {
            return $items;
        }

        return array_values(array_filter(array_map(function (array $item): array {
            $segments = explode('/', $item['path']);
            array_shift($segments);
            $item['path'] = implode('/', $segments);

            return $item;
        }, $items), fn (array $item): bool => $item['path'] !== ''));
    }

    protected function buildImportTree(array $entries): array
    {
        return $this->buildImportNodesFromDirectory(
            $this->buildImportDirectoryTree($entries)
        );
    }

    protected function buildImportDirectoryTree(array $entries): array
    {
        $root = [
            'directories' => [],
            'files' => [],
        ];
        $encounter = 0;

        foreach ($entries as $entry) {
            if (! in_array($entry['type'], ['markdown', 'directory'], true)) {
                continue;
            }

            $segments = $entry['type'] === 'directory'
                ? array_values(array_filter(explode('/', trim((string) $entry['path'], '/'))))
                : $entry['directories'];
            $cursor = &$root;
            $pathSegments = [];

            foreach ($segments as $segment) {
                $pathSegments[] = $segment;
                $directoryPath = implode('/', $pathSegments);

                if (! isset($cursor['directories'][$segment])) {
                    $cursor['directories'][$segment] = [
                        'type' => 'directory',
                        'segment' => $segment,
                        'path' => $directoryPath,
                        'info' => $this->parseImportNodeLabel($segment),
                        'encounter' => $encounter++,
                        'directories' => [],
                        'files' => [],
                    ];
                }

                $cursor = &$cursor['directories'][$segment];
            }

            if ($entry['type'] !== 'markdown') {
                unset($cursor);

                continue;
            }

            $cursor['files'][] = [
                'type' => 'markdown',
                'path' => $entry['path'],
                'filename' => $entry['filename'],
                'content' => (string) $entry['content'],
                'info' => $this->parseImportNodeLabel(pathinfo($entry['filename'], PATHINFO_FILENAME)),
                'encounter' => $encounter++,
            ];

            unset($cursor);
        }

        return $root;
    }

    protected function buildImportNodesFromDirectory(array $directory, ?string $skipFilePath = null): array
    {
        $nodes = [];

        foreach ($this->sortImportDirectoryItems($directory) as $item) {
            if ($item['type'] === 'markdown') {
                if ($skipFilePath && $item['path'] === $skipFilePath) {
                    continue;
                }

                $nodes[] = $this->makeImportedFileNode($item);

                continue;
            }

            $info = $item['info'];

            if (! $this->directoryHasImportableContent($item)) {
                continue;
            }

            if ($info['is_separator']) {
                $nodes[] = [
                    'node_type' => Doc::NODE_TYPE_SEPARATOR,
                    'name' => $info['display_name'],
                    'slug_seed' => $info['slug_seed'],
                    'source_path' => 'zip-dir:'.$item['path'],
                    'import_path' => $item['path'],
                    'markdown_content' => null,
                    'status' => BaseStatusEnum::PUBLISHED,
                    'is_default' => false,
                    'children' => [],
                ];

                continue;
            }

            $representativeFile = $this->findDirectoryRepresentativeFile($item);

            if ($representativeFile) {
                $nodes[] = [
                    'node_type' => Doc::NODE_TYPE_DOC,
                    'name' => $representativeFile['info']['display_name'],
                    'slug_seed' => $representativeFile['info']['slug_seed'],
                    'source_path' => 'zip-file:'.$representativeFile['path'],
                    'import_path' => $representativeFile['path'],
                    'markdown_content' => (string) $representativeFile['content'],
                    'status' => BaseStatusEnum::PUBLISHED,
                    'is_default' => false,
                    'children' => $this->buildImportNodesFromDirectory($item, $representativeFile['path']),
                ];

                continue;
            }

            $nodes[] = [
                'node_type' => Doc::NODE_TYPE_TITLE,
                'name' => $info['display_name'],
                'slug_seed' => $info['slug_seed'],
                'source_path' => 'zip-dir:'.$item['path'],
                'import_path' => $item['path'],
                'markdown_content' => null,
                'status' => BaseStatusEnum::PUBLISHED,
                'is_default' => false,
                'children' => [],
            ];

            $nodes = array_merge($nodes, $this->buildImportNodesFromDirectory($item));
        }

        return $nodes;
    }

    protected function directoryHasImportableContent(array $directory): bool
    {
        if (($directory['info']['is_separator'] ?? false) === true) {
            return true;
        }

        if (($directory['files'] ?? []) !== []) {
            return true;
        }

        foreach ($directory['directories'] ?? [] as $childDirectory) {
            if ($this->directoryHasImportableContent($childDirectory)) {
                return true;
            }
        }

        return false;
    }

    protected function sortImportDirectoryItems(array $directory): array
    {
        $items = [
            ...array_values($directory['directories'] ?? []),
            ...array_values($directory['files'] ?? []),
        ];

        usort($items, function (array $first, array $second): int {
            $firstInfo = $first['info'];
            $secondInfo = $second['info'];
            $firstHasOrder = $firstInfo['order'] !== null;
            $secondHasOrder = $secondInfo['order'] !== null;

            if ($firstHasOrder && $secondHasOrder && $firstInfo['order'] !== $secondInfo['order']) {
                return $firstInfo['order'] <=> $secondInfo['order'];
            }

            if ($firstHasOrder !== $secondHasOrder) {
                return $firstHasOrder ? -1 : 1;
            }

            return $first['encounter'] <=> $second['encounter'];
        });

        return $items;
    }

    protected function findDirectoryRepresentativeFile(array $directory): ?array
    {
        $directoryName = Str::lower((string) Arr::get($directory, 'info.clean_name'));

        if ($directoryName === '' || $directoryName === '_') {
            return null;
        }

        foreach ($this->sortImportDirectoryItems($directory) as $item) {
            if ($item['type'] !== 'markdown' || $item['info']['is_separator']) {
                continue;
            }

            if (Str::lower((string) $item['info']['clean_name']) === $directoryName) {
                return $item;
            }
        }

        return null;
    }

    protected function makeImportedFileNode(array $file): array
    {
        return [
            'node_type' => $file['info']['is_separator'] ? Doc::NODE_TYPE_SEPARATOR : Doc::NODE_TYPE_DOC,
            'name' => $file['info']['display_name'],
            'slug_seed' => $file['info']['slug_seed'],
            'source_path' => 'zip-file:'.$file['path'],
            'import_path' => $file['path'],
            'markdown_content' => $file['info']['is_separator'] ? null : (string) $file['content'],
            'status' => BaseStatusEnum::PUBLISHED,
            'is_default' => false,
            'children' => [],
        ];
    }

    protected function buildImportTreeFromMetadata(array $entries, array $metadata): array
    {
        $markdownByPath = collect($entries)
            ->where('type', 'markdown')
            ->keyBy('path');

        $buildNodes = function (array $items) use (&$buildNodes, $markdownByPath): array {
            $normalized = [];

            foreach ($items as $item) {
                $nodeType = Arr::get($item, 'node_type', Doc::NODE_TYPE_DOC);

                if (! in_array($nodeType, [Doc::NODE_TYPE_DOC, Doc::NODE_TYPE_TITLE, Doc::NODE_TYPE_SEPARATOR], true)) {
                    continue;
                }

                $nodePath = trim((string) Arr::get($item, 'path'), '/');
                $contentPath = trim((string) Arr::get($item, 'content_path', $nodePath), '/');
                $storage = Arr::get($item, 'storage') === 'directory' ? 'directory' : 'file';
                $name = trim((string) Arr::get($item, 'name'));

                if ($name === '') {
                    $name = match ($nodeType) {
                        Doc::NODE_TYPE_TITLE => 'Title',
                        Doc::NODE_TYPE_SEPARATOR => trans('plugins/docs-pro::docs-pro.node_type_separator'),
                        default => 'Document',
                    };
                }

                $entry = $markdownByPath->get($contentPath, []);

                $normalized[] = [
                    'node_type' => $nodeType,
                    'name' => $name,
                    'slug_seed' => trim((string) Arr::get($item, 'slug')) ?: $name,
                    'source_path' => ($storage === 'directory' ? 'zip-dir:' : 'zip-file:').($storage === 'directory' ? $nodePath : $contentPath),
                    'import_path' => $nodePath !== '' ? $nodePath : $contentPath,
                    'markdown_content' => $nodeType === Doc::NODE_TYPE_DOC
                        ? (string) Arr::get($entry, 'content', '')
                        : null,
                    'status' => $nodeType === Doc::NODE_TYPE_DOC
                        ? $this->resolveImportedStatus(Arr::get($item, 'status'))
                        : BaseStatusEnum::PUBLISHED,
                    'is_default' => $nodeType === Doc::NODE_TYPE_DOC && (bool) Arr::get($item, 'is_default', false),
                    'children' => $buildNodes((array) Arr::get($item, 'children', [])),
                ];
            }

            return $normalized;
        };

        return $buildNodes((array) Arr::get($metadata, 'tree', []));
    }

    protected function registerImportTreeNode(
        array &$nodes,
        array &$rootKeys,
        ?string $parentKey,
        string $nodeKey,
        array $payload
    ): void {
        $nodes[$nodeKey] = $payload + ['children_keys' => []];

        if ($parentKey === null) {
            $rootKeys[] = $nodeKey;

            return;
        }

        $nodes[$parentKey]['children_keys'][] = $nodeKey;
    }

    protected function normalizeImportTree(array $nodes, array $keys): array
    {
        $normalized = [];

        foreach ($this->sortImportNodeKeys($keys, $nodes) as $key) {
            $node = $nodes[$key];
            $normalized[] = [
                'node_type' => $node['node_type'],
                'name' => $node['name'],
                'slug_seed' => $node['slug_seed'],
                'source_path' => $node['source_path'],
                'import_path' => $node['import_path'],
                'markdown_content' => $node['markdown_content'],
                'status' => $node['status'],
                'is_default' => $node['is_default'],
                'children' => $this->normalizeImportTree($nodes, $node['children_keys']),
            ];
        }

        return $normalized;
    }

    protected function sortImportNodeKeys(array $keys, array $nodes): array
    {
        usort($keys, function (string $firstKey, string $secondKey) use ($nodes): int {
            $first = $nodes[$firstKey];
            $second = $nodes[$secondKey];

            $firstHasOrder = $first['order_number'] !== null;
            $secondHasOrder = $second['order_number'] !== null;

            if ($firstHasOrder && $secondHasOrder && $first['order_number'] !== $second['order_number']) {
                return $first['order_number'] <=> $second['order_number'];
            }

            if ($firstHasOrder !== $secondHasOrder) {
                return $firstHasOrder ? -1 : 1;
            }

            return $first['encounter'] <=> $second['encounter'];
        });

        return $keys;
    }

    protected function parseImportNodeLabel(string $value): array
    {
        $raw = trim($value);
        $order = null;
        $cleanName = $raw;

        if (preg_match('/^(\d+)(.*)$/u', $raw, $matches)) {
            $rest = ltrim((string) $matches[2]);
            $rest = preg_replace('/^[\s.-]+/u', '', $rest) ?? $rest;

            if ($rest !== '_' && str_starts_with($rest, '_')) {
                $rest = ltrim($rest, '_');
            }

            if ($rest !== '') {
                $order = (int) $matches[1];
                $cleanName = $rest;
            }
        }

        $cleanName = trim($cleanName);

        if ($cleanName === '') {
            $cleanName = $raw !== '' ? $raw : 'item';
        }

        $isSeparator = $cleanName === '_';

        return [
            'order' => $order,
            'clean_name' => $cleanName,
            'display_name' => $isSeparator
                ? trans('plugins/docs-pro::docs-pro.node_type_separator')
                : $this->formatImportDisplayName($cleanName),
            'slug_seed' => $isSeparator ? 'separator' : $cleanName,
            'is_separator' => $isSeparator,
        ];
    }

    protected function formatImportDisplayName(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return 'Untitled';
        }

        $spacedValue = preg_replace('/[_-]+/u', ' ', $value) ?? $value;
        $spacedValue = trim(preg_replace('/\s+/u', ' ', $spacedValue) ?? $spacedValue);

        if ($spacedValue === '') {
            return 'Untitled';
        }

        if (preg_match('/[A-Z]/u', $value) || str_contains($value, ' ')) {
            return $spacedValue;
        }

        return Str::headline($spacedValue);
    }

    protected function resolveImportedStatus(mixed $value): string
    {
        return is_string($value) && BaseStatusEnum::isValid($value)
            ? $value
            : BaseStatusEnum::PUBLISHED;
    }

    protected function appendExportNodes(
        ZipArchive $zip,
        \Illuminate\Support\Collection $childrenByParent,
        int|string|null $parentId,
        string $basePath,
        array &$manifest
    ): array {
        $children = $childrenByParent->get((string) ($parentId ?: 0), collect())->values();

        if ($children->isEmpty()) {
            return [];
        }

        $padding = max(2, strlen((string) $children->count()));
        $tree = [];
        $exportedAssets = Arr::get($manifest, 'exported_assets', []);

        foreach ($children as $index => $document) {
            $prefix = str_pad((string) ($index + 1), $padding, '0', STR_PAD_LEFT);

            if ($document->isSeparator()) {
                $relativePath = $this->joinExportPath($basePath, $prefix.'-_.md');
                $zip->addFromString($relativePath, '');
                $tree[] = [
                    'node_type' => Doc::NODE_TYPE_SEPARATOR,
                    'name' => $document->name,
                    'slug' => $document->slug,
                    'path' => $relativePath,
                    'content_path' => $relativePath,
                    'storage' => 'file',
                    'status' => BaseStatusEnum::PUBLISHED,
                    'is_default' => false,
                    'children' => [],
                ];

                continue;
            }

            $segmentName = $this->makeExportPathSegment(
                $document->name ?: $document->slug,
                $document->isTitle() ? 'Title' : 'Doc'
            );
            $relativeBasePath = $this->joinExportPath($basePath, $prefix.'-'.$segmentName);
            $childTree = [];

            if ($document->isTitle()) {
                $zip->addEmptyDir($relativeBasePath);
                $childTree = $this->appendExportNodes($zip, $childrenByParent, $document->getKey(), $relativeBasePath, $manifest);
                $tree[] = [
                    'node_type' => Doc::NODE_TYPE_TITLE,
                    'name' => $document->name,
                    'slug' => $document->slug,
                    'path' => $relativeBasePath,
                    'content_path' => null,
                    'storage' => 'directory',
                    'status' => BaseStatusEnum::PUBLISHED,
                    'is_default' => false,
                    'children' => $childTree,
                ];

                continue;
            }

            $hasChildren = $childrenByParent->has((string) $document->getKey())
                && $childrenByParent->get((string) $document->getKey(), collect())->isNotEmpty();
            $contentPath = $hasChildren
                ? $this->joinExportPath(
                    $relativeBasePath,
                    '00-'.$this->makeExportPathSegment($document->name ?: $document->slug, 'Doc').'.md'
                )
                : $relativeBasePath.'.md';

            if ($hasChildren) {
                $zip->addEmptyDir($relativeBasePath);
            }

            $markdown = $this->exportDocumentMarkdown($document);
            $this->appendReferencedAssetsToExport($zip, $document, $contentPath, $markdown, $exportedAssets);
            $zip->addFromString($contentPath, $markdown);

            if ($hasChildren) {
                $childTree = $this->appendExportNodes($zip, $childrenByParent, $document->getKey(), $relativeBasePath, $manifest);
            }

            $manifest['documents'][$contentPath] = [
                'status' => $document->status?->getValue() ?: BaseStatusEnum::PUBLISHED,
            ];

            if ($document->is_default) {
                $manifest['default_doc'] = $contentPath;
            }

            $tree[] = [
                'node_type' => Doc::NODE_TYPE_DOC,
                'name' => $document->name,
                'slug' => $document->slug,
                'path' => $hasChildren ? $relativeBasePath : $contentPath,
                'content_path' => $contentPath,
                'storage' => $hasChildren ? 'directory' : 'file',
                'status' => $document->status?->getValue() ?: BaseStatusEnum::PUBLISHED,
                'is_default' => (bool) $document->is_default,
                'children' => $childTree,
            ];
        }

        $manifest['exported_assets'] = $exportedAssets;

        return $tree;
    }

    protected function joinExportPath(string $basePath, string $segment): string
    {
        return trim($basePath !== '' ? $basePath.'/'.$segment : $segment, '/');
    }

    protected function makeExportPathSegment(?string $value, string $fallback): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/[\\\\\/:*?"<>|]+/u', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value, " .\t\n\r\0\x0B");

        return $value !== '' ? $value : $fallback;
    }

    protected function exportDocumentMarkdown(Doc $document): string
    {
        $markdown = $this->nullableString($document->markdown_content);

        if ($markdown !== null) {
            return $markdown;
        }

        $content = (string) $document->content;
        $content = preg_replace('/<\s*br\s*\/?>/i', "\n", $content) ?? $content;
        $plainText = trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5));
        $plainText = preg_replace('/\n{3,}/', "\n\n", $plainText) ?? $plainText;

        $heading = '# '.($document->name ?: 'Document');

        return $plainText !== ''
            ? $heading."\n\n".$plainText."\n"
            : $heading."\n";
    }

    protected function appendReferencedAssetsToExport(
        ZipArchive $zip,
        Doc $document,
        string $contentPath,
        string $markdown,
        array &$exportedAssets
    ): void {
        foreach ($this->extractRelativeAssetReferences($markdown) as $reference) {
            [$referencePath] = $this->splitReferencePath($reference);
            $storagePath = $this->resolveDocumentAssetStoragePath($document->product, $document, $referencePath);

            if (! $storagePath || ! Storage::disk('local')->exists($storagePath)) {
                continue;
            }

            $targetPath = $this->normalizeRelativePath(dirname($contentPath).'/'.$referencePath);

            if (! $targetPath || isset($exportedAssets[$targetPath])) {
                continue;
            }

            $contents = Storage::disk('local')->get($storagePath);
            $zip->addFromString($targetPath, $contents);
            $exportedAssets[$targetPath] = true;
        }
    }

    protected function extractRelativeAssetReferences(string $markdown): array
    {
        $references = [];

        preg_match_all('/!\[[^\]]*]\(([^)]+)\)/u', $markdown, $markdownMatches);
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/iu', $markdown, $htmlMatches);

        foreach (array_merge($markdownMatches[1] ?? [], $htmlMatches[1] ?? []) as $value) {
            $reference = trim((string) $value);

            if (! $this->isRelativeAssetReference($reference)) {
                continue;
            }

            $references[] = $reference;
        }

        return array_values(array_unique($references));
    }

    protected function storeImportedAssets(DocProduct $product, array $assetEntries, bool $replaceExisting): void
    {
        $disk = Storage::disk('local');
        $directory = $this->productAssetDirectory($product);

        if ($replaceExisting && $disk->exists($directory)) {
            $disk->deleteDirectory($directory);
        }

        foreach ($assetEntries as $asset) {
            $normalizedPath = $this->normalizeRelativePath((string) Arr::get($asset, 'path'));

            if (! $normalizedPath) {
                continue;
            }

            $disk->put($directory.'/'.$normalizedPath, (string) Arr::get($asset, 'content', ''));
        }
    }

    protected function cloneProductAssets(DocProduct $sourceProduct, DocProduct $targetProduct): void
    {
        $disk = Storage::disk('local');
        $sourceDirectory = $this->productAssetDirectory($sourceProduct);
        $targetDirectory = $this->productAssetDirectory($targetProduct);

        if (! $disk->exists($sourceDirectory)) {
            return;
        }

        $disk->deleteDirectory($targetDirectory);

        foreach ($disk->allFiles($sourceDirectory) as $file) {
            $relativePath = Str::after($file, $sourceDirectory.'/');
            $disk->put($targetDirectory.'/'.$relativePath, $disk->get($file));
        }
    }

    protected function replaceRelativeAssetUrls(string $html, DocProduct $product, Doc $document): string
    {
        return preg_replace_callback(
            '/\b(src|href)=([\'"])([^\'"]+)\2/i',
            function (array $matches) use ($product, $document): string {
                $resolvedUrl = $this->resolveDocumentAssetUrl($product, $document, $matches[3]);

                if (! $resolvedUrl) {
                    return $matches[0];
                }

                return sprintf('%s=%s%s%s', $matches[1], $matches[2], $resolvedUrl, $matches[2]);
            },
            $html
        ) ?? $html;
    }

    protected function resolveDocumentAssetUrl(DocProduct $product, Doc $document, string $reference): ?string
    {
        if (! $this->isRelativeAssetReference($reference)) {
            return null;
        }

        [$path, $suffix] = $this->splitReferencePath($reference);
        $storagePath = $this->resolveDocumentAssetStoragePath($product, $document, $path);

        if (! $storagePath || ! Storage::disk('local')->exists($storagePath)) {
            return null;
        }

        return route('public.docs.asset', [
            'productSlug' => $product->slug,
            'path' => Str::after($storagePath, $this->productAssetDirectory($product).'/'),
        ]).$suffix;
    }

    protected function resolveDocumentAssetStoragePath(DocProduct $product, Doc $document, string $reference): ?string
    {
        [$path] = $this->splitReferencePath($reference);
        $documentDirectory = $this->documentImportDirectory($document);
        $resolvedPath = $this->normalizeRelativePath(
            $documentDirectory !== '' ? $documentDirectory.'/'.$path : $path
        );

        if (! $resolvedPath) {
            return null;
        }

        return $this->productAssetDirectory($product).'/'.$resolvedPath;
    }

    protected function documentImportDirectory(Doc $document): string
    {
        $sourcePath = (string) $document->source_path;

        if (str_starts_with($sourcePath, 'zip-file:')) {
            $relativePath = Str::after($sourcePath, 'zip-file:');

            return trim(dirname($relativePath), '.\\/');
        }

        if (str_starts_with($sourcePath, 'zip-dir:')) {
            return trim(Str::after($sourcePath, 'zip-dir:'), '/');
        }

        return '';
    }

    protected function isRelativeAssetReference(string $reference): bool
    {
        [$path] = $this->splitReferencePath($reference);
        $path = trim($path);

        if ($path === '') {
            return false;
        }

        if (
            str_starts_with($path, '#') ||
            str_starts_with($path, '/') ||
            str_starts_with($path, '//') ||
            preg_match('/^[a-z][a-z0-9+\-.]*:/i', $path)
        ) {
            return false;
        }

        return true;
    }

    protected function splitReferencePath(string $reference): array
    {
        if (! preg_match('/^([^?#]+)(.*)$/u', $reference, $matches)) {
            return [$reference, ''];
        }

        return [
            trim((string) $matches[1]),
            (string) ($matches[2] ?? ''),
        ];
    }

    protected function productAssetDirectory(DocProduct $product): string
    {
        return 'docs-pro/assets/products/'.$product->getKey();
    }

    protected function normalizeRelativePath(string $path): ?string
    {
        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            $segment = trim($segment);

            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return $segments !== [] ? implode('/', $segments) : null;
    }

    protected function getSiblingOrders(DocProduct $product): array
    {
        return Doc::query()
            ->where('product_id', $product->getKey())
            ->get(['parent_id', 'sort_order'])
            ->groupBy(fn (Doc $doc): string => (string) ($doc->parent_id ?: 0))
            ->map(fn ($items): int => (int) $items->max('sort_order'))
            ->all();
    }

    protected function nextSiblingOrder(int|string|null $parentId, array &$siblingOrders): int
    {
        $key = (string) ($parentId ?: 0);
        $current = $siblingOrders[$key] ?? -1;
        $next = $current + 1;
        $siblingOrders[$key] = $next;

        return $next;
    }

    protected function renderMarkdown(string $markdown): string
    {
        return BaseHelper::clean(Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }

    public function previewMarkdown(string $markdown): string
    {
        return $this->renderMarkdown($markdown);
    }

    public function syncDocumentTree(DocProduct $product, array $tree): void
    {
        $documentsById = Doc::query()
            ->where('product_id', $product->getKey())
            ->get()
            ->keyBy(fn (Doc $document): string => (string) $document->getKey());

        DB::transaction(function () use ($product, $tree, $documentsById): void {
            $this->applyTreeNodeOrder($product, $this->normalizeEditorTree($tree, $documentsById), null);
            $this->ensureDefaultDocument($product);
        });
    }

    public function saveEditorSnapshot(
        DocProduct $product,
        array $tree,
        array $nodes,
        int|string|null $selectedNodeId = null
    ): array {
        return DB::transaction(function () use ($product, $tree, $nodes, $selectedNodeId): array {
            $nodesById = collect($nodes)->keyBy(fn (array $node): string => (string) Arr::get($node, 'id'));
            $tree = $this->normalizeEditorTree($tree, $nodesById);
            $existingDocuments = Doc::query()
                ->where('product_id', $product->getKey())
                ->get()
                ->keyBy(fn (Doc $document): string => (string) $document->getKey());

            $persistedIds = [];
            $idMap = [];

            $persistNodes = function (array $items, ?Doc $parent = null) use (
                &$persistNodes,
                $existingDocuments,
                $nodesById,
                $product,
                &$persistedIds,
                &$idMap
            ): void {
                foreach (array_values($items) as $index => $item) {
                    $sourceId = (string) Arr::get($item, 'id');

                    if ($sourceId === '') {
                        continue;
                    }

                    $payload = (array) $nodesById->get($sourceId, []);
                    $nodeType = Arr::get($payload, 'node_type', Doc::NODE_TYPE_DOC);

                    if (! in_array($nodeType, [Doc::NODE_TYPE_DOC, Doc::NODE_TYPE_TITLE, Doc::NODE_TYPE_SEPARATOR], true)) {
                        $nodeType = Doc::NODE_TYPE_DOC;
                    }

                    $document = $this->saveDocument(
                        $product,
                        [
                            'parent_id' => $parent?->getKey(),
                            'name' => $this->resolveEditorNodeName($payload, $nodeType),
                            'node_type' => $nodeType,
                            'excerpt' => $nodeType === Doc::NODE_TYPE_DOC ? $this->nullableString(Arr::get($payload, 'excerpt')) : null,
                            'markdown_content' => $nodeType === Doc::NODE_TYPE_DOC ? $this->nullableString(Arr::get($payload, 'markdown_content')) : null,
                            'sort_order' => $index,
                            'is_section' => $nodeType === Doc::NODE_TYPE_DOC && (bool) Arr::get($payload, 'is_section', false),
                            'is_default' => $nodeType === Doc::NODE_TYPE_DOC && (bool) Arr::get($payload, 'is_default', false),
                            'status' => $nodeType === Doc::NODE_TYPE_DOC
                                ? Arr::get($payload, 'status', BaseStatusEnum::DRAFT)
                                : BaseStatusEnum::PUBLISHED,
                        ],
                        $existingDocuments->get($sourceId)
                    );

                    $persistedIds[] = (int) $document->getKey();
                    $idMap[$sourceId] = (int) $document->getKey();

                    $persistNodes((array) Arr::get($item, 'children', []), $document);
                }
            };

            $persistNodes($tree);

            if ($persistedIds === []) {
                $product->docs()->delete();
            } else {
                Doc::query()
                    ->where('product_id', $product->getKey())
                    ->whereNotIn('id', $persistedIds)
                    ->delete();
            }

            $this->ensureDefaultDocument($product);

            $documents = Doc::query()
                ->where('product_id', $product->getKey())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $selectedDocument = null;

            if ($selectedNodeId !== null) {
                $selectedPersistedId = $idMap[(string) $selectedNodeId] ?? (is_numeric($selectedNodeId) ? (int) $selectedNodeId : null);

                if ($selectedPersistedId) {
                    $selectedDocument = $documents->firstWhere('id', $selectedPersistedId);
                }
            }

            if ($selectedDocument?->isSeparator()) {
                $selectedDocument = null;
            }

            $selectedDocument ??= $documents->firstWhere('is_default', true)
                ?: $documents->first(fn (Doc $document): bool => $document->isDoc())
                ?: $documents->first(fn (Doc $document): bool => $document->isTitle());

            return [
                'documents' => $documents,
                'selectedDocument' => $selectedDocument,
            ];
        });
    }

    protected function normalizeImportedTree(array $nodes): array
    {
        $normalized = [];

        foreach ($nodes as $node) {
            $nodeType = Arr::get($node, 'node_type', Doc::NODE_TYPE_DOC);
            $children = $this->normalizeImportedTree((array) Arr::get($node, 'children', []));
            $node['children'] = in_array($nodeType, [Doc::NODE_TYPE_DOC, Doc::NODE_TYPE_TITLE]) ? $children : [];
            $normalized[] = $node;
        }

        return $normalized;
    }

    protected function normalizeEditorTree(array $tree, \Illuminate\Support\Collection $nodesById): array
    {
        $normalized = [];

        foreach ($tree as $item) {
            $id = (string) Arr::get($item, 'id');

            if ($id === '') {
                continue;
            }

            $nodeType = Arr::get((array) $nodesById->get($id, []), 'node_type', Doc::NODE_TYPE_DOC);
            $children = $this->normalizeEditorTree((array) Arr::get($item, 'children', []), $nodesById);
            $normalized[] = [
                'id' => $id,
                'children' => in_array($nodeType, [Doc::NODE_TYPE_DOC, Doc::NODE_TYPE_TITLE]) ? $children : [],
            ];
        }

        return $normalized;
    }

    protected function makeExcerpt(string $html): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));

        return $text !== '' ? Str::limit($text, 220) : null;
    }

    protected function applyTreeNodeOrder(DocProduct $product, array $nodes, int|string|null $parentId): void
    {
        foreach (array_values($nodes) as $index => $node) {
            $documentId = Arr::get($node, 'id');

            if (! $documentId) {
                continue;
            }

            $document = Doc::query()
                ->where('product_id', $product->getKey())
                ->whereKey($documentId)
                ->first();

            if (! $document) {
                continue;
            }

            $document->forceFill([
                'parent_id' => $parentId,
                'sort_order' => $index,
            ])->saveQuietly();

            $this->refreshDocumentPath($document);

            $this->applyTreeNodeOrder($product, Arr::get($node, 'children', []), $document->getKey());
        }
    }

    protected function ensureDefaultDocument(DocProduct $product): void
    {
        $defaultDocumentQuery = Doc::query()
            ->where('product_id', $product->getKey())
            ->where('node_type', Doc::NODE_TYPE_DOC);

        if ($defaultDocumentQuery->where('is_default', true)->exists()) {
            return;
        }

        $fallbackDocument = Doc::query()
            ->where('product_id', $product->getKey())
            ->where('node_type', Doc::NODE_TYPE_DOC)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();

        if ($fallbackDocument) {
            $fallbackDocument->forceFill(['is_default' => true])->saveQuietly();
        }
    }

    protected function ensureStableSourcePath(Doc $document): string
    {
        if (filled($document->source_path)) {
            return (string) $document->source_path;
        }

        $sourcePath = sprintf('manual-%s:%s', $document->node_type ?: Doc::NODE_TYPE_DOC, $document->getKey());

        $document->forceFill([
            'source_path' => $sourcePath,
        ])->saveQuietly();

        return $sourcePath;
    }

    protected function resolveEditorNodeName(array $payload, string $nodeType): string
    {
        $name = trim((string) Arr::get($payload, 'name'));

        if ($name !== '') {
            return $name;
        }

        return match ($nodeType) {
            Doc::NODE_TYPE_TITLE => 'New title',
            Doc::NODE_TYPE_SEPARATOR => 'New separator',
            default => 'New doc',
        };
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' || $value === null ? null : (string) $value;
    }
}
