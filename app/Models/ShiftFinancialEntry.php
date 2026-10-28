<?php

namespace App\Models;

use App\Services\ShiftCalculator;
use Illuminate\Database\Eloquent\Model;

class ShiftFinancialEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return array_fill_keys(array_merge(ShiftCalculator::INPUTS, ['pos_total', 'expected_cash', 'difference']), 'decimal:2');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
