<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftType;
use App\Services\Audit;
use App\Services\ShiftCalculator;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ShiftController extends Controller
{
    public function index(Request $r)
    {
        $q = Shift::visibleTo($r->user())->with(['employee', 'department', 'shiftType', 'financial']);
        if ($r->filled('department')) {
            $q->whereHas('department', fn ($q) => $q->where('code', $r->department));
        }
        if ($r->filled('cash_status')) {
            $r->validate(['cash_status' => 'in:waiting,short,over,balanced']);
            $q->whereHas('financial', fn ($q) => $q->where('cash_status', $r->cash_status));
        }
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }
        if ($r->filled('search')) {
            $q->whereHas('employee', fn ($q) => $q->where('name', 'like', '%'.$r->string('search').'%'));
        }
        if ($r->filled('date')) {
            $r->validate(['date' => 'date_format:Y-m-d']);
            $q->whereDate('date', $r->date);
        }
        $shifts = $q->orderByDesc('date')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('shifts.index', compact('shifts'));
    }

    private function options(Request $r, ?Shift $shift = null)
    {
        $departments = Department::when(! $r->user()->isSuper(), fn ($q) => $q->where('id', $r->user()->department_id))->get();
        $employees = Employee::with('department')->when(! $r->user()->isSuper(), fn ($q) => $q->where('department_id', $r->user()->department_id))->when($r->user()->isStaff(), fn ($q) => $q->where('id', $r->user()->employee_id))->where(fn ($q) => $q->where('active', true)->when($shift, fn ($q) => $q->orWhere('id', $shift->employee_id)))->orderBy('name')->get();
        $types = ShiftType::where(fn ($q) => $q->where('active', true)->when($shift, fn ($q) => $q->orWhere('id', $shift->shift_type_id)))->get();

        return compact('departments', 'employees', 'types', 'shift');
    }

    public function create(Request $r)
    {
        return view('shifts.form', $this->options($r));
    }

    public function edit(Request $r, Shift $shift)
    {
        Gate::authorize('view', $shift);
        $shift->load('financial');

        return view('shifts.form', $this->options($r, $shift) + ['logs' => AuditLog::where('subject_type', 'Shift')->where('subject_id', $shift->id)->latest('id')->get()]);
    }

    private function validated(Request $r): array
    {
        $rules = ['department_id' => 'required|integer|exists:departments,id', 'employee_id' => 'required|integer|exists:employees,id', 'shift_type_id' => 'required|integer|exists:shift_types,id', 'date' => 'required|date_format:Y-m-d', 'status' => ['required', Rule::in(['draft', 'counting', 'review', 'pending'])], 'notes' => 'nullable|string|max:4000', 'reason' => 'nullable|string|min:5|max:2000', 'duplicate_reason' => 'nullable|string|min:5|max:1000', 'version' => 'nullable|integer|min:1', 'review_status' => ['nullable', Rule::in(['unchecked', 'checked', 'needs_review'])]];
        foreach (ShiftCalculator::INPUTS as $key) {
            $rules[$key] = ($key === 'sales' ? 'required' : 'nullable').'|regex:/^\d{1,11}(?:\.\d{1,2})?$/';
        }

        return $r->validate($rules);
    }

    public function store(Request $r, ShiftService $service)
    {
        $s = $service->save($this->validated($r));

        return redirect()->route('shifts.edit', $s)->with('success', 'Vardiya kaydedildi.');
    }

    public function update(Request $r, Shift $shift, ShiftService $service)
    {
        $service->save($this->validated($r), $shift);

        return back()->with('success', 'Vardiya güncellendi.');
    }

    public function transition(Request $r, Shift $shift, ShiftService $service)
    {
        $d = $r->validate(['action' => 'required|in:approve,close', 'version' => 'required|integer', 'reason' => 'nullable|string|min:5|max:2000']);
        $service->transition($shift, $d['action'], (int) $d['version'], $d['reason'] ?? null);

        return back()->with('success', 'Vardiya durumu güncellendi.');
    }

    public function destroy(Request $r, Shift $shift)
    {
        $d = $r->validate(['version' => 'required|integer', 'reason' => 'required|string|min:5|max:2000']);
        DB::transaction(function () use ($shift, $d) {
            $s = Shift::lockForUpdate()->findOrFail($shift->id);
            Gate::authorize('delete', $s);
            abort_unless($s->version === (int) $d['version'], 409);
            Audit::record('delete', $s, $s->load('financial')->toArray(), null, $d['reason']);
            $s->delete();
        });

        return redirect()->route('shifts.index')->with('success', 'Kayıt arşivlendi.');
    }
}
