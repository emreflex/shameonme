<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    protected $guarded = [];

    use SoftDeletes;

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shiftType()
    {
        return $this->belongsTo(ShiftType::class);
    }

    public function financial()
    {
        return $this->hasOne(ShiftFinancialEntry::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo($q, User $u)
    {
        if (! $u->isSuper()) {
            $q->where('department_id', $u->department_id);
        } if ($u->isStaff()) {
            $q->where('created_by', $u->id);
        }

        return $q;
    }
}
