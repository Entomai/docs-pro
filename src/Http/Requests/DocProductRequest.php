<?php

namespace Botble\DocsPro\Http\Requests;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\DocsPro\Models\DocProduct;
use Botble\Support\Http\Requests\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocProductRequest extends Request
{
    protected function prepareForValidation(): void
    {
        if (! $this->input('slug') && $this->input('name')) {
            $this->merge([
                'slug' => Str::slug($this->input('name')),
            ]);
        }
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof DocProduct ? $product->getKey() : $product;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('docs_pros', 'slug')->ignore($productId),
            ],
            'menu_label' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'status' => Rule::in(BaseStatusEnum::values()),
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => trans('plugins/docs-pro::docs-pro.form_name'),
            'slug' => trans('plugins/docs-pro::docs-pro.form_slug'),
            'menu_label' => trans('plugins/docs-pro::docs-pro.form_menu_label'),
            'description' => trans('plugins/docs-pro::docs-pro.form_description'),
            'sort_order' => trans('plugins/docs-pro::docs-pro.form_sort_order'),
            'is_default' => trans('plugins/docs-pro::docs-pro.form_is_default'),
            'status' => trans('plugins/docs-pro::docs-pro.form_status'),
        ];
    }
}
