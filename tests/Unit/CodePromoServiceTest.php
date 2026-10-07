<?php

namespace Tests\Unit;

use App\Models\Forfait_usager;
use App\Services\CodePromoService;
use PHPUnit\Framework\TestCase;

class CodePromoServiceTest extends TestCase
{
    public function test_it_uses_the_configured_discounted_amount_for_a_percentage_discount(): void
    {
        $forfait = (new Forfait_usager())->forceFill([
            'prix' => 10000,
            'reduction_type' => 'percentage',
            'reduction' => 20,
            'montant_apres_reduction' => 8000,
        ]);

        $quote = (new CodePromoService())->quote(null, $forfait, 1);

        $this->assertSame(10000.0, $quote['montant_initial']);
        $this->assertSame(2000.0, $quote['montant_reduction_forfait']);
        $this->assertSame(8000.0, $quote['montant_final']);
    }

    public function test_it_calculates_a_fixed_discount_when_the_configured_amount_is_zero(): void
    {
        $forfait = (new Forfait_usager())->forceFill([
            'prix' => 10000,
            'reduction_type' => 'fixed',
            'reduction' => 1500,
            'montant_apres_reduction' => 0,
        ]);

        $quote = (new CodePromoService())->quote(null, $forfait, 1);

        $this->assertSame(1500.0, $quote['montant_reduction_forfait']);
        $this->assertSame(8500.0, $quote['montant_apres_reduction_forfait']);
        $this->assertSame(8500.0, $quote['montant_final']);
    }

    public function test_it_uses_the_normal_price_when_there_is_no_plan_discount(): void
    {
        $forfait = (new Forfait_usager())->forceFill([
            'prix' => 10000,
            'reduction_type' => 'fixed',
            'reduction' => 0,
            'montant_apres_reduction' => 0,
        ]);

        $quote = (new CodePromoService())->quote(null, $forfait, 1);

        $this->assertSame(0.0, $quote['montant_reduction']);
        $this->assertSame(10000.0, $quote['montant_final']);
    }
}
