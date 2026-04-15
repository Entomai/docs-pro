<?php

namespace Botble\DocsPro\Tables;

use Botble\Base\Facades\BaseHelper;
use Botble\DocsPro\Models\Doc;
use Botble\DocsPro\Models\DocProduct;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\Actions\DeleteAction;
use Botble\Table\Actions\EditAction;
use Botble\Table\BulkActions\DeleteBulkAction;
use Botble\Table\Columns\Column;
use Botble\Table\Columns\CreatedAtColumn;
use Botble\Table\Columns\FormattedColumn;
use Botble\Table\Columns\IdColumn;
use Botble\Table\Columns\StatusColumn;
use Botble\Table\Columns\YesNoColumn;
use Botble\Table\HeaderActions\CreateHeaderAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;

class DocTable extends TableAbstract
{
    protected ?DocProduct $product = null;

    public function setProduct(DocProduct $product): static
    {
        $this->product = $product;
        $this->removeHeaderActions()->addHeaderActions([
            CreateHeaderAction::make()->route('docs-pro.docs.create', [$product->getKey()]),
        ]);
        $this->removeAllActions()->addActions([
            EditAction::make()->route('docs-pro.docs.edit', [$product->getKey()]),
            DeleteAction::make()->route('docs-pro.docs.destroy', [$product->getKey()]),
        ]);

        return $this;
    }

    public function setup(): void
    {
        $this
            ->model(Doc::class);
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        if (! $this->product) {
            $query = Doc::query()->whereRaw('1 = 0');

            return $this->applyScopes($query);
        }

        $documents = Doc::query()
            ->where('product_id', $this->product->getKey())
            ->select([
                'id',
                'product_id',
                'parent_id',
                'name',
                'slug_path',
                'sort_order',
                'is_section',
                'is_default',
                'status',
                'created_at',
            ])
            ->with('parent:id,name,parent_id')
            ->get();

        $sortedIds = $this->sortHierarchically($documents)->pluck('id')->all();

        $query = Doc::query()
            ->where('product_id', $this->product->getKey())
            ->select([
                'id',
                'product_id',
                'parent_id',
                'name',
                'slug_path',
                'sort_order',
                'is_section',
                'is_default',
                'status',
                'created_at',
            ])
            ->with('parent:id,name,parent_id');

        if ($sortedIds !== []) {
            $query
                ->whereIn('id', $sortedIds)
                ->orderByRaw('FIELD(id, '.implode(',', $sortedIds).')');
        } else {
            $query->whereRaw('1 = 0');
        }

        return $this->applyScopes($query);
    }

    public function columns(): array
    {
        if (! $this->product) {
            return [];
        }

        return [
            IdColumn::make(),
            FormattedColumn::make('name')
                ->title(trans('core/base::tables.name'))
                ->alignStart()
                ->renderUsing(function (FormattedColumn $column): string {
                    /** @var Doc $document */
                    $document = $column->getItem();
                    $depth = substr_count($document->slug_path, '/');
                    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
                    $icon = $document->is_section
                        ? BaseHelper::renderIcon('ti ti-folder', 'me-1 text-warning')
                        : BaseHelper::renderIcon('ti ti-file-text', 'me-1 text-primary');

                    return sprintf(
                        '%s%s<a href="%s">%s</a>',
                        $indent,
                        $icon,
                        route('docs-pro.docs.edit', [$this->product->getKey(), $document->getKey()]),
                        e($document->name)
                    );
                }),
            Column::make('slug_path')
                ->title(trans('plugins/docs-pro::docs-pro.table_path'))
                ->alignStart(),
            YesNoColumn::make('is_default')->title(trans('plugins/docs-pro::docs-pro.table_default')),
            YesNoColumn::make('is_section')->title(trans('plugins/docs-pro::docs-pro.table_section')),
            CreatedAtColumn::make(),
            StatusColumn::make(),
        ];
    }

    public function bulkActions(): array
    {
        return [
            DeleteBulkAction::make()->permission('docs-pro.docs.destroy'),
        ];
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
}
