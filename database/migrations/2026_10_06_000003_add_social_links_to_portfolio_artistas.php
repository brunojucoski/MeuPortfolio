<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_artistas', function (Blueprint $table) {
            $table->string('link_tiktok', 2000)->nullable();
            $table->string('link_github', 2000)->nullable();
            $table->string('link_linkedin', 2000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_artistas', function (Blueprint $table) {
            $table->dropColumn(['link_tiktok', 'link_github', 'link_linkedin']);
        });
    }
};
