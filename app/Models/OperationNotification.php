<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationNotification extends Model
{
    protected $guarded = [];

    protected $table = 'notifications';

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
