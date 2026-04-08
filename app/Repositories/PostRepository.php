<?php

namespace App\Repositories;

use App\Models\Post;

class PostRepository
{
    public function update(int|string $id, array $data): Post
    {
        $post = Post::findOrFail($id);
        $post->update($data);

        return $post->fresh();
    }

    public function updateStatus(int|string $id, mixed $status): Post
    {
        return $this->update($id, ['status' => $status]);
    }

    public function delete(int|string $id): bool
    {
        return (bool) Post::destroy($id);
    }
}
