<?php

namespace Botble\DocsPro\Forms;

use Botble\Base\Forms\FieldOptions\EditorFieldOption;
use Botble\Base\Forms\FieldOptions\HtmlFieldOption;
use Botble\Base\Forms\FieldOptions\NameFieldOption;
use Botble\Base\Forms\FieldOptions\NumberFieldOption;
use Botble\Base\Forms\FieldOptions\OnOffFieldOption;
use Botble\Base\Forms\FieldOptions\SelectFieldOption;
use Botble\Base\Forms\FieldOptions\StatusFieldOption;
use Botble\Base\Forms\FieldOptions\TextareaFieldOption;
use Botble\Base\Forms\FieldOptions\TextFieldOption;
use Botble\Base\Forms\Fields\EditorField;
use Botble\Base\Forms\Fields\HtmlField;
use Botble\Base\Forms\Fields\NumberField;
use Botble\Base\Forms\Fields\OnOffField;
use Botble\Base\Forms\Fields\SelectField;
use Botble\Base\Forms\Fields\TextareaField;
use Botble\Base\Forms\Fields\TextField;
use Botble\Base\Forms\FormAbstract;
use Botble\DocsPro\Http\Requests\DocRequest;
use Botble\DocsPro\Models\Doc;
use Botble\DocsPro\Models\DocProduct;
use Illuminate\Support\Str;

class DocForm extends FormAbstract
{
    public function setup(): void
    {
        $this->setupModel(new Doc);

        /** @var Doc $model */
        $model = $this->getModel();
        /** @var DocProduct $product */
        $product = $this->getFormOption('product');

        $this
            ->setValidatorClass(DocRequest::class)
            ->add(
                'context',
                HtmlField::class,
                HtmlFieldOption::make()
                    ->content(view('plugins/docs-pro::docs.context', compact('product', 'model'))->render())
            )
            ->add('name', TextField::class, NameFieldOption::make()->required())
            ->add(
                'slug',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/docs-pro::docs-pro.form_slug'))
                    ->helperText(trans('plugins/docs-pro::docs-pro.doc_slug_helper'))
            )
            ->add(
                'menu_title',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/docs-pro::docs-pro.form_menu_title'))
                    ->helperText(trans('plugins/docs-pro::docs-pro.form_menu_title_helper'))
            )
            ->add(
                'parent_id',
                SelectField::class,
                SelectFieldOption::make()
                    ->label(trans('plugins/docs-pro::docs-pro.form_parent'))
                    ->choices($this->getParentChoices($product, $model))
                    ->searchable()
            )
            ->add(
                'excerpt',
                TextareaField::class,
                TextareaFieldOption::make()
                    ->label(trans('plugins/docs-pro::docs-pro.form_excerpt'))
                    ->rows(3)
            )
            ->add(
                'content',
                EditorField::class,
                EditorFieldOption::make()
                    ->label(trans('plugins/docs-pro::docs-pro.form_content'))
                    ->rows(12)
            )
            ->add(
                'sort_order',
                NumberField::class,
                NumberFieldOption::make()
                    ->label(trans('plugins/docs-pro::docs-pro.form_sort_order'))
                    ->defaultValue(0)
                    ->min(0)
            )
            ->add(
                'is_section',
                OnOffField::class,
                OnOffFieldOption::make()
                    ->label(trans('plugins/docs-pro::docs-pro.form_is_section'))
                    ->helperText(trans('plugins/docs-pro::docs-pro.form_is_section_helper'))
                    ->value((bool) old('is_section', $model->is_section))
            )
            ->add(
                'is_default',
                OnOffField::class,
                OnOffFieldOption::make()
                    ->label(trans('plugins/docs-pro::docs-pro.form_doc_is_default'))
                    ->helperText(trans('plugins/docs-pro::docs-pro.form_doc_is_default_helper'))
                    ->value((bool) old('is_default', $model->is_default))
            )
            ->when(
                $model->exists,
                fn (DocForm $form) => $form->add(
                    'public_link',
                    HtmlField::class,
                    HtmlFieldOption::make()
                        ->content(sprintf(
                            '<div class="alert alert-info">%s <a href="%s" target="_blank">%s</a></div>',
                            e(trans('plugins/docs-pro::docs-pro.public_link')),
                            e(route('public.docs.show', ['productSlug' => $product->slug, 'path' => $model->slug_path])),
                            e(Str::limit(route('public.docs.show', ['productSlug' => $product->slug, 'path' => $model->slug_path]), 80))
                        ))
                )
            )
            ->add('status', SelectField::class, StatusFieldOption::make())
            ->setBreakFieldPoint('status');
    }

    protected function getParentChoices(DocProduct $product, Doc $model): array
    {
        $choices = [0 => trans('plugins/docs-pro::docs-pro.parent_none')];

        $documents = Doc::query()
            ->where('product_id', $product->getKey())
            ->when(
                $model->exists,
                fn ($query) => $query->whereKeyNot($model->getKey())
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name']);

        $grouped = $documents->groupBy(fn (Doc $doc): int => (int) ($doc->parent_id ?: 0));

        $addChildren = function (int $parentId = 0, int $depth = 0) use (&$addChildren, &$choices, $grouped): void {
            foreach ($grouped->get($parentId, collect()) as $document) {
                $choices[$document->getKey()] = str_repeat('— ', $depth).$document->name;
                $addChildren((int) $document->getKey(), $depth + 1);
            }
        };

        $addChildren();

        return $choices;
    }
}
