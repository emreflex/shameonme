<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\ImportBatch;
use App\Models\ShiftType;
use App\Services\Audit;
use App\Services\ShiftService;
use App\Services\WorkbookImporter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    public function index(Request $r)
    {
        abort_if($r->user()->isStaff(), 403);

        return view('imports.index', ['batch' => null]);
    }

    public function preview(Request $r, WorkbookImporter $importer)
    {
        abort_if($r->user()->isStaff(), 403);
        $d = $r->validate(['file' => 'required|file|max:5120|mimes:xlsx,xlsm', 'date' => 'nullable|date_format:Y-m-d', 'shift_type_id' => 'nullable|exists:shift_types,id']);
        $file = $r->file('file');
        $preview = $importer->preview($file->getRealPath(), $d['date'] ?? null, isset($d['shift_type_id']) ? ShiftType::findOrFail($d['shift_type_id'])->name : null);
        if (! $r->user()->isSuper()) {
            foreach ($preview['rows'] as &$row) {
                if (($row['data']['department'] ?? null) !== $r->user()->department->code) {
                    $row['error'] = 'Bu departmana aktarım yetkiniz yok.';
                }
            }
        }
        $preview['errors'] = count(array_filter($preview['rows'], fn ($row) => $row['error'] !== null));
        $batch = ImportBatch::create(['user_id' => $r->user()->id, 'filename' => mb_substr(basename($file->getClientOriginalName()), 0, 200), 'checksum' => hash_file('sha256', $file->getRealPath()), 'preview' => $preview, 'skipped' => $preview['skipped'], 'errors' => $preview['errors']]);

        return redirect()->route('imports.show', $batch);
    }

    public function show(Request $r, ImportBatch $batch)
    {
        abort_unless(! $r->user()->isStaff() && $batch->user_id === $r->user()->id, 403);

        return view('imports.index', compact('batch'));
    }

    public function confirm(Request $r, ImportBatch $batch, ShiftService $service)
    {
        abort_unless(! $r->user()->isStaff() && $batch->user_id === $r->user()->id, 403);
        $r->validate(['confirm' => 'accepted']);
        DB::transaction(function () use ($batch, $service, $r) {
            $batch = ImportBatch::lockForUpdate()->findOrFail($batch->id);
            abort_unless($batch->status === 'preview', 409, 'Bu aktarım daha önce işlendi.');
            $success = 0;
            $skipped = $batch->skipped;
            $errors = $batch->errors;
            $preview = $batch->preview;
            foreach ($preview['rows'] as &$row) {
                if ($row['error']) {
                    continue;
                }
                try {
                    $result = DB::transaction(function () use ($row, $service, $r, $batch) {
                        $department = Department::where('code', $row['data']['department'])->lockForUpdate()->firstOrFail();
                        abort_unless($r->user()->manages($department->id), 403);
                        if (DB::table('import_rows')->where('fingerprint', $row['fingerprint'])->exists()) {
                            return false;
                        }
                        $employee = Employee::firstOrCreate(['department_id' => $department->id, 'name' => $row['data']['employee']]);
                        $type = ShiftType::where('name', $row['data']['type'])->first();
                        if (! $type) {
                            throw new \RuntimeException('Vardiya türü tanımlı değil. Önce ayarlardan ekleyin.');
                        }
                        $s = $service->save($row['data'] + ['department_id' => $department->id, 'employee_id' => $employee->id, 'shift_type_id' => $type->id, 'status' => 'draft']);
                        DB::table('import_rows')->insert(['import_batch_id' => $batch->id, 'fingerprint' => $row['fingerprint'], 'shift_id' => $s->id]);

                        return true;
                    });
                    if ($result) {
                        $success++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $row['error'] = $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : ($e instanceof QueryException ? 'Veri bütünlüğü hatası; satır aktarılmadı.' : $e->getMessage());
                }
            }
            $batch->update(['status' => 'completed', 'success' => $success, 'skipped' => $skipped, 'errors' => $errors, 'preview' => $preview]);
            Audit::record('import', $batch, null, ['success' => $success, 'skipped' => $skipped, 'errors' => $errors]);
        });

        return back()->with('success', 'Aktarım tamamlandı; sonuçları aşağıda inceleyin.');
    }
}
