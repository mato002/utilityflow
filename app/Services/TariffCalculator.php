<?php

namespace App\Services;

use App\Models\Tariff;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class TariffCalculator
{
    /** @return array{total:string, items:array<int, array{description:string, quantity:string, unit_price:string, amount:string}>} */
    public function calculate(Tariff $tariff, string $consumption): array
    {
        $items = [];
        $totalCents = 0;

        if ((float) $tariff->fixed_charge > 0) {
            $fixedCents = Money::cents((string) $tariff->fixed_charge);
            $items[] = [
                'description' => $tariff->name.' fixed charge',
                'quantity' => '1.0000',
                'unit_price' => (string) $tariff->fixed_charge,
                'amount' => Money::decimal($fixedCents),
            ];
            $totalCents += $fixedCents;
        }

        if ($tariff->rate_type === 'tiered') {
            $remaining = (int) round((float) $consumption * 10_000);
            foreach ($tariff->tiers as $tier) {
                $from = (int) round((float) $tier->from_units * 10_000);
                $to = $tier->to_units === null ? PHP_INT_MAX : (int) round((float) $tier->to_units * 10_000);
                $tierCapacity = $to === PHP_INT_MAX ? $remaining : max(0, $to - $from);
                $tierUnits = min($remaining, $tierCapacity);
                if ($tierUnits <= 0) {
                    continue;
                }

                $quantity = Money::decimalQuantity($tierUnits);
                $unitPrice = (string) $tier->price_per_unit;
                $lineCents = Money::quantityRateCents($quantity, $unitPrice);
                $items[] = [
                    'description' => $tariff->name.' tier',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => Money::decimal($lineCents),
                ];
                $totalCents += $lineCents;
                $remaining -= $tierUnits;
                if ($remaining <= 0) {
                    break;
                }
            }

            if ($remaining > 0) {
                throw ValidationException::withMessages(['tariff' => 'The configured tariff tiers do not cover the full consumption amount.']);
            }
        } elseif ($tariff->rate_type === 'per_unit' && (float) $consumption > 0) {
            $lineCents = Money::quantityRateCents($consumption, (string) $tariff->price_per_unit);
            $items[] = [
                'description' => $tariff->name.' usage',
                'quantity' => $consumption,
                'unit_price' => (string) $tariff->price_per_unit,
                'amount' => Money::decimal($lineCents),
            ];
            $totalCents += $lineCents;
        }

        return ['total' => Money::decimal($totalCents), 'items' => $items];
    }
}
