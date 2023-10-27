<?php

namespace Drupal\fuel_calculator;

/** 
 * Class FuelCalculatorService.
 */

class FuelCalculatorService {
    /**
     * Calculates the fuel spent and the fuel cost.
     * 
     * @param float $distance
     *  Distance traveled
     * @param float $consumption
     *  Fuel consumption per 100km
     * @param float $price_per_liter
     *  Price per liter
     * 
     * @return array
     * 
     * An array containing the fuel spent and the fuel cost.
     */
    public function calculateFuelCost(float $distance, float $consumption, float $price_per_liter) {
        $fuel_spent = ($distance * $consumption) / 100;
        $fuel_cost = $fuel_spent * $price_per_liter;

        return [
            'fuel_spent' => $fuel_spent,
            'fuel_cost' => $fuel_cost,
        ];
    }
}