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
            'caption' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Cek jika tidak ada caption dan foto
        if (!$request->has('caption') && !$request->hasFile('photo')) {
            return response()->json([
                'status' => 400,
                'message' => 'Caption atau foto harus diisi.',
            ], 400);
        }

        try {
            $photoPath = null;

            // Jika ada foto, simpan foto
            if ($request->hasFile('photo')) {
                // Pastikan file foto valid dan simpan di folder 'posts' pada disk public
                $photoPath = $request->file('photo')->store('posts', 'public');
            }

            // Membuat postingan baru
            $post = Post::create([
                'user_id' => $request->user()->id,
                'caption' => $request->caption,
                'photo' => $photoPath ? Storage::url($photoPath) : null,
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



    // Hapus Postingan
    public function deletePost($postId)
    {
        $post = Post::findOrFail($postId);

        // Periksa apakah pengguna yang menghapus adalah pemilik postingan
        if ($post->user_id !== auth()->id()) {
            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak memiliki izin untuk menghapus postingan ini.',
            ], 403);
        }

        // Hapus foto jika ada
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

        // Periksa apakah pengguna yang menghapus adalah pemilik komentar
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
