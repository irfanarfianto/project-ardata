<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Like;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/posts",
     *     summary="Buat Postingan Baru",
     *     description="Membuat postingan baru dengan caption dan foto",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"caption", "photo"},
     *             @OA\Property(property="caption", type="string", example="Foto liburan"),
     *             @OA\Property(property="photo", type="array", @OA\Items(type="string", format="binary")),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Postingan berhasil dibuat",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=201),
     *             @OA\Property(property="message", type="string", example="Postingan berhasil dibuat"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="caption", type="string", example="Foto liburan"),
     *                 @OA\Property(property="photo", type="string", example="url_foto"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Gagal membuat postingan",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=500),
     *             @OA\Property(property="message", type="string", example="Gagal membuat postingan"),
     *             @OA\Property(property="error", type="string", example="Error detail")
     *         )
     *     )
     * )
     */
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
            return response($post, 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Gagal membuat postingan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/posts/{postId}",
     *     summary="Update Postingan",
     *     description="Update caption dan foto postingan yang sudah ada",
     *     @OA\Parameter(
     *         name="postId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="caption", type="string", example="Caption baru"),
     *             @OA\Property(property="photo", type="array", @OA\Items(type="string", format="binary")),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Postingan berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Postingan berhasil diupdate"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="caption", type="string", example="Caption baru"),
     *                 @OA\Property(property="photo", type="string", example="url_foto_baru"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Anda tidak memiliki izin untuk mengupdate postingan ini",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=403),
     *             @OA\Property(property="message", type="string", example="Anda tidak memiliki izin untuk mengupdate postingan ini")
     *         )
     *     )
     * )
     */
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

            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Gagal mengupdate postingan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * @OA\Delete(
     *     path="/api/posts/{postId}",
     *     summary="Hapus Postingan",
     *     description="Menghapus postingan yang ada berdasarkan ID",
     *     @OA\Parameter(
     *         name="postId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Postingan berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Postingan berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Anda tidak memiliki izin untuk menghapus postingan ini",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=403),
     *             @OA\Property(property="message", type="string", example="Anda tidak memiliki izin untuk menghapus postingan ini")
     *         )
     *     )
     * )
     */
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


    /**
     * @OA\Post(
     *     path="/api/posts/{postId}/like",
     *     summary="Like or unlike a post",
     *     description="Allows a user to like or unlike a post. If the user already liked the post, the like is removed.",
     *     @OA\Parameter(
     *         name="postId",
     *         in="path",
     *         description="ID of the post to like/unlike",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully liked or unliked the post",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Postingan disukai")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=500),
     *             @OA\Property(property="message", type="string", example="Gagal menambahkan like")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/comments/{commentId}",
     *     summary="Delete a comment",
     *     description="Allows a user to delete their comment.",
     *     @OA\Parameter(
     *         name="commentId",
     *         in="path",
     *         description="ID of the comment to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully deleted the comment",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Komentar berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden, user is not the owner of the comment",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=403),
     *             @OA\Property(property="message", type="string", example="Anda tidak memiliki izin untuk menghapus komentar ini.")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/posts/{postId}/comments",
     *     summary="Add a comment to a post",
     *     description="Allows a user to add a comment to a post.",
     *     @OA\Parameter(
     *         name="postId",
     *         in="path",
     *         description="ID of the post to add a comment to",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content", type="string", example="This is a great post!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successfully added the comment",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=201),
     *             @OA\Property(property="message", type="string", example="Komentar berhasil ditambahkan"),
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/Comment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request, validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=400),
     *             @OA\Property(property="message", type="string", example="The content field is required.")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/comments/{commentId}/replies",
     *     summary="Reply to a comment",
     *     description="Allows a user to reply to a comment.",
     *     @OA\Parameter(
     *         name="commentId",
     *         in="path",
     *         description="ID of the comment to reply to",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content", type="string", example="This is my reply to the comment!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successfully replied to the comment",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=201),
     *             @OA\Property(property="message", type="string", example="Balasan berhasil ditambahkan"),
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/Comment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=500),
     *             @OA\Property(property="message", type="string", example="Gagal menambahkan balasan")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/posts",
     *     summary="Get all posts",
     *     description="Retrieve all posts along with their comments and likes.",
     *     @OA\Response(
     *         response=200,
     *         description="Successfully fetched all posts",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Berhasil mendapatkan semua postingan"),
     *             @OA\Property(property="data", type="array", items={"$ref": "#/components/schemas/Post"})
     *         )
     *     )
     * )
     */
    public function getAllPosts()
    {
        $posts = Post::with(['user:id,name,profile_photo,unique_number', 'likes', 'comments'])->latest()->get();

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


    /**
     * @OA\Get(
     *     path="/api/posts/{postId}/comments",
     *     summary="Get comments with replies",
     *     description="Get all comments for a post including their replies.",
     *     @OA\Parameter(
     *         name="postId",
     *         in="path",
     *         description="ID of the post",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully fetched comments with replies",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Komentar berhasil diambil"),
     *             @OA\Property(property="data", type="array", items={"$ref": "#/components/schemas/Comment"})
     *         )
     *     )
     * )
     */
    public function getCommentsWithReplies($postId)
    {
        $comments = Comment::where('post_id', $postId)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'asc')
            ->get();

        $comments->each(function ($comment) {
            $comment->makeHidden(['updated_at', 'user_id']);
            $comment->user->makeHidden(['id', 'email', 'email_verified_at', 'created_at', 'updated_at', 'province_code', 'city_code', 'register_number', 'unique_number']);
            $comment->replies->each(function ($reply) {
                $reply->makeHidden(['updated_at', 'user_id']);
                $reply->user->makeHidden(['id', 'email', 'email_verified_at', 'created_at', 'updated_at', 'province_code', 'city_code', 'register_number', 'unique_number']);
            });
        });

        return response()->json([
            'status' => 200,
            'message' => 'Komentar berhasil diambil',
            'data' => $comments,
        ]);
    }
}
