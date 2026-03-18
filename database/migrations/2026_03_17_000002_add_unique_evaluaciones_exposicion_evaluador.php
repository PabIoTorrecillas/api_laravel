<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            // Garantiza que un evaluador no pueda evaluar la misma exposición dos veces
            $table->unique(['id_expo', 'id_usuario'], 'unique_evaluacion_por_exposicion_evaluador');
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->dropUnique('unique_evaluacion_por_exposicion_evaluador');
        });
    }
};
