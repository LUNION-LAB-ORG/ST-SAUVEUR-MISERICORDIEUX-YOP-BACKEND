<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use SoftDeletes;

    protected $table = 'notifications';

    protected $fillable = [
        'type',
        'icon',
        'title',
        'message',
        'related_type',
        'related_id',
        'link',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'bool',
        'read_at' => 'datetime',
    ];
}
