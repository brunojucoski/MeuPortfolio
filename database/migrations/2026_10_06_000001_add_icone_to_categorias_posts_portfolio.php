<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias_posts_portfolio', function (Blueprint $table) {
            $table->string('icone', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('categorias_posts_portfolio', function (Blueprint $table) {
            $table->dropColumn('icone');
        });
    }
};
