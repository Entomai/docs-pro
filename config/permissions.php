<?php

return [
    [
        'name' => 'Docs Pro',
        'flag' => 'docs-pro.products.index',
    ],
    [
        'name' => 'Create products',
        'flag' => 'docs-pro.products.create',
        'parent_flag' => 'docs-pro.products.index',
    ],
    [
        'name' => 'Edit products',
        'flag' => 'docs-pro.products.edit',
        'parent_flag' => 'docs-pro.products.index',
    ],
    [
        'name' => 'Delete products',
        'flag' => 'docs-pro.products.destroy',
        'parent_flag' => 'docs-pro.products.index',
    ],
    [
        'name' => 'Manage docs',
        'flag' => 'docs-pro.docs.index',
        'parent_flag' => 'docs-pro.products.index',
    ],
    [
        'name' => 'Create docs',
        'flag' => 'docs-pro.docs.create',
        'parent_flag' => 'docs-pro.docs.index',
    ],
    [
        'name' => 'Edit docs',
        'flag' => 'docs-pro.docs.edit',
        'parent_flag' => 'docs-pro.docs.index',
    ],
    [
        'name' => 'Delete docs',
        'flag' => 'docs-pro.docs.destroy',
        'parent_flag' => 'docs-pro.docs.index',
    ],
    [
        'name' => 'Import docs ZIP',
        'flag' => 'docs-pro.import',
        'parent_flag' => 'docs-pro.docs.index',
    ],
    [
        'name' => 'Export docs ZIP',
        'flag' => 'docs-pro.export',
        'parent_flag' => 'docs-pro.docs.index',
    ],
];
