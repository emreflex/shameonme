<?php

namespace Tests\Unit;

use App\Services\Money;
use App\Services\ShiftCalculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShiftCalculatorTest extends TestCase
{
    public function test_petrol_calculation_is_exact(): void
    {
        $r = (new ShiftCalculator)->calculate('petrol', ['sales' => '1000.10', 'credit' => '100', 'fast' => '50', 'expense' => '25', 'points' => '10', 'pos_kuveyt' => '200', 'pos_garanti' => '100', 'pos_ziraat' => '50', 'pos_other' => '5', 'counted_cash' => '455.10', 'coins' => '5']);
        $this->assertSame('355.00', $r['pos_total']);
        $this->assertSame('460.10', $r['expected_cash']);
        $this->assertSame('0.00', $r['difference']);
        $this->assertSame('balanced', $r['cash_status']);
    }

    public function test_market_calculation_is_independent(): void
    {
        $r = (new ShiftCalculator)->calculate('market', ['sales' => '1000', 'expense' => '50', 'fast' => '50', 'pos_kuveyt' => '100', 'pos_garanti' => '100', 'pos_ziraat' => '50', 'pos_other' => '50', 'counted_cash' => '590', 'coins' => '5']);
        $this->assertSame('300.00', $r['pos_total']);
        $this->assertSame('600.00', $r['expected_cash']);
        $this->assertSame('-5.00', $r['difference']);
        $this->assertSame('short', $r['cash_status']);
    }

    public function test_null_count_is_not_zero_count(): void
    {
        $c = new ShiftCalculator;
        $a = $c->calculate('petrol', ['sales' => '100', 'counted_cash' => null]);
        $b = $c->calculate('petrol', ['sales' => '100', 'counted_cash' => '0']);
        $this->assertNull($a['difference']);
        $this->assertSame('waiting', $a['cash_status']);
        $this->assertSame('-100.00', $b['difference']);
        $this->assertSame('short', $b['cash_status']);
    }

    public function test_excess_and_negative_expected_cash(): void
    {
        $r = (new ShiftCalculator)->calculate('market', ['sales' => '10', 'expense' => '20', 'counted_cash' => '0']);
        $this->assertSame('-10.00', $r['expected_cash']);
        $this->assertSame('10.00', $r['difference']);
        $this->assertSame('over', $r['cash_status']);
    }

    public function test_market_refuses_petrol_only_amounts(): void
    {
        $this->expectException(ValidationException::class);
        (new ShiftCalculator)->calculate('market', ['credit' => '1']);
    }

    public function test_money_rejects_precision_loss(): void
    {
        $this->expectException(ValidationException::class);
        Money::cents('1.001');
    }

    public function test_large_amounts_and_format_are_exact(): void
    {
        $this->assertSame('99999999999.99', Money::decimal(Money::cents('99999999999.99')));
        $this->assertSame('-1.234,56 ₺', Money::format('-1234.56'));
    }

    public function test_aggregated_pos_can_exceed_individual_input_size(): void
    {
        $r = (new ShiftCalculator)->calculate('market', ['sales' => '99999999999.99', 'pos_kuveyt' => '99999999999.99', 'pos_garanti' => '99999999999.99', 'counted_cash' => '0']);
        $this->assertSame('199999999999.98', $r['pos_total']);
        $this->assertSame(19999999999998, Money::cents($r['pos_total']));
    }
}
