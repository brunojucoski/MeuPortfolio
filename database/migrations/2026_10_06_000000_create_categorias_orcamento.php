<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_orcamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_portfolio_artista')->constrained('portfolio_artistas')->cascadeOnDelete();
            $table->string('nome', 120);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
            $table->unique(['id_portfolio_artista', 'nome']);
        });

        Schema::table('perguntas_proposta_contrato', function (Blueprint $table) {
            $table->foreignId('id_categoria_orcamento')->nullable()->constrained('categorias_orcamento')->restrictOnDelete();
        });
        Schema::table('proposta_contrato', function (Blueprint $table) {
            $table->foreignId('id_categoria_orcamento')->nullable()->constrained('categorias_orcamento')->restrictOnDelete();
            $table->string('categoria_orcamento_nome', 120)->nullable();
        });

        // Preserve the existing forms and identify their historical proposals.
        DB::table('perguntas_proposta_contrato')->select('id_portfolio_artista')->distinct()->get()
            ->each(function ($portfolio) {
                $id = DB::table('categorias_orcamento')->insertGetId([
                    'id_portfolio_artista' => $portfolio->id_portfolio_artista,
                    'nome' => 'Geral', 'ordem' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('perguntas_proposta_contrato')->where('id_portfolio_artista', $portfolio->id_portfolio_artista)
                    ->update(['id_categoria_orcamento' => $id]);
                DB::table('proposta_contrato')->where('id_artista', $portfolio->id_portfolio_artista)
                    ->update(['id_categoria_orcamento' => $id, 'categoria_orcamento_nome' => 'Geral']);
            });
    }

    public function down(): void
    {
        Schema::table('proposta_contrato', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_categoria_orcamento');
            $table->dropColumn('categoria_orcamento_nome');
        });
        Schema::table('perguntas_proposta_contrato', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_categoria_orcamento');
        });
        Schema::dropIfExists('categorias_orcamento');
    }
};
