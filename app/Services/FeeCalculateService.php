<?php

namespace App\Services;

class FeeCalculateService
{
    public const MIN_FEE_IRR = 500000;
    public const MAX_FEE_IRR = 50000000;

    private const FEE_TIERS = [
        ['min_quantity' => 0.0, 'max_quantity' => 1.0, 'percentage' => 0.02],
        ['min_quantity' => 1.0, 'max_quantity' => 10.0, 'percentage' => 0.015],
        ['min_quantity' => 10.0, 'max_quantity' => 10000.0, 'percentage' => 0.01],
    ];

    public function calculateFeePercentage(float $quantity, float $price): float
    {
        $this->validateInputs($quantity, $price);

        foreach (self::FEE_TIERS as $tier) {
            if ($quantity >= $tier['min_quantity'] && $quantity < $tier['max_quantity']) {
                return $tier['percentage'];
            }
        }

        return self::FEE_TIERS[0]['percentage']; // Default to first tier
    }

    public function calculateFee(float $quantity, float $matchedQuantity, float $price): array
    {
        $this->validateInputs($quantity, $price, $matchedQuantity);

        $feePercentage = $this->calculateFeePercentage($quantity, $price);
        $feeInIrr = $this->clampFee($matchedQuantity * $price * $feePercentage);
        $feeInGold = $feeInIrr / $price;

        return [
            'irrFee' => $feeInIrr,
            'goldFee' => $feeInGold,
        ];
    }


    private function clampFee(float $fee): float
    {
        return max(self::MIN_FEE_IRR, min(self::MAX_FEE_IRR, $fee));
    }


    private function validateInputs(float ...$values): void
    {
        foreach ($values as $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException('Input values cannot be negative');
            }
        }
    }
}
