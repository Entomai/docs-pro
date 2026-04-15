<?php

namespace Botble\DocsPro\Forms;

use Botble\Base\Forms\FieldOptions\HtmlFieldOption;
use Botble\Base\Forms\FieldOptions\NameFieldOption;
use Botble\Base\Forms\FieldOptions\NumberFieldOption;
use Botble\Base\Forms\FieldOptions\OnOffFieldOption;
use Botble\Base\Forms\FieldOptions\StatusFieldOption;
use Botble\Base\Forms\FieldOptions\TextFieldOption;
use Botble\Base\Forms\Fields\HtmlField;
use Botble\Base\Forms\Fields\NumberField;
use Botble\Base\Forms\Fields\OnOffField;
use Botble\Base\Forms\Fields\SelectField;
use Botble\Base\Forms\Fields\TextField;
use Botble\Base\Forms\FormAbstract;
use Botble\DocsPro\Http\Requests\DocProductRequest;
use Botble\DocsPro\Models\DocProduct;

class DocProductForm extends FormAbstract
{
    public function setup(): void
    {
        $this->setupModel(new DocProduct);

        /** @var DocProduct $model */
        $model = $this->getModel();

        $this
            ->setValidatorClass(DocProductRequest::class)
            ->add(
                'name',
                TextField::class,
                NameFieldOption::make()
                    ->required()
                    ->attributes(['data-docs-pro-product-name' => '1'])
            )
            ->add(
                'slug',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/docs-pro::docs-pro.form_slug'))
                    ->helperText(trans('plugins/docs-pro::docs-pro.form_slug_helper'))
                    ->attributes(['data-docs-pro-product-slug' => '1'])
            )
            ->add(
                'menu_label',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/docs-pro::docs-pro.form_menu_label'))
                    ->helperText(trans('plugins/docs-pro::docs-pro.form_menu_label_helper'))
                    ->attributes(['data-docs-pro-product-menu-label' => '1'])
            )
            ->when(
                ! $model->exists,
                fn (DocProductForm $form) => $form->add(
                    'auto_fill_fields_script',
                    HtmlField::class,
                    HtmlFieldOption::make()
                        ->content($this->renderAutoFillFieldsScript())
                        ->labelShow(false)
                        ->wrapperAttributes(['class' => 'd-none'])
                )
            )
            ->add(
                'description',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/docs-pro::docs-pro.form_description'))
                    ->maxLength(40)
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
                'is_default',
                OnOffField::class,
                OnOffFieldOption::make()
                    ->label(trans('plugins/docs-pro::docs-pro.form_is_default'))
                    ->helperText(trans('plugins/docs-pro::docs-pro.form_is_default_helper'))
                    ->value((bool) old('is_default', $model->is_default))
            )
            ->when(
                $model->exists,
                fn (DocProductForm $form) => $form->add(
                    'quick_actions',
                    HtmlField::class,
                    HtmlFieldOption::make()
                        ->content(view('plugins/docs-pro::products.quick-actions', ['product' => $model])->render())
                )
            )
            ->add('status', SelectField::class, StatusFieldOption::make())
            ->setBreakFieldPoint('status');
    }

    protected function renderAutoFillFieldsScript(): string
    {
        return <<<'HTML'
<script>
(() => {
    const init = () => {
        const form = document.querySelector('.js-base-form');

        if (!form || form.dataset.docsProProductAutoFillReady === '1') {
            return;
        }

        const nameInput = form.querySelector('[data-docs-pro-product-name]');
        const slugInput = form.querySelector('[data-docs-pro-product-slug]');
        const menuLabelInput = form.querySelector('[data-docs-pro-product-menu-label]');

        if (!nameInput || !slugInput || !menuLabelInput) {
            return;
        }

        form.dataset.docsProProductAutoFillReady = '1';

        const slugify = (value) =>
            value
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');

        const syncState = {
            slugAuto: slugInput.value.trim() === '',
            menuLabelAuto: menuLabelInput.value.trim() === '',
        };

        const refreshAutoFlags = () => {
            const currentName = nameInput.value.trim();
            const generatedSlug = slugify(currentName);

            syncState.slugAuto = slugInput.value.trim() === '' || slugInput.value.trim() === generatedSlug;
            syncState.menuLabelAuto = menuLabelInput.value.trim() === '' || menuLabelInput.value.trim() === currentName;
        };

        const syncFromName = () => {
            const currentName = nameInput.value.trim();

            if (syncState.slugAuto) {
                slugInput.value = slugify(currentName);
            }

            if (syncState.menuLabelAuto) {
                menuLabelInput.value = currentName;
            }
        };

        nameInput.addEventListener('input', syncFromName);
        slugInput.addEventListener('input', refreshAutoFlags);
        menuLabelInput.addEventListener('input', refreshAutoFlags);

        syncFromName();
        refreshAutoFlags();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
</script>
HTML;
    }
}
