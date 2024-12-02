<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Like;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    // Buat Postingan Baru
    public function createPost(Request $request)
    {
        // Validasi input
        $request->validate([
            'caption' => 'required_without:photo|string',
            'photo' => 'required_without:caption|array|max:8',
            'photo.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',  // Validasi untuk masing-masing file
        ]);

        try {
            $photoPaths = [];

            // Memeriksa apakah ada file foto yang diupload
            if ($request->hasFile('photo')) {
                foreach ($request->file('photo') as $photo) {
                    $photoPath = $photo->store('posts', 'public'); // Menyimpan foto ke folder 'posts'
                    $photoPaths[] = Storage::url($photoPath); // Menyimpan URL foto
                }
            }

            // Membuat postingan baru
            $post = Post::create([
                'user_id' => $request->user()->id,
                'caption' => $request->caption,
                'photo' => json_encode($photoPaths), // Menyimpan array URL dalam bentuk JSON
            ]);

            return response()->json([
                'status' => 201,
                'message' => 'Postingan berhasil dibuat',
                'data' => $post,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Gagal membuat postingan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Update Postingan
    public function updatePost(Request $request, $postId)
    {
        $post = Post::findOrFail($postId);

        // Pastikan pengguna yang sedang login adalah pemilik postingan
        if ($post->user_id !== auth()->id()) {
            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak memiliki izin untuk mengupdate postingan ini.',
            ], 403);
        }

        // Validasi input
        $request->validate([
            'caption' => 'nullable|string',
            'photo' => 'nullable|array|max:8',
            'photo.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            $photoPaths = json_decode($post->photo, true) ?? [];

            if ($request->hasFile('photo')) {
                if (!empty($photoPaths)) {
                    foreach ($photoPaths as $photo) {
                        $photoPath = str_replace('/storage/', '', $photo);
                        Storage::disk('public')->delete($photoPath);
                    }
                }
                // Upload foto baru
                $photoPaths = [];
                foreach ($request->file('photo') as $photo) {
                    $photoPath = $photo->store('posts', 'public');
                    $photoPaths[] = Storage::url($photoPath);
                }
            }

            // Perbarui postingan
            $post->update([
                'caption' => $request->caption ?? $post->caption,
                'photo' => json_encode($photoPaths),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Postingan berhasil diupdate',
                'data' => $post,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Gagal mengupdate postingan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    // Hapus Postingan
    public function deletePost($postId)
    {
        $post = Post::findOrFail($postId);

        if ($post->user_id !== auth()->id()) {
            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak memiliki izin untuk menghapus postingan ini.',
            ], 403);
        }

        if ($post->photo) {
            Storage::delete($post->photo);
        }

        $post->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Postingan berhasil dihapus',
        ]);
    }


    // Like Postingan
    public function likePost($postId)
    {
        $user = auth()->user();
        $post = Post::findOrFail($postId);

        $like = Like::where('user_id', $user->id)->where('post_id', $post->id)->first();
        if ($like) {
            $like->delete();
            return response()->json([
                'status' => 200,
                'message' => 'Like dibatalkan',
            ]);
        }

        Like::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Postingan disukai',
        ]);
    }
    // Hapus Komentar
    public function deleteComment($commentId)
    {
        $comment = Comment::findOrFail($commentId);

        if ($comment->user_id !== auth()->id()) {
            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak memiliki izin untuk menghapus komentar ini.',
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Komentar berhasil dihapus',
        ]);
    }

    // Tambah Komentar
    public function addComment(Request $request, $postId)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $post = Post::findOrFail($postId);

        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'post_id' => $post->id,
            'content' => $request->content,
        ]);

        return response()->json([
            'status' => 201,
            'message' => 'Komentar berhasil ditambahkan',
            'data' => $comment,
        ], 201);
    }

    // Balas Komentar
    public function replyToComment(Request $request, $commentId)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $parentComment = Comment::findOrFail($commentId);

        try {
            $reply = Comment::create([
                'post_id' => $parentComment->post_id,
                'user_id' => auth()->id(),
                'parent_id' => $parentComment->id,
                'content' => $request->content,
            ]);

            return response()->json([
                'status' => 201,
                'message' => 'Balasan berhasil ditambahkan',
                'data' => $reply,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Gagal menambahkan balasan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Dapatkan Semua Postingan
    public function getAllPosts()
    {
        $posts = Post::with(['user:id,name,profile_photo', 'likes', 'comments'])->latest()->get();

        $posts->each(function ($post) {
            $post->makeHidden(['user_id']);
            $post->comments->each(function ($comment) {
                $comment->makeHidden(['updated_at']);
            });
            $post->user->makeHidden(['id']);
        });

        return response()->json([
            'status' => 200,
            'message' => 'Berhasil mendapatkan semua postingan',
            'data' => $posts,
        ]);
    }


    // Dapatkan Komentar dengan Balasan
    public function getCommentsWithReplies($postId)
    {
        $comments = Comment::where('post_id', $postId)
            ->whereNull('parent_id')
            ->with('replies.user')
            ->orderBy('created_at', 'asc')
            ->get();


        return response()->json([
            'status' => 200,
            'message' => 'Komentar berhasil diambil',
            'data' => $comments,
        ]);
    }
}
