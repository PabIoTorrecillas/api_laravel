<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Maestro;
use App\Models\Alumno;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
public function run(): void
    {
        Role::firstOrCreate(['nombre_rol' => 'Maestro']);
        Role::firstOrCreate(['nombre_rol' => 'Alumno']);
        Role::firstOrCreate(['nombre_rol' => 'Admin']);

        $rolMaestro = Role::where('nombre_rol', 'Maestro')->first();
        $rolAlumno  = Role::where('nombre_rol', 'Alumno')->first();
        $rolAdmin   = Role::where('nombre_rol', 'Admin')->first();

        User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'nombre'   => 'Administrador del Sistema',
                'password' => Hash::make('admin123'), //cambiamos mas bien quitamos  luego jajaja
                'id_rol'   => $rolAdmin->id_rol
            ]
        );

        $userMaestro = User::firstOrCreate(
            ['email' => 'maestro@test.com'],
            [
                'nombre'   => 'Profe Troncoso',
                'password' => Hash::make('secret123'),
                'id_rol'   => $rolMaestro->id_rol
            ]
        );

        Maestro::firstOrCreate([
            'id_usuario' => $userMaestro->id_usuario
        ]);

        $userAlumno = User::firstOrCreate(
            ['email' => 'alumno@test.com'],
            [
                'nombre'   => 'Juanito Pérez',
                'password' => Hash::make('secret123'),
                'id_rol'   => $rolAlumno->id_rol
            ]
        );

        Alumno::firstOrCreate([
            'id_usuario' => $userAlumno->id_usuario,
            'num_ctrl'   => '22030128'
        ]);

        $this->command->info('Base de datos poblada: admin@test.com, maestro@test.com y alumno@test.com');
    }
}
