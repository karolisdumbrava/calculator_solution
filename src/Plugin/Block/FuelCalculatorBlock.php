<?php

namespace Drupal\fuel_calculator\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'FuelCalculator' Block.
 *
 * @Block(
 *   id = "fuel_calculator_block",
 *   admin_label = @Translation("Fuel Calculator Block"),
 *   category = @Translation("Custom"),
 * )
 */
class FuelCalculatorBlock extends BlockBase {
    public function build() {
        $form = \Drupal::formBuilder()->getForm('Drupal\fuel_calculator\Form\FuelCalculatorForm');
      
        return $form;
      }
      
}
