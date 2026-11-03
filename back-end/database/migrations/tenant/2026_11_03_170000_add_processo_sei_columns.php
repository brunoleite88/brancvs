<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planos_entregas_repactuacoes') && !Schema::hasColumn('planos_entregas_repactuacoes', 'numero_processo_sei')) {
            Schema::table('planos_entregas_repactuacoes', function (Blueprint $table) {
                $table->string('numero_processo_sei', 60)->nullable()->after('ciclo');
                $table->index('numero_processo_sei');
            });
        }

        if (Schema::hasTable('planos_entregas') && !Schema::hasColumn('planos_entregas', 'numero_processo_sei')) {
            Schema::table('planos_entregas', function (Blueprint $table) {
                $table->string('numero_processo_sei', 60)->nullable()->after('nome');
                $table->index('numero_processo_sei');
            });
        }

        if (Schema::hasTable('projetos') && !Schema::hasColumn('projetos', 'numero_processo_sei')) {
            Schema::table('projetos', function (Blueprint $table) {
                $table->string('numero_processo_sei', 60)->nullable()->after('nome');
                $table->index('numero_processo_sei');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('planos_entregas_repactuacoes') && Schema::hasColumn('planos_entregas_repactuacoes', 'numero_processo_sei')) {
            Schema::table('planos_entregas_repactuacoes', function (Blueprint $table) {
                $table->dropColumn('numero_processo_sei');
            });
        }

        if (Schema::hasTable('planos_entregas') && Schema::hasColumn('planos_entregas', 'numero_processo_sei')) {
            Schema::table('planos_entregas', function (Blueprint $table) {
                $table->dropColumn('numero_processo_sei');
            });
        }

        if (Schema::hasTable('projetos') && Schema::hasColumn('projetos', 'numero_processo_sei')) {
            Schema::table('projetos', function (Blueprint $table) {
                $table->dropColumn('numero_processo_sei');
            });
        }
    }
};
