<?php

namespace Botble\DocsPro\Http\Requests;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\DocsPro\Models\Doc;
use Botble\Support\Http\Requests\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocRequest extends Request
{
    protected function prepareForValidation(): void
    {
        $nodeType = $this->input('node_type') ?: 'doc';

        if (! $this->input('name')) {
            $this->merge([
                'name' => match ($nodeType) {
                    'title' => 'New title',
                    'separator' => 'New separator',
                    default => 'New doc',
                },
            ]);
        }

        if (! $this->input('slug') && $this->input('name')) {
            $this->merge([
                'slug' => Str::slug($this->input('name')),
            ]);
        }

        if ((int) $this->input('parent_id') === 0) {
            $this->merge([
                'parent_id' => null,
            ]);
        }

        if (! $this->input('node_type')) {
            $this->merge([
                'node_type' => Doc::NODE_TYPE_DOC,
            ]);
        }

        if ($this->input('node_type') !== Doc::NODE_TYPE_DOC) {
            $this->merge([
                'is_default' => false,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'node_type' => ['required', Rule::in([
                Doc::NODE_TYPE_DOC,
                Doc::NODE_TYPE_TITLE,
                Doc::NODE_TYPE_SEPARATOR,
            ])],
            'menu_title' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:docs_pro_docs,id'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'markdown_content' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_section' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'status' => Rule::in(BaseStatusEnum::values()),
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => trans('plugins/docs-pro::docs-pro.form_name'),
            'slug' => trans('plugins/docs-pro::docs-pro.form_slug'),
            'node_type' => trans('plugins/docs-pro::docs-pro.form_node_type'),
            'menu_title' => trans('plugins/docs-pro::docs-pro.form_menu_title'),
            'parent_id' => trans('plugins/docs-pro::docs-pro.form_parent'),
            'excerpt' => trans('plugins/docs-pro::docs-pro.form_excerpt'),
            'markdown_content' => trans('plugins/docs-pro::docs-pro.form_markdown'),
            'content' => trans('plugins/docs-pro::docs-pro.form_content'),
            'sort_order' => trans('plugins/docs-pro::docs-pro.form_sort_order'),
            'is_section' => trans('plugins/docs-pro::docs-pro.form_is_section'),
            'is_default' => trans('plugins/docs-pro::docs-pro.form_doc_is_default'),
            'status' => trans('plugins/docs-pro::docs-pro.form_status'),
        ];
    }
}
