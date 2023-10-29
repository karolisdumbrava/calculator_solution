<?php

namespace Drupal\fuel_calculator;

use InvalidArgumentException;

/**
 * Class FuelCalculatorService.
 */
class FuelCalculatorService
{
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
   *  Array containing:
   *  - fuel_spent: Fuel spent
   *  - fuel_cost: Fuel cost
   */
  public function calculateFuelCost(float $distance, float $consumption, float $price_per_liter): array
  {

    $parameters = [
      'distance' => $distance,
      'consumption' => $consumption,
      'price_per_liter' => $price_per_liter,
    ];

    foreach ($parameters as $name => $value) {
      // Check for negative values
      if ($value < 0) {
        throw new InvalidArgumentException(sprintf('The parameter %s must be greater than 0.', $name));
      }

      // Check for non-numeric values
      if (!is_numeric($value)) {
        throw new InvalidArgumentException(sprintf('The parameter %s must be a number.', $name));
      }

      // Check for empty values
      if (empty($value)) {
        throw new InvalidArgumentException(sprintf('The parameter %s is required.', $name));
      }
    }

    // Calculation
    $fuel_spent = ($distance * $consumption) / 100;
    $fuel_cost = $fuel_spent * $price_per_liter;

    return [
      'fuel_spent' => $fuel_spent,
      'fuel_cost' => $fuel_cost,
    ];
  }
}
