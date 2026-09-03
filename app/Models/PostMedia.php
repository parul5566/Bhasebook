<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostMedia extends Model
{
    protected $fillable = ['post_id', 'path', 'mime', 'kind', 'width', 'height'];
}
