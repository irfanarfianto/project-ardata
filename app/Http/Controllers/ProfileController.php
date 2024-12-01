<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    // Update Nama dan Foto Profil
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            $user = $request->user();

            // Update nama jika diberikan
            if ($request->has('name') && $request->name) {
                $user->name = $request->name;
            }

            // Update foto profil jika diberikan
            if ($request->hasFile('profile_photo')) {
                // Hapus foto lama jika ada
                if ($user->profile_photo) {
                    $oldPhotoPath = str_replace('/storage/', '', $user->profile_photo);
                    Storage::disk('public')->delete($oldPhotoPath);
                }

                // Simpan foto baru
                $newPhotoPath = $request->file('profile_photo')->store('profile_photos', 'public');
                $user->profile_photo = Storage::url($newPhotoPath);
            }

            // Simpan perubahan
            $user->save();

            return response()->json([
                'status' => 200,
                'message' => 'Profil berhasil diperbarui',
                'data' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Gagal memperbarui profil',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
