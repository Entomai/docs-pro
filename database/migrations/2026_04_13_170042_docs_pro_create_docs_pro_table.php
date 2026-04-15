<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('docs_pros')) {
            Schema::create('docs_pros', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('status', 60)->default('published');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('docs_pros_translations')) {
            Schema::create('docs_pros_translations', function (Blueprint $table) {
                $table->string('lang_code');
                $table->foreignId('docs_pros_id');
                $table->string('name', 255)->nullable();

                $table->primary(['lang_code', 'docs_pros_id'], 'docs_pros_translations_primary');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_pros');
        Schema::dropIfExists('docs_pros_translations');
    }
};
