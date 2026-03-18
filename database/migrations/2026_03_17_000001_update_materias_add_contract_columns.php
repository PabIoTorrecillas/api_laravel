<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('materias', function (Blueprint $table) {
            // Añadimos los campos del contrato OpenAPI
            $table->string('clave_materia', 20)->nullable()->after('id_materia');
            $table->string('nombre_materia', 100)->nullable()->after('clave_materia');
        });

        // Migramos datos existentes: el campo original 'materia' pasa a 'nombre_materia'
        DB::statement("UPDATE materias SET nombre_materia = materia, clave_materia = CONCAT('MAT-', id_materia) WHERE nombre_materia IS NULL");

        Schema::table('materias', function (Blueprint $table) {
            $table->string('clave_materia', 20)->nullable(false)->unique()->change();
            $table->string('nombre_materia', 100)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('materias', function (Blueprint $table) {
            $table->dropColumn(['clave_materia', 'nombre_materia']);
        });
    }
};
