<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\OperationNotification;
use App\Models\Shift;
use App\Services\Money;
use App\Services\Reporting;
use App\Services\ShiftCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function dashboard(Request $r, Reporting $reports)
    {
        $rows = $reports->query($r)->get();

        return view('dashboard', ['summary' => $reports->summary($rows), 'shifts' => $rows->sortByDesc('date')->take(8), 'range' => $reports->range($r)]);
    }

    public function index(Request $r, Reporting $reports)
    {
        $rows = $reports->query($r)->get();
        $sort = $r->input('sort', 'date');
        $rows = $rows->sortBy(fn ($s) => $sort === 'date' ? $s->date->format('Y-m-d') : Money::signedCents($s->financial->$sort ?? '0'), SORT_REGULAR, $r->input('direction', 'desc') === 'desc');
        $data = ['rows' => $rows, 'summary' => $reports->summary($rows), 'range' => $reports->range($r), 'breakdown' => $reports->breakdown($rows, $r->input('report', 'daily'))];
        if ($r->output === 'pdf') {
            return Pdf::loadView('reports.print', $data)->setPaper('a4', 'landscape')->download('vardiya-raporu.pdf');
        }
        if ($r->output === 'xlsx') {
            return $this->excel($rows, $data['breakdown']);
        }
        $data['rows'] = new LengthAwarePaginator($rows->forPage($r->integer('page', 1), 30)->values(), $rows->count(), 30, $r->integer('page', 1), ['path' => $r->url(), 'query' => $r->query()]);

        return view('reports.index', $data);
    }

    public function daily(Request $r, Reporting $reports)
    {
        $r->validate(['date' => 'nullable|date_format:Y-m-d']);
        $date = $r->input('date', today()->format('Y-m-d'));
        $r->merge(['period' => 'custom', 'from' => $date, 'to' => $date]);
        $rows = $reports->query($r)->get();
        $data = ['rows' => $rows, 'date' => $date, 'range' => [$date, $date], 'summary' => $reports->summary($rows), 'groups' => $rows->groupBy('department.code')->map(fn ($g) => $reports->summary($g))];
        if ($r->output === 'pdf') {
            return Pdf::loadView('reports.print', $data)->setPaper('a4', 'landscape')->download('gunluk-kontrol.pdf');
        }

        return view('reports.daily', $data);
    }

    private function excel($rows, $breakdown)
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('KAYITLAR');
        $fields = ShiftCalculator::INPUTS;
        $sheet->fromArray(array_merge(['Departman', 'Tarih', 'Vardiya', 'Personel'], $fields, ['pos_total', 'expected_cash', 'difference', 'Durum', 'Açıklama']), null, 'A1');
        $i = 2;
        foreach ($rows as $s) {
            $values = [$s->department->code, $s->date->format('Y-m-d'), $s->shiftType->name, $s->employee->name];
            foreach ($fields as $f) {
                $values[] = $s->financial->$f;
            }array_push($values, $s->financial->pos_total, $s->financial->expected_cash, $s->financial->difference, __('ui.status.'.$s->status), $s->notes);
            foreach ($values as $c => $v) {
                if ($v !== null) {
                    $sheet->setCellValueExplicit([$c + 1, $i], (string) $v, DataType::TYPE_STRING);
                }
            }$i++;
        }
        $summarySheet = $book->createSheet();
        $summarySheet->setTitle('RAPOR ÖZETİ');
        $summarySheet->fromArray(['Grup', 'Vardiya', 'Satış', 'POS', 'FAST', 'Gider', 'Kasa farkı'], null, 'A1');
        $i = 2;
        foreach ($breakdown as $label => $item) {
            $summarySheet->fromArray(array_merge([$label], array_values($item)), null, 'A'.$i);
            $i++;
        }$book->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, 'vardiyalar.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function notifications(Request $r)
    {
        $items = OperationNotification::with('shift')->whereHas('shift', fn ($q) => $q->visibleTo($r->user()))->latest()->paginate(30);

        return view('notifications', compact('items'));
    }

    public function activity(Request $r)
    {
        abort_if($r->user()->isStaff(), 403);
        $logs = AuditLog::when(! $r->user()->isSuper(), fn ($q) => $q->where('subject_type', 'Shift')->whereIn('subject_id', Shift::withTrashed()->where('department_id', $r->user()->department_id)->select('id')))->latest('id')->paginate(30);

        return view('activity', compact('logs'));
    }
}
