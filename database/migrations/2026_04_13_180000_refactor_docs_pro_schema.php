<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('docs_pros')) {
            Schema::table('docs_pros', function (Blueprint $table): void {
                if (! Schema::hasColumn('docs_pros', 'slug')) {
                    $table->string('slug', 255)->nullable()->after('name');
                }

                if (! Schema::hasColumn('docs_pros', 'menu_label')) {
                    $table->string('menu_label', 255)->nullable()->after('slug');
                }

                if (! Schema::hasColumn('docs_pros', 'description')) {
                    $table->text('description')->nullable()->after('menu_label');
                }

                if (! Schema::hasColumn('docs_pros', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('description');
                }

                if (! Schema::hasColumn('docs_pros', 'is_default')) {
                    $table->boolean('is_default')->default(false)->after('sort_order');
                }

                if (! Schema::hasColumn('docs_pros', 'source_archive_name')) {
                    $table->string('source_archive_name', 255)->nullable()->after('is_default');
                }
            });

            $products = DB::table('docs_pros')
                ->select(['id', 'name', 'slug'])
                ->orderBy('id')
                ->get();

            $existingSlugs = [];

            foreach ($products as $product) {
                $slug = $product->slug ?: Str::slug((string) $product->name);

                if ($slug === '') {
                    $slug = 'product-'.$product->id;
                }

                $baseSlug = $slug;
                $counter = 2;

                while (in_array($slug, $existingSlugs, true)) {
                    $slug = $baseSlug.'-'.$counter;
                    $counter++;
                }

                $existingSlugs[] = $slug;

                DB::table('docs_pros')
                    ->where('id', $product->id)
                    ->update([
                        'slug' => $slug,
                    ]);
            }

            if ($products->isNotEmpty() && ! DB::table('docs_pros')->where('is_default', true)->exists()) {
                DB::table('docs_pros')
                    ->where('id', $products->first()->id)
                    ->update(['is_default' => true]);
            }

            Schema::table('docs_pros', function (Blueprint $table): void {
                $table->string('slug', 255)->nullable(false)->change();
            });

            try {
                Schema::table('docs_pros', function (Blueprint $table): void {
                    $table->unique('slug', 'docs_pros_slug_unique');
                });
            } catch (\Throwable) {
                // The unique index may already exist on upgraded installations.
            }
        }

        if (! Schema::hasTable('docs_pro_docs')) {
            Schema::create('docs_pro_docs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('docs_pros')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('docs_pro_docs')->nullOnDelete();
                $table->string('name', 255);
                $table->string('slug', 255);
                $table->string('slug_path', 500);
                $table->string('menu_title', 255)->nullable();
                $table->text('excerpt')->nullable();
                $table->longText('content')->nullable();
                $table->string('source_path', 1024)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_section')->default(false);
                $table->boolean('is_default')->default(false);
                $table->string('status', 60)->default('published');
                $table->timestamps();

                $table->unique(['product_id', 'slug_path'], 'docs_pro_docs_product_slug_path_unique');
                $table->index(['product_id', 'parent_id'], 'docs_pro_docs_product_parent_index');
            });
        } else {
            Schema::table('docs_pro_docs', function (Blueprint $table): void {
                if (Schema::hasColumn('docs_pro_docs', 'slug_path')) {
                    $table->string('slug_path', 500)->change();
                }
            });

            try {
                Schema::table('docs_pro_docs', function (Blueprint $table): void {
                    $table->unique(['product_id', 'slug_path'], 'docs_pro_docs_product_slug_path_unique');
                });
            } catch (\Throwable) {
                // The unique index may already exist on upgraded installations.
            }

            try {
                Schema::table('docs_pro_docs', function (Blueprint $table): void {
                    $table->index(['product_id', 'parent_id'], 'docs_pro_docs_product_parent_index');
                });
            } catch (\Throwable) {
                // The compound index may already exist on upgraded installations.
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_pro_docs');
    }
};
