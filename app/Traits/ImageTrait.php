<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait ImageTrait {

    /**
     * @param Request $request
     * @return $this|false|string
     */

    public function getImageAttribute($value){
        if ($value) {
            return getimg($value);
        } elseif(filter_var($value, FILTER_VALIDATE_URL)){
            return  $value;
        } else
            return  asset('storage/images/default.jpg');
    }

    public function setImageAttribute($value,$directory = 'images'){
        if (is_file($value))
            $this->attributes['image'] = uploader($value,$directory);
        else
            $this->attributes['image'] = $value;
    }

    public function uploadImage($folder,$image){
        $image->store('/',$folder);
        $filename = $image->hashName();
        $path = 'images/'.'/'. $filename;
        return $path;
    }
    public function uploadImagePost($folder,$image){
        $image->store('/',$folder);
        $filename = $image->hashName();
        $path = 'images/' .$folder.'/'. $filename;
        return $path;
    }
    //videos
    public function uploadImageVideo($folder,$image){
        $image->store('/',$folder);
        $filename = $image->hashName();
        $path = 'videos/'. $filename;
        return $path;
    }
    public function uploadImageFront($folder,$image){
        $image->store('/',$folder);
        $filename = $image->hashName();
        $path =  $filename;
        return $path;
    }
    //
    //deleteImage
    public function deleteImage($imagePath)
    {
        if ($imagePath && file_exists(public_path($imagePath))) {
            unlink(public_path($imagePath));
        }
    }

}
