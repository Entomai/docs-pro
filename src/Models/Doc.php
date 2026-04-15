<?php

namespace Botble\DocsPro\Models;

use Botble\Base\Casts\SafeContent;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doc extends BaseModel
{
    public const NODE_TYPE_DOC = 'doc';

    public const NODE_TYPE_TITLE = 'title';

    public const NODE_TYPE_SEPARATOR = 'separator';

    protected $table = 'docs_pro_docs';

    protected $fillable = [
        'product_id',
        'parent_id',
        'name',
        'slug',
        'slug_path',
        'menu_title',
        'excerpt',
        'markdown_content',
        'content',
        'source_path',
        'sort_order',
        'node_type',
        'is_section',
        'is_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'name' => SafeContent::class,
            'slug' => SafeContent::class,
            'slug_path' => SafeContent::class,
            'menu_title' => SafeContent::class,
            'excerpt' => SafeContent::class,
            'source_path' => SafeContent::class,
            'sort_order' => 'integer',
            'node_type' => 'string',
            'is_section' => 'boolean',
            'is_default' => 'boolean',
            'status' => BaseStatusEnum::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DocProduct::class, 'product_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function scopePublished($query)
    {
        return $query->where('status', BaseStatusEnum::PUBLISHED);
    }

    public function menuTitle(): string
    {
        return $this->menu_title ?: $this->name;
    }

    public function isDoc(): bool
    {
        return $this->node_type === self::NODE_TYPE_DOC;
    }

    public function isTitle(): bool
    {
        return $this->node_type === self::NODE_TYPE_TITLE;
    }

    public function isSeparator(): bool
    {
        return $this->node_type === self::NODE_TYPE_SEPARATOR;
    }
}
