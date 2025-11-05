<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Ejecuta el seeder para crear un usuario administrador.
     */
    public function run(): void
    {
        // Elimina si ya existe un usuario con el mismo correo o usuario
        User::where('email', 'admin@dipii.com')
            ->orWhere('usuario', 'Cesar')
            ->delete();

        // Crea el nuevo usuario administrador
        User::create([
            'nombre'     => 'Cesar',
            'usuario'  => 'Cesar', // usa este campo solo si existe en tu tabla
            'email'    => 'admin@dipii.com',
            'password' => Hash::make('Cesar123'), // Laravel genera el hash automáticamente
        ]);
    }
}
