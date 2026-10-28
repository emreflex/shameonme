<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class WorkbookImporter
{
    public function preview(string $path, ?string $date = null, ?string $type = null): array
    {
        if (filesize($path) > 5 * 1024 * 1024) {
            abort(422, 'Dosya 5 MB sınırını aşıyor.');
        }
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            abort(422, 'Geçerli Excel arşivi değil.');
        }$size = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $size += $zip->statIndex($i)['size'];
        }$zip->close();
        abort_if($size > 40 * 1024 * 1024, 422, 'Açılmış dosya boyutu çok büyük.');
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $book = $reader->load($path);
        $rows = [];
        $skipped = 0;
        $recognized = false;
        foreach ($book->getWorksheetIterator() as $sheet) {
            if ($sheet->getHighestDataRow() > 10000) {
                abort(422, 'En fazla 10.000 satır desteklenir.');
            }
            if ($sheet->getTitle() === 'VARDİYA') {
                $recognized = true;
                foreach (['petrol' => range(3, 12), 'market' => range(25, 29)] as $dep => $indices) {
                    foreach ($indices as $i) {
                        $columns = $dep === 'petrol' ? ['sales' => 'C', 'credit' => 'D', 'fast' => 'E', 'expense' => 'F', 'points' => 'G', 'pos_kuveyt' => 'H', 'pos_garanti' => 'I', 'pos_ziraat' => 'J', 'counted_cash' => 'M', 'coins' => 'N'] : ['sales' => 'C', 'expense' => 'D', 'fast' => 'E', 'pos_kuveyt' => 'F', 'pos_garanti' => 'G', 'pos_ziraat' => 'H', 'counted_cash' => 'M', 'coins' => 'N'];
                        $d = [];
                        foreach ($columns as $key => $col) {
                            $d[$key] = $sheet->getCell($col.$i)->getValue();
                        }
                        if ($dep === 'market') {
                            $iVal = $sheet->getCell('I'.$i)->getValue();
                            $jVal = $sheet->getCell('J'.$i)->getValue();
                            if ($iVal !== null || $jVal !== null) {
                                try {
                                    $d['pos_other'] = Money::decimal(Money::cents($iVal ?? '0') + Money::cents($jVal ?? '0'));
                                } catch (\Throwable) {
                                    $d['pos_other'] = 'Geçersiz POS';
                                }
                            }
                        }
                        if (! collect($d)->contains(fn ($v) => $v !== null && $v !== '')) {
                            $skipped++;

                            continue;
                        }
                        $d += ['department' => $dep, 'employee' => trim((string) $sheet->getCell('B'.$i)->getValue()), 'date' => $date, 'type' => $type];
                        $rows[] = $this->row($d, $sheet->getTitle().'!'.$i);
                    }
                }
            } elseif ($sheet->getTitle() === 'KAYITLAR') {
                $recognized = true;
                $headers = [];
                foreach ($sheet->getRowIterator(1, 1) as $r) {
                    foreach ($r->getCellIterator() as $cell) {
                        $headers[$cell->getColumn()] = (string) $cell->getValue();
                    }
                }
                for ($i = 2; $i <= $sheet->getHighestDataRow(); $i++) {
                    $d = [];
                    foreach ($headers as $col => $key) {
                        $d[$key] = $sheet->getCell($col.$i)->getValue();
                    }
                    if (! collect(ShiftCalculator::INPUTS)->contains(fn ($key) => isset($d[$key]) && $d[$key] !== '')) {
                        $skipped++;

                        continue;
                    }
                    $rows[] = $this->row(array_merge($d, ['department' => $d['Departman'] ?? null, 'employee' => $d['Personel'] ?? null, 'date' => $d['Tarih'] ?? null, 'type' => $d['Vardiya'] ?? null, 'notes' => $d['Açıklama'] ?? null]), 'KAYITLAR!'.$i);
                }
            }
        }
        $book->disconnectWorksheets();
        abort_unless($recognized, 422, 'Desteklenen sayfa yok. YEDEK.xlsm VARDİYA veya uygulamanın KAYITLAR çıktısını kullanın.');

        return ['rows' => $rows, 'skipped' => $skipped, 'errors' => count(array_filter($rows, fn ($r) => $r['error'] !== null))];
    }

    private function row(array $d, string $source): array
    {
        $out = ['source' => $source, 'data' => $d, 'error' => null, 'fingerprint' => null];
        try {
            if (! in_array($d['department'] ?? null, ['petrol', 'market'])) {
                throw new \RuntimeException('Departman petrol veya market olmalı.');
            }
            if (blank($d['employee'] ?? null) || blank($d['type'] ?? null) || blank($d['date'] ?? null)) {
                throw new \RuntimeException('Personel, tarih ve vardiya türü gerekli. Eski şablon için tarih/vardiya seçin.');
            }
            if (is_numeric($d['date'])) {
                $d['date'] = Date::excelToDateTimeObject($d['date'])->format('Y-m-d');
            }
            if (! is_string($d['date']) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $d['date'])) {
                throw new \RuntimeException('Tarih YYYY-AA-GG biçiminde olmalı.');
            }
            [$y,$m,$day] = array_map('intval', explode('-', $d['date']));
            if (! checkdate($m, $day, $y)) {
                throw new \RuntimeException('Geçersiz tarih.');
            }
            if (strlen((string) $d['employee']) > 150 || strlen((string) $d['type']) > 100) {
                throw new \RuntimeException('Personel/vardiya adı çok uzun.');
            }
            foreach (ShiftCalculator::INPUTS as $key) {
                $v = $d[$key] ?? null;
                if (is_float($v)) {
                    if (abs($v * 100 - round($v * 100)) > 0.0001) {
                        throw new \RuntimeException('Tutar en fazla iki ondalık basamak içermeli.');
                    }$v = number_format($v, 2, '.', '');
                }
                $d[$key] = $v === null || $v === '' ? ($key === 'counted_cash' ? null : '0.00') : Money::decimal(Money::cents($v));
            }
            $calc = app(ShiftCalculator::class)->calculate($d['department'], $d);
            $d['employee'] = trim($d['employee']);
            $d['type'] = trim($d['type']);
            $canonical = array_intersect_key($d, array_flip(array_merge(['department', 'employee', 'date', 'type'], ShiftCalculator::INPUTS)));
            ksort($canonical);
            $out['fingerprint'] = hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE));
            $out['data'] = $d;
            $out['calculated'] = $calc;
        } catch (\Throwable $e) {
            $out['error'] = $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage();
        }

        return $out;
    }
}
