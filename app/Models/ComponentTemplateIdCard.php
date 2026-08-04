<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComponentTemplateIdCard extends Model
{
    protected $table = 'component_template_id_card';
    protected $fillable = ['id_template', 'title', 'position_x', 'position_y'];

    public function template()
    {
        return $this->belongsTo(TemplateIdCard::class, 'id_template');
    }
}
