<?php

namespace Botble\DocsPro\Models;

use Botble\Base\Casts\SafeContent;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Models\BaseModel;
use Botble\Language\Models\LanguageMeta;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DocProduct extends BaseModel
{
    protected $table = 'docs_pros';

    protected $fillable = [
        'name',
        'slug',
        'menu_label',
        'description',
        'sort_order',
        'is_default',
        'source_archive_name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'name' => SafeContent::class,
            'slug' => SafeContent::class,
            'menu_label' => SafeContent::class,
            'description' => SafeContent::class,
            'source_archive_name' => SafeContent::class,
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'status' => BaseStatusEnum::class,
        ];
    }

    public function docs(): HasMany
    {
        return $this->hasMany(Doc::class, 'product_id');
    }

    public function defaultDoc(): HasOne
    {
        return $this->hasOne(Doc::class, 'product_id')
            ->where('is_default', true);
    }

    public function languageMetas(): MorphMany
    {
        return $this->morphMany(LanguageMeta::class, 'reference');
    }

    public function scopePublished($query)
    {
        return $query->where('status', BaseStatusEnum::PUBLISHED);
    }

    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => $this->menu_label ?: $this->name);
    }
}
