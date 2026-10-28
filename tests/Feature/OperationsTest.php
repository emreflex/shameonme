<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\ImportBatch;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\Reporting;
use App\Services\ShiftService;
use App\Services\WorkbookImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Department $petrol;

    protected Department $market;

    protected Employee $employee;

    protected ShiftType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->petrol = Department::where('code', 'petrol')->first();
        $this->market = Department::where('code', 'market')->first();
        $this->employee = Employee::create(['name' => 'Petrol Görevlisi', 'department_id' => $this->petrol->id]);
        $this->type = ShiftType::first();
        $this->admin = $this->actor('super_admin');
        $this->actingAs($this->admin);
    }

    private function actor(string $role, ?Employee $e = null): User
    {
        $u = new User(['name' => 'Test Kullanıcısı', 'email' => uniqid().'@test.local', 'password' => 'TestSecret12345']);
        $u->forceFill(['role_id' => Role::where('name', $role)->first()->id, 'department_id' => $e?->department_id, 'employee_id' => $e?->id])->save();

        return $u;
    }

    private function data(array $overrides = []): array
    {
        return array_merge(['department_id' => $this->petrol->id, 'employee_id' => $this->employee->id, 'shift_type_id' => $this->type->id, 'date' => today()->format('Y-m-d'), 'sales' => '1000', 'pos_kuveyt' => '200', 'counted_cash' => '800', 'status' => 'pending'], $overrides);
    }

    private function create(array $overrides = []): Shift
    {
        return app(ShiftService::class)->save($this->data($overrides));
    }

    public function test_real_shift_is_saved_with_recalculated_totals_and_audit(): void
    {
        $this->post('/shifts', $this->data(['pos_total' => '1', 'difference' => '999']))->assertRedirect();
        $s = Shift::firstOrFail();
        $this->assertSame('200.00', $s->financial->pos_total);
        $this->assertSame('0.00', $s->financial->difference);
        $this->assertDatabaseHas('audit_logs', ['action' => 'create', 'subject_id' => $s->id]);
    }

    public function test_guest_cannot_read_financial_data(): void
    {
        auth()->logout();
        $this->get('/')->assertRedirect('/login');
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_login_regenerates_session_and_logout_works(): void
    {
        auth()->logout();
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'TestSecret12345'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->admin);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_staff_cannot_change_another_employee_or_department(): void
    {
        $s = $this->create();
        $e = Employee::create(['name' => 'Market Görevlisi', 'department_id' => $this->market->id]);
        $staff = $this->actor('staff', $e);
        $this->actingAs($staff);
        $this->get('/shifts/'.$s->id.'/edit')->assertForbidden();
        $this->post('/shifts', $this->data())->assertForbidden();
        $this->get('/employees')->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/imports')->assertForbidden();
        $this->get('/activity')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->post('/backup', ['password' => 'TestSecret12345'])->assertForbidden();
    }

    public function test_staff_sees_only_own_records_and_cannot_approve(): void
    {
        $s = $this->create();
        $staff = $this->actor('staff', $this->employee);
        $this->actingAs($staff);
        $this->get('/shifts')->assertOk()->assertDontSee($s->number);
        $own = $this->create(['date' => today()->subDay()->format('Y-m-d'), 'status' => 'draft']);
        $this->post('/shifts/'.$own->id.'/transition', ['version' => 1, 'action' => 'approve'])->assertForbidden();
    }

    public function test_closed_shifts_require_manager_reason_and_staff_cannot_edit(): void
    {
        $s = $this->create();
        $service = app(ShiftService::class);
        $service->transition($s, 'approve', 1, null);
        $service->transition($s, 'close', 2, null);
        $s->refresh();
        $this->assertSame('closed', $s->status);
        $this->put('/shifts/'.$s->id, $this->data(['version' => 3, 'status' => 'review']))->assertSessionHasErrors('reason');
        $staff = $this->actor('staff', $this->employee);
        $s->created_by = $staff->id;
        $s->save();
        $this->actingAs($staff);
        $this->put('/shifts/'.$s->id, $this->data(['version' => 3, 'status' => 'draft']))->assertForbidden();
        $this->actingAs($this->admin);
        $this->put('/shifts/'.$s->id, $this->data(['version' => 3, 'reason' => 'Sayım düzeltildi', 'status' => 'review', 'counted_cash' => '790']))->assertRedirect();
        $this->assertSame('review', $s->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'update', 'reason' => 'Sayım düzeltildi']);
    }

    public function test_optimistic_lock_prevents_lost_update(): void
    {
        $s = $this->create();
        $this->put('/shifts/'.$s->id, $this->data(['version' => 1, 'sales' => '1100']))->assertRedirect();
        $this->put('/shifts/'.$s->id, $this->data(['version' => 1, 'sales' => '1200']))->assertStatus(409);
        $this->assertSame('1100.00', $s->fresh()->financial->sales);
    }

    public function test_duplicate_requires_privileged_reason(): void
    {
        $this->create();
        $this->post('/shifts', $this->data())->assertSessionHasErrors('duplicate_reason');
        $this->post('/shifts', $this->data(['duplicate_reason' => 'Ek teslim işlemi']))->assertRedirect();
        $this->assertSame(2, Shift::count());
    }

    public function test_pending_requires_actual_cash_count(): void
    {
        $this->post('/shifts', $this->data(['counted_cash' => null]))->assertSessionHasErrors('counted_cash');
        $this->assertSame(0, Shift::count());
    }

    public function test_financial_validation_rejects_negative_and_decimal_overprecision(): void
    {
        foreach (['-1', '1.001', 'NaN', '1e8'] as $value) {
            $this->post('/shifts', $this->data(['sales' => $value]))->assertSessionHasErrors('sales');
        }$this->assertSame(0, Shift::count());
    }

    public function test_department_employee_mismatch_is_refused(): void
    {
        $this->post('/shifts', $this->data(['department_id' => $this->market->id]))->assertNotFound();
    }

    public function test_approval_and_soft_delete_are_controlled(): void
    {
        $s = $this->create(['counted_cash' => '790']);
        $this->post('/shifts/'.$s->id.'/transition', ['action' => 'approve', 'version' => 1])->assertSessionHasErrors('reason');
        $this->post('/shifts/'.$s->id.'/transition', ['action' => 'approve', 'version' => 1, 'reason' => 'Fark incelemeye alındı'])->assertRedirect();
        $this->delete('/shifts/'.$s->id, ['version' => 2, 'reason' => 'Yanlış kayıt'])->assertForbidden();
        $draft = $this->create(['status' => 'draft', 'date' => today()->subDay()->format('Y-m-d')]);
        $this->delete('/shifts/'.$draft->id, ['version' => 1, 'reason' => 'Yanlış kayıt'])->assertRedirect();
        $this->assertSoftDeleted('shifts', ['id' => $draft->id]);
    }

    public function test_daily_monthly_reports_isolate_departments_and_uncounted_shifts(): void
    {
        $this->create();
        $this->create(['date' => today()->subDay()->format('Y-m-d'), 'counted_cash' => null, 'status' => 'counting']);
        $e = Employee::create(['name' => 'Diğer Görevli', 'department_id' => $this->market->id]);
        $this->create(['department_id' => $this->market->id, 'employee_id' => $e->id, 'sales' => '500', 'counted_cash' => '300']);
        $r = Request::create('/?period=today');
        $r->setUserResolver(fn () => $this->admin);
        $service = app(Reporting::class);
        $summary = $service->summary($service->query($r)->get());
        $this->assertSame('1500.00', $summary['totals']['sales']);
        $this->assertSame(0, $summary['cash']['short']);
        $r->merge(['department' => 'petrol']);
        $this->assertSame('1000.00', $service->summary($service->query($r)->get())['totals']['sales']);
        $manager = $this->actor('manager', $this->employee);
        $r->setUserResolver(fn () => $manager);
        $r->merge(['department' => 'market']);
        $this->assertCount(0, $service->query($r)->get());
        $this->get('/daily')->assertOk()->assertSee('Günlük kasa kontrolü');
        $this->get('/reports?period=year')->assertOk()->assertSee('2.500,00');
    }

    public function test_all_authorized_pages_render_and_exports_are_real(): void
    {
        $s = $this->create();
        foreach (['/', '/shifts', '/shifts/create', '/shifts/'.$s->id.'/edit', '/daily', '/reports', '/employees', '/employees/'.$this->employee->id, '/settings', '/users', '/imports', '/activity', '/notifications'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/reports?output=pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/reports?output=xlsx')->assertOk()->assertDownload('vardiyalar.xlsx');
    }

    public function test_backup_is_encrypted_and_password_protected(): void
    {
        $this->post('/backup', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $response = $this->post('/backup', ['password' => 'TestSecret12345']);
        $response->assertOk();
        $payload = json_decode(Crypt::decryptString($response->streamedContent()), true);
        $this->assertSame('istasyon-logical-v1', $payload['format']);
        $this->assertArrayHasKey('users', $payload['tables']);
    }

    private function workbook(bool $blank = false): string
    {
        $b = new Spreadsheet;
        $s = $b->getActiveSheet();
        $s->setTitle('VARDİYA');
        $s->setCellValue('B3', 'İçe Aktarılan');
        $s->setCellValue('B25', 'Market Aktarılan');
        if (! $blank) {
            $s->setCellValue('C3', 1000);
            $s->setCellValue('H3', 200);
            $s->setCellValue('M3', 800);
            $s->setCellValue('C25', 500);
            $s->setCellValue('D25', 20);
            $s->setCellValue('F25', 80);
            $s->setCellValue('M25', 400);
        }$path = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($b))->save($path);

        return $path;
    }

    public function test_excel_preview_does_not_import_name_only_rows_or_formula_totals(): void
    {
        $path = $this->workbook(true);
        try {
            $p = app(WorkbookImporter::class)->preview($path);
            $this->assertSame(15, $p['skipped']);
            $this->assertCount(0, $p['rows']);
            $this->assertSame(0, Shift::count());
        } finally {
            unlink($path);
        }
    }

    public function test_excel_requires_missing_metadata_and_recalculates_both_departments(): void
    {
        $path = $this->workbook();
        try {
            $service = app(WorkbookImporter::class);
            $p = $service->preview($path);
            $this->assertSame(2, $p['errors']);
            $p = $service->preview($path, today()->format('Y-m-d'), $this->type->name);
            $this->assertSame(0, $p['errors']);
            $this->assertSame('800.00', $p['rows'][0]['calculated']['expected_cash']);
            $this->assertSame('400.00', $p['rows'][1]['calculated']['expected_cash']);
        } finally {
            unlink($path);
        }
    }

    public function test_import_confirm_is_idempotent_and_requires_ownership(): void
    {
        $path = $this->workbook();
        try {
            $p = app(WorkbookImporter::class)->preview($path, today()->format('Y-m-d'), $this->type->name);
            $batch = ImportBatch::create(['user_id' => $this->admin->id, 'filename' => 'test.xlsx', 'checksum' => hash_file('sha256', $path), 'preview' => $p, 'skipped' => $p['skipped'], 'errors' => 0]);
            $this->post('/imports/'.$batch->id.'/confirm', ['confirm' => 1])->assertRedirect();
            $this->assertSame(2, Shift::count());
            $this->assertSame(2, $batch->fresh()->success);
            $this->post('/imports/'.$batch->id.'/confirm', ['confirm' => 1])->assertStatus(409);
            $second = ImportBatch::create(['user_id' => $this->admin->id, 'filename' => 'same.xlsx', 'checksum' => hash_file('sha256', $path), 'preview' => $p, 'skipped' => $p['skipped'], 'errors' => 0]);
            $this->post('/imports/'.$second->id.'/confirm', ['confirm' => 1])->assertRedirect();
            $this->assertSame(2, Shift::count());
            $this->assertSame(15, $second->fresh()->skipped);
        } finally {
            unlink($path);
        }
    }

    public function test_excel_output_round_trip_preserves_decimal_zero_and_empty_counts(): void
    {
        $this->create(['counted_cash' => '0', 'sales' => '1000.10']);
        $response = $this->get('/reports?output=xlsx');
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'export');
        try {
            file_put_contents($path, $response->streamedContent());
            $preview = app(WorkbookImporter::class)->preview($path);
            $this->assertSame(0, $preview['errors']);
            $this->assertCount(1, $preview['rows']);
            $this->assertSame('0.00', $preview['rows'][0]['data']['counted_cash']);
            $this->assertSame('1000.10', $preview['rows'][0]['data']['sales']);
        } finally {
            unlink($path);
        }
    }

    public function test_report_period_breakdowns_and_filters_are_exact(): void
    {
        $this->create(['date' => '2026-01-02']);
        $this->create(['date' => '2026-02-03', 'sales' => '2000']);
        $r = Request::create('/?period=custom&from=2026-01-01&to=2026-12-31');
        $r->setUserResolver(fn () => $this->admin);
        $service = app(Reporting::class);
        $rows = $service->query($r)->get();
        $months = $service->breakdown($rows, 'monthly');
        $this->assertSame('1000.00', $months['2026-01']['sales']);
        $this->assertSame('2000.00', $months['2026-02']['sales']);
        $this->assertSame('3000.00', $service->breakdown($rows, 'yearly')['2026']['sales']);
        $this->assertSame('400.00', $service->breakdown($rows, 'pos')['Kuveyt']['pos_total']);
        $r->merge(['from' => '2026-02-01', 'to' => '2026-02-28']);
        $this->assertCount(1, $service->query($r)->get());
    }

    public function test_department_manager_cannot_inspect_another_import_batch(): void
    {
        $manager = $this->actor('manager', $this->employee);
        $batch = ImportBatch::create(['user_id' => $this->admin->id, 'filename' => 'private.xlsx', 'checksum' => str_repeat('a', 64), 'preview' => ['rows' => [], 'skipped' => 0, 'errors' => 0]]);
        $this->actingAs($manager);
        $this->get('/imports/'.$batch->id)->assertForbidden();
        $this->post('/imports/'.$batch->id.'/confirm', ['confirm' => 1])->assertForbidden();
    }

    public function test_backup_restores_only_into_empty_target_without_overwriting(): void
    {
        $this->create();
        $backup = $this->post('/backup', ['password' => 'TestSecret12345'])->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'backup');
        file_put_contents($path, $backup);
        try {
            $this->artisan('app:restore-backup', ['file' => $path, '--confirm' => true])->assertExitCode(1);
            $this->assertSame(1, Shift::count());
            $tables = ['import_rows', 'import_batches', 'application_settings', 'notifications', 'audit_logs', 'shift_financial_entries', 'shifts', 'shift_types', 'users', 'employees', 'departments', 'roles'];
            foreach ($tables as $table) {
                DB::table($table)->delete();
            }
            $this->artisan('app:restore-backup', ['file' => $path, '--confirm' => true])->assertExitCode(0);
            $this->assertSame(1, Shift::count());
            $this->assertSame('1000.00', Shift::first()->financial->sales);
            $this->assertTrue(Hash::check('TestSecret12345', User::findOrFail($this->admin->id)->password));
        } finally {
            unlink($path);
        }
    }

    public function test_excel_upload_previews_before_confirming(): void
    {
        $path = $this->workbook();
        try {
            $upload = new UploadedFile($path, 'source.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $this->post('/imports', ['file' => $upload, 'date' => today()->format('Y-m-d'), 'shift_type_id' => $this->type->id])->assertRedirect();
            $this->assertSame(0, Shift::count());
            $batch = ImportBatch::firstOrFail();
            $this->get('/imports/'.$batch->id)->assertOk()->assertSee('Henüz finansal kayıt oluşturulmadı');
            $this->post('/imports/'.$batch->id.'/confirm', ['confirm' => 1])->assertRedirect();
            $this->assertSame(2, Shift::count());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
