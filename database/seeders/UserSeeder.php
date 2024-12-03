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
                'email' => 'johne@gmail.com',
                'password' => 'password123',
                'province_code' => 'JKT',
                'city_code' => '001',
                'profile_photo' => 'default.jpg',   // Jika tidak ada foto
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'janeh@gmail.com',
                'password' => 'password456',
                'province_code' => 'BGR',
                'city_code' => '002',
                'profile_photo' => 'default.jpg',
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
            if ($userData['profile_photo'] !== 'default.jpg') {
                $profilePhotoPath = Storage::putFile('profile_photos', $userData['profile_photo']);
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
                'profile_photo' => $profilePhotoPath ? Storage::url($profilePhotoPath) : 'default.jpg',
            ]);
        }
    }
}
