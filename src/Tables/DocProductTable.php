<?php

namespace Botble\DocsPro\Tables;

use Botble\DocsPro\Models\DocProduct;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\Actions\DeleteAction;
use Botble\Table\Actions\EditAction;
use Botble\Table\BulkActions\DeleteBulkAction;
use Botble\Table\Columns\CreatedAtColumn;
use Botble\Table\Columns\FormattedColumn;
use Botble\Table\Columns\IdColumn;
use Botble\Table\Columns\NameColumn;
use Botble\Table\Columns\StatusColumn;
use Botble\Table\Columns\YesNoColumn;
use Botble\Table\HeaderActions\CreateHeaderAction;
use Illuminate\Database\Eloquent\Builder;

class DocProductTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(DocProduct::class)
            ->addHeaderAction(CreateHeaderAction::make()->route('docs-pro.products.create'))
            ->addActions([
                EditAction::make()->route('docs-pro.products.edit'),
                DeleteAction::make()->route('docs-pro.products.destroy'),
            ])
            ->addColumns([
                IdColumn::make(),
                NameColumn::make()->route('docs-pro.products.edit'),
                FormattedColumn::make('slug')
                    ->title(trans('plugins/docs-pro::docs-pro.table_portal'))
                    ->renderUsing(function (FormattedColumn $column): string {
                        /** @var DocProduct $product */
                        $product = $column->getItem();

                        $portalUrl = route('public.docs.show', ['productSlug' => $product->slug]);
                        $manageDocsUrl = route('docs-pro.docs.index', $product);
                        $importUrl = route('docs-pro.import.create', $product);
                        $exportUrl = route('docs-pro.import.export', $product);

                        return sprintf(
                            '<div class="d-flex flex-column gap-1"><a href="%s" target="_blank">%s</a><div class="small text-muted"><a href="%s">%s</a> &middot; <a href="%s">%s</a> &middot; <a href="%s">%s</a></div></div>',
                            e($portalUrl),
                            e($portalUrl),
                            e($manageDocsUrl),
                            e(trans('plugins/docs-pro::docs-pro.manage_docs')),
                            e($importUrl),
                            e(trans('plugins/docs-pro::docs-pro.import_title')),
                            e($exportUrl),
                            e(trans('plugins/docs-pro::docs-pro.export_title'))
                        );
                    }),
                FormattedColumn::make('docs_count')
                    ->title(trans('plugins/docs-pro::docs-pro.table_docs'))
                    ->renderUsing(fn (FormattedColumn $column): string => (string) $column->getItem()->docs_count),
                YesNoColumn::make('is_default')->title(trans('plugins/docs-pro::docs-pro.table_default')),
                CreatedAtColumn::make(),
                StatusColumn::make(),
            ])
            ->addBulkActions([
                DeleteBulkAction::make()->permission('docs-pro.products.destroy'),
            ])
            ->queryUsing(function (Builder $query): void {
                $query
                    ->select([
                        'id',
                        'name',
                        'slug',
                        'is_default',
                        'status',
                        'created_at',
                    ])
                    ->withCount('docs')
                    ->orderByDesc('is_default')
                    ->orderBy('sort_order')
                    ->orderBy('name');
            });
    }
}
