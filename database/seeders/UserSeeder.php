<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'John Doe',
                'email' => 'johndoe@example.com',
                'password' => 'password123', // Password yang di-hash nanti
                'province_code' => 'JKT',  // Contoh kode provinsi
                'city_code' => '001',      // Contoh kode kota
                'profile_photo' => null,   // Jika tidak ada foto
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'janesmith@example.com',
                'password' => 'password456',
                'province_code' => 'BGR',
                'city_code' => '002',
                'profile_photo' => null,
            ],
            // Tambahkan lebih banyak data pengguna jika diperlukan
        ];

        foreach ($users as $userData) {
            // Hitung nomor urut registrasi
            $registerNumber = User::where('province_code', $userData['province_code'])
                ->where('city_code', $userData['city_code'])
                ->count() + 1;

            // Buat nomor unik
            $uniqueNumber = strtoupper($userData['province_code'])
                . strtoupper($userData['city_code'])
                . str_pad($registerNumber, 4, '0', STR_PAD_LEFT);

            // Proses unggah foto profil (jika ada)
            $profilePhotoPath = null;
            if ($userData['profile_photo']) {
                $profilePhotoPath = $userData['profile_photo']->store('profile_photos', 'public');
            }

            // Buat user baru
            User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'province_code' => $userData['province_code'],
                'city_code' => $userData['city_code'],
                'register_number' => $registerNumber,
                'unique_number' => $uniqueNumber,
                'profile_photo' => $profilePhotoPath ? Storage::url($profilePhotoPath) : null,
            ]);
        }
    }
}
