<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\Audit;
use App\Services\Reporting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ManagementController extends Controller
{
    public function employees(Request $r)
    {
        abort_if($r->user()->isStaff(), 403);
        $employees = Employee::with('department')->when(! $r->user()->isSuper(), fn ($q) => $q->where('department_id', $r->user()->department_id))->when($r->search, fn ($q) => $q->where('name', 'like', '%'.$r->search.'%'))->orderBy('name')->paginate(25)->withQueryString();
        $departments = Department::when(! $r->user()->isSuper(), fn ($q) => $q->where('id', $r->user()->department_id))->get();

        return view('management.employees', compact('employees', 'departments'));
    }

    public function saveEmployee(Request $r, ?Employee $employee = null)
    {
        abort_if($r->user()->isStaff(), 403);
        $d = $r->validate(['name' => ['required', 'string', 'max:150', Rule::unique('employees')->where('department_id', $r->input('department_id'))->ignore($employee?->id)], 'department_id' => 'required|integer|exists:departments,id', 'active' => 'required|boolean']);
        abort_unless($r->user()->manages((int) $d['department_id']), 403);
        if ($employee) {
            abort_unless($r->user()->manages($employee->department_id), 403);
        }
        DB::transaction(function () use ($employee, $d) {
            $before = $employee?->toArray();
            if ($employee) {
                $employee = Employee::lockForUpdate()->findOrFail($employee->id);
                if ($employee->department_id !== (int) $d['department_id'] && ($employee->shifts()->withTrashed()->exists() || User::where('employee_id', $employee->id)->exists())) {
                    abort(422, 'Geçmişi/hesabı olan personelin departmanı değiştirilemez. Diğer departmanda ayrı personel oluşturun.');
                }
            } $e = $employee ?? new Employee;
            $e->fill($d);
            $e->save();
            Audit::record('employee.save', $e, $before, $e->toArray());
        });

        return back()->with('success', 'Personel kaydedildi.');
    }

    public function employee(Request $r, Employee $employee, Reporting $report)
    {
        abort_if($r->user()->isStaff(), 403);
        abort_unless($r->user()->manages($employee->department_id), 403);
        $r->merge(['employee_id' => $employee->id]);
        $rows = $report->query($r)->get();

        return view('management.employee', ['employee' => $employee, 'rows' => $rows, 'summary' => $report->summary($rows)]);
    }

    public function settings(Request $r)
    {
        abort_unless($r->user()->isSuper(), 403);

        return view('management.settings', ['types' => ShiftType::all(), 'business' => ApplicationSetting::where('key', 'business_name')->value('value')]);
    }

    public function saveSettings(Request $r)
    {
        abort_unless($r->user()->isSuper(), 403);
        $d = $r->validate(['business_name' => 'required|string|max:150']);
        DB::transaction(function () use ($d) {
            $s = ApplicationSetting::where('key', 'business_name')->firstOrFail();
            $before = $s->toArray();
            $s->update(['value' => $d['business_name']]);
            Audit::record('settings', $s, $before, $s->toArray());
        });

        return back()->with('success', 'Ayarlar kaydedildi.');
    }

    public function saveType(Request $r, ?ShiftType $type = null)
    {
        abort_unless($r->user()->isSuper(), 403);
        $d = $r->validate(['name' => ['required', 'string', 'max:100', Rule::unique('shift_types')->ignore($type?->id)], 'active' => 'required|boolean']);
        DB::transaction(function () use ($d, $type) {
            $s = $type ?? new ShiftType;
            $before = $s->exists ? $s->toArray() : null;
            $s->fill($d)->save();
            Audit::record('shift_type', $s, $before, $s->toArray());
        });

        return back()->with('success', 'Vardiya türü kaydedildi.');
    }

    public function users(Request $r)
    {
        abort_unless($r->user()->isSuper(), 403);

        return view('management.users', ['users' => User::with(['role', 'department'])->paginate(25), 'roles' => Role::all(), 'departments' => Department::all(), 'employees' => Employee::with('department')->get()]);
    }

    public function saveUser(Request $r, ?User $user = null)
    {
        abort_unless($r->user()->isSuper(), 403);
        $d = $r->validate(['name' => 'required|string|max:150', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)], 'password' => [$user ? 'nullable' : 'required', Password::min(12)->letters()->numbers()], 'role_id' => 'required|exists:roles,id', 'department_id' => 'nullable|exists:departments,id', 'employee_id' => 'nullable|exists:employees,id', 'active' => 'required|boolean']);
        $role = Role::findOrFail($d['role_id']);
        if ($role->name !== 'super_admin' && empty($d['department_id'])) {
            abort(422, 'Departman gerekli.');
        }
        if ($role->name === 'staff' && empty($d['employee_id'])) {
            abort(422, 'Personel hesabı için personel eşlemesi gerekli.');
        }
        if (! empty($d['employee_id'])) {
            $e = Employee::findOrFail($d['employee_id']);
            abort_unless($e->department_id === (int) $d['department_id'], 422, 'Personel ve hesap departmanı eşleşmeli.');
        }
        if ($user?->id === $r->user()->id && ($role->name !== 'super_admin' || ! $d['active'])) {
            abort(422, 'Kendi yönetici erişiminizi kaldıramazsınız.');
        }
        DB::transaction(function () use ($user, $d) {
            $u = $user ?? new User;
            $before = $user?->toArray();
            if (empty($d['password'])) {
                unset($d['password']);
            }$u->forceFill($d)->save();
            Audit::record('user.save', $u, $before, $u->toArray());
        });

        return back()->with('success', 'Kullanıcı kaydedildi.');
    }

    public function backup(Request $r)
    {
        abort_unless($r->user()->isSuper(), 403);
        $r->validate(['password' => 'required|current_password']);
        $tables = ['roles', 'departments', 'employees', 'users', 'shift_types', 'shifts', 'shift_financial_entries', 'audit_logs', 'notifications', 'application_settings', 'import_batches', 'import_rows'];
        $data = DB::transaction(function () use ($tables) {
            $result = ['format' => 'istasyon-logical-v1', 'created_at' => now()->toIso8601String(), 'tables' => []];
            foreach ($tables as $t) {
                $result['tables'][$t] = DB::table($t)->orderBy('id')->get()->toArray();
            }

            return $result;
        });
        Audit::record('backup', $r->user(), null, ['tables' => count($tables)]);
        $encrypted = Crypt::encryptString(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return response()->streamDownload(fn () => print ($encrypted), 'istasyon-'.now()->format('Ymd-His').'.backup', ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store']);
    }
}
