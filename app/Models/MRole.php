<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MRole extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'm_roles';

    /**
     * @var string[]
     */
    protected $fillable = ['name'];
}
