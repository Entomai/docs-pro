<?php

namespace Botble\DocsPro\Http\Requests;

use Botble\Support\Http\Requests\Request;

class DocsImportRequest extends Request
{
    public function rules(): array
    {
        return [
            'archive' => ['required', 'file', 'mimes:zip'],
            'replace_existing' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'archive' => trans('plugins/docs-pro::docs-pro.import_archive'),
            'replace_existing' => trans('plugins/docs-pro::docs-pro.import_replace_existing'),
        ];
    }
}
