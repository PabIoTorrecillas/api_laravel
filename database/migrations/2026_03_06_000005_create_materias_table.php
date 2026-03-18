<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('materias', function (Blueprint $table) {
            $table->id('id_materia');
            $table->string('clave_materia', 20)->unique();
            $table->string('nombre_materia', 100)->unique();
            $table->string('materia')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materias');
    }
};