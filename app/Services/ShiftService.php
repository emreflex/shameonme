<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationNotification;
use App\Models\Shift;
use App\Models\ShiftType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShiftService
{
    public function save(array $data, ?Shift $existing = null): Shift
    {
        return DB::transaction(function () use ($data, $existing) {
            $user = auth()->user();
            if ($existing) {
                $s = Shift::lockForUpdate()->findOrFail($existing->id);
                Gate::authorize('update', $s);
                if ((int) ($data['version'] ?? 0) !== $s->version) {
                    abort(409, 'Kayıt değişti. Sayfayı yenileyip tekrar deneyin.');
                }
                if (in_array($s->status, ['approved', 'closed']) && blank($data['reason'] ?? null)) {
                    throw ValidationException::withMessages(['reason' => 'Onaylı/kapalı kayıt düzeltmesi için gerekçe zorunludur.']);
                }
            } else {
                $s = new Shift;
            }
            $department = Department::findOrFail($data['department_id']);
            if (! $user->isSuper() && $department->id !== $user->department_id) {
                abort(403);
            }
            if ($existing && $department->id !== $s->department_id) {
                abort(422, 'Kayıt departmanı değiştirilemez.');
            }
            $employee = Employee::where('department_id', $department->id)->lockForUpdate()->findOrFail($data['employee_id']);
            if (! $employee->active && (! $existing || $s->employee_id !== $employee->id)) {
                throw ValidationException::withMessages(['employee_id' => 'Personel pasif.']);
            }
            if ($user->isStaff() && $employee->id !== $user->employee_id) {
                abort(403);
            }
            $type = ShiftType::findOrFail($data['shift_type_id']);
            if (! $type->active && (! $existing || $s->shift_type_id !== $type->id)) {
                throw ValidationException::withMessages(['shift_type_id' => 'Vardiya türü pasif.']);
            }
            $before = $existing ? $s->load('financial')->toArray() : null;
            $duplicate = Shift::withTrashed()->where('department_id', $department->id)->where('employee_id', $employee->id)->whereDate('date', $data['date'])->where('shift_type_id', $type->id)->when($existing, fn ($q) => $q->where('id', '!=', $s->id));
            $max = $duplicate->max('sequence');
            if ($max && (! $existing || $s->employee_id !== $employee->id || $s->date->format('Y-m-d') !== $data['date'] || $s->shift_type_id !== $type->id)) {
                if ($user->isStaff() || blank($data['duplicate_reason'] ?? null)) {
                    throw ValidationException::withMessages(['duplicate_reason' => 'Mükerrer vardiya: yönetici gerekçesi zorunlu.']);
                }
                $s->sequence = $max + 1;
            } elseif (! $existing) {
                $s->sequence = 1;
            }
            $financial = app(ShiftCalculator::class)->calculate($department->code, $data);
            $status = $data['status'] ?? 'draft';
            if (! in_array($status, ['draft', 'counting', 'review', 'pending'])) {
                abort(422);
            }
            if ($financial['counted_cash'] === null && $status === 'pending') {
                throw ValidationException::withMessages(['counted_cash' => 'Onaya göndermek için nakit sayımı gerekli.']);
            }
            $s->fill(['number' => $s->number ?? (string) Str::uuid(), 'department_id' => $department->id, 'employee_id' => $employee->id, 'shift_type_id' => $type->id, 'date' => $data['date'], 'notes' => $data['notes'] ?? null, 'status' => $status, 'review_status' => $user->isStaff() ? 'unchecked' : ($data['review_status'] ?? 'unchecked'), 'created_by' => $s->created_by ?? $user->id, 'duplicate_reason' => $data['duplicate_reason'] ?? $s->duplicate_reason, 'version' => $existing ? $s->version + 1 : 1]);
            $s->save();
            $s->financial()->updateOrCreate([], $financial);
            OperationNotification::where('shift_id', $s->id)->delete();
            if (in_array($financial['cash_status'], ['short', 'over']) || $status !== 'closed') {
                OperationNotification::create(['department_id' => $s->department_id, 'shift_id' => $s->id, 'message' => $financial['cash_status'] === 'short' ? 'Kasa eksiği: kontrol gerekli' : ($financial['cash_status'] === 'over' ? 'Kasa fazlası: kontrol gerekli' : 'Kapanmamış vardiya')]);
            }
            Audit::record($existing ? 'update' : 'create', $s, $before, $s->fresh('financial')->toArray(), $data['reason'] ?? $data['duplicate_reason'] ?? null);

            return $s;
        }, 3);
    }

    public function transition(Shift $shift, string $action, int $version, ?string $reason): void
    {
        DB::transaction(function () use ($shift, $action, $version, $reason) {
            $s = Shift::lockForUpdate()->findOrFail($shift->id);
            Gate::authorize('approve', $s);
            if ($s->version !== $version) {
                abort(409, 'Kayıt değişti, yeniden yükleyin.');
            }
            $before = $s->toArray();
            $cash = $s->financial;
            if ($action === 'approve') {
                if (! in_array($s->status, ['pending', 'review']) || $cash->counted_cash === null) {
                    abort(422, 'Yalnızca sayımı yapılmış ve onaya/kontrole gönderilmiş kayıt onaylanır.');
                }
                if ($cash->cash_status !== 'balanced' && blank($reason)) {
                    throw ValidationException::withMessages(['reason' => 'Kasa farkı olan kaydın onayı için gerekçe zorunlu.']);
                }
                $s->status = 'approved';
            } elseif ($action === 'close') {
                if ($s->status !== 'approved') {
                    abort(422, 'Önce vardiyayı onaylayın.');
                }$s->status = 'closed';
                OperationNotification::where('shift_id', $s->id)->delete();
            } else {
                abort(422);
            }
            $s->version++;
            $s->save();
            Audit::record($action, $s, $before, $s->toArray(), $reason);
        });
    }
}
