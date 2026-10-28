<?php

namespace App\Services;

use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class Reporting
{
    public function range(Request $r): array
    {
        $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'period' => 'nullable|in:today,yesterday,week,month,last_month,year,custom', 'department' => 'nullable|in:petrol,market', 'employee_id' => 'nullable|integer', 'search' => 'nullable|string|max:100', 'sort' => 'nullable|in:date,sales,difference', 'direction' => 'nullable|in:asc,desc', 'status' => 'nullable|in:draft,counting,review,pending,approved,closed', 'report' => 'nullable|in:daily,weekly,monthly,yearly,petrol,market,pos,employee,difference,expense,status']);
        $now = Carbon::today();
        [$from,$to] = match ($r->input('period', 'month')) {
            'today' => [$now->copy(), $now->copy()], 'yesterday' => [$now->copy()->subDay(), $now->copy()->subDay()],
            'week' => [$now->copy()->subDays(6), $now->copy()], 'year' => [$now->copy()->startOfYear(), $now->copy()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'custom' => [Carbon::parse($r->input('from') ?: $now->format('Y-m-d')), Carbon::parse($r->input('to') ?: $now->format('Y-m-d'))],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]
        };
        abort_if($from->diffInDays($to) > 3660, 422, 'En fazla 10 yıllık aralık seçin.');

        return [$from->format('Y-m-d'), $to->format('Y-m-d')];
    }

    public function query(Request $r)
    {
        [$from,$to] = $this->range($r);
        $q = Shift::visibleTo($r->user())->with(['financial', 'department', 'employee', 'shiftType'])->whereDate('date', '>=', $from)->whereDate('date', '<=', $to);
        $dep = in_array($r->report, ['petrol', 'market']) ? $r->report : $r->department;
        if ($dep) {
            $q->whereHas('department', fn ($q) => $q->where('code', $dep));
        }
        if ($r->employee_id) {
            $q->where('employee_id', $r->employee_id);
        }
        if ($r->status) {
            $q->where('status', $r->status);
        }
        if ($r->search) {
            $q->whereHas('employee', fn ($q) => $q->where('name', 'like', '%'.$r->search.'%'));
        }
        if ($r->report === 'difference') {
            $q->whereHas('financial', fn ($q) => $q->whereNotNull('difference')->where('difference', '!=', 0));
        }

        return $q;
    }

    public function breakdown($rows, string $kind): array
    {
        if ($kind === 'pos') {
            $sum = $this->summary($rows);
            $out = [];
            foreach (['Kuveyt' => 'pos_kuveyt', 'Garanti' => 'pos_garanti', 'Ziraat' => 'pos_ziraat', 'Diğer' => 'pos_other'] as $label => $key) {
                $out[$label] = ['count' => null, 'sales' => null, 'pos_total' => $sum['totals'][$key], 'fast' => null, 'expense' => null, 'difference' => null];
            }

            return $out;
        }
        $groups = $rows->groupBy(fn ($s) => match ($kind) {
            'weekly' => $s->date->copy()->startOfWeek()->format('d.m.Y').' haftası',
            'monthly' => $s->date->format('Y-m'), 'yearly' => $s->date->format('Y'),
            'employee' => $s->department->name.' / '.$s->employee->name,
            'status' => __('ui.status.'.$s->status), 'expense' => $s->department->name,
            default => $s->date->format('d.m.Y').' / '.$s->department->name
        });
        $out = [];
        foreach ($groups as $label => $group) {
            $sum = $this->summary($group);
            $out[$label] = ['count' => $sum['count']] + array_intersect_key($sum['totals'], array_flip(['sales', 'pos_total', 'fast', 'expense', 'difference']));
        }

        return $out;
    }

    public function summary($rows): array
    {
        $totals = array_fill_keys(array_merge(ShiftCalculator::INPUTS, ['pos_total', 'expected_cash', 'difference']), 0);
        $cash = ['short' => 0, 'over' => 0, 'balanced' => 0, 'waiting' => 0];
        $pending = 0;
        $departments = ['petrol' => 0, 'market' => 0];
        $trend = [];
        $monthly = [];
        $people = [];
        $expenses = ['petrol' => 0, 'market' => 0];
        foreach ($rows as $s) {
            foreach ($totals as $key => $_) {
                $totals[$key] += Money::signedCents($s->financial->$key ?? '0');
            }
            $cash[$s->financial->cash_status]++;
            $pending += ($s->status === 'pending' ? 1 : 0);
            $sales = Money::cents($s->financial->sales);
            $dep = $s->department->code;
            $departments[$dep] += $sales;
            $expenses[$dep] += Money::cents($s->financial->expense);
            $day = $s->date->format('Y-m-d');
            $month = $s->date->format('Y-m');
            $trend[$day] = ($trend[$day] ?? 0) + $sales;
            $monthly[$month] = ($monthly[$month] ?? 0) + $sales;
            $name = $s->department->name.' / '.$s->employee->name;
            $people[$name] = ($people[$name] ?? 0) + $sales;
        }
        ksort($trend);
        ksort($monthly);

        return ['totals' => array_map(fn ($c) => Money::decimal($c), $totals), 'cash' => $cash, 'pending' => $pending, 'count' => count($rows), 'departments' => $departments, 'trend' => $trend, 'monthly' => $monthly, 'people' => $people, 'expenses' => $expenses];
    }
}
