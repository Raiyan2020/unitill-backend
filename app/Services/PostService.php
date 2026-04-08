<?php
namespace App\Services;

use App\Repositories\PostRepository;
use App\Traits\ImageTrait;

class PostService
{
    use ImageTrait;
    protected $postRepo;

    public function __construct(PostRepository $postRepo)
    {
        $this->postRepo = $postRepo;
    }
    // Update post
    public function updatePost($id, array $data)
    {


        return $this->postRepo->update($id, $data);
    }


    public function updatePostStatus($id, $status)
    {
        return $this->postRepo->updateStatus($id, $status);
    }

    public function deletePost($id)
    {
        return $this->postRepo->delete($id);
    }
}
