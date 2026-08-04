<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateIdCard extends Model
{
    protected $table = 'template_id_card';
    protected $fillable = ['path', 'status'];
}
