<?php

namespace Botble\DocsPro;

use Botble\PluginManagement\Abstracts\PluginOperationAbstract;
use Illuminate\Support\Facades\Schema;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('docs_pro_docs');
        Schema::dropIfExists('docs_pros_translations');
        Schema::dropIfExists('docs_pros');

        Schema::enableForeignKeyConstraints();
    }
}
