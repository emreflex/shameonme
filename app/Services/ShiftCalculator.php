<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class ShiftCalculator
{
    public const INPUTS = ['sales', 'credit', 'fast', 'expense', 'points', 'pos_kuveyt', 'pos_garanti', 'pos_ziraat', 'pos_other', 'counted_cash', 'coins'];

    public function calculate(string $department, array $data): array
    {
        if (! in_array($department, ['petrol', 'market'], true)) {
            abort(422);
        }
        $amounts = [];
        foreach (self::INPUTS as $key) {
            if (! preg_match('/^\d{1,11}(?:\.\d{1,2})?$/D', (string) (($data[$key] ?? '') === '' ? '0' : $data[$key]))) {
                throw ValidationException::withMessages([$key => 'Geçersiz tutar veya tutar sınırı aşıldı.']);
            }
            $amounts[$key] = Money::cents($data[$key] ?? '0');
        }
        if ($department === 'market' && ($amounts['credit'] || $amounts['points'])) {
            throw ValidationException::withMessages(['credit' => 'Market kaydında veresiye ve puan kullanılamaz.']);
        }
        $pos = array_sum(array_intersect_key($amounts, array_flip(['pos_kuveyt', 'pos_garanti', 'pos_ziraat', 'pos_other'])));
        $expected = $amounts['sales'] - $amounts['fast'] - $amounts['expense'] - $pos - ($department === 'petrol' ? $amounts['credit'] + $amounts['points'] : 0);
        $uncounted = ! array_key_exists('counted_cash', $data) || $data['counted_cash'] === null || $data['counted_cash'] === '';
        $difference = $uncounted ? null : $amounts['counted_cash'] + $amounts['coins'] - $expected;
        $out = array_map(fn ($c) => Money::decimal($c), $amounts);
        $out['counted_cash'] = $uncounted ? null : $out['counted_cash'];

        return $out + ['pos_total' => Money::decimal($pos), 'expected_cash' => Money::decimal($expected), 'difference' => $difference === null ? null : Money::decimal($difference), 'cash_status' => $difference === null ? 'waiting' : ($difference === 0 ? 'balanced' : ($difference < 0 ? 'short' : 'over'))];
    }
}
