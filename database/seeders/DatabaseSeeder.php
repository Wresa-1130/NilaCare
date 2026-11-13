<?php

namespace Database\Seeders;

use App\Models\Pond;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Akun petugas dan kolam awal. Ubah email/password lewat .env sebelum seeding.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('NILACARE_ADMIN_EMAIL', 'petugas@nilacare.test')],
            ['name' => env('NILACARE_ADMIN_NAME', 'Petugas Kolam'), 'password' => Hash::make(env('NILACARE_ADMIN_PASSWORD', 'nilacare123'))]
        );

        Pond::firstOrCreate(
            ['code' => config('nilacare.default_pond')],
            ['name' => 'Kolam Nila 1']
        );
    }
}
