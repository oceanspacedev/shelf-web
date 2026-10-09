<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObTaskTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'ob_task_template_id',
        'room',
        'sort_order',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ObTaskTemplate::class, 'ob_task_template_id');
    }
}
