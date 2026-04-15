<?php

use Botble\DocsPro\Models\Doc;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('docs_pro_docs')) {
            return;
        }

        Schema::table('docs_pro_docs', function (Blueprint $table): void {
            if (! Schema::hasColumn('docs_pro_docs', 'node_type')) {
                $table->string('node_type', 30)->default(Doc::NODE_TYPE_DOC)->after('sort_order');
            }

            if (! Schema::hasColumn('docs_pro_docs', 'markdown_content')) {
                $table->longText('markdown_content')->nullable()->after('excerpt');
            }
        });

        DB::table('docs_pro_docs')
            ->whereNull('node_type')
            ->update([
                'node_type' => Doc::NODE_TYPE_DOC,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('docs_pro_docs')) {
            return;
        }

        Schema::table('docs_pro_docs', function (Blueprint $table): void {
            if (Schema::hasColumn('docs_pro_docs', 'node_type')) {
                $table->dropColumn('node_type');
            }

            if (Schema::hasColumn('docs_pro_docs', 'markdown_content')) {
                $table->dropColumn('markdown_content');
            }
        });
    }
};
