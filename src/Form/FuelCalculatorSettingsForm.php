<?php

namespace Drupal\fuel_calculator\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class FuelCalculatorSettingsForm extends ConfigFormBase
{
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array
  {
    return [
      'fuel_calculator.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */

  public function getFormId(): string
  {
    return 'fuel_calculator_settings';
  }

  /**
   * {@inheritdoc}
   */

  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $config = $this->config('fuel_calculator.settings');

    $form['default_distance'] = [
      '#type' => 'number',
      '#title' => $this->t('Default distance'),
      '#default_value' => $config->get('default_distance'),
      '#required' => TRUE,
    ];

    $form['default_consumption'] = [
      '#type' => 'number',
      '#title' => $this->t('Default consumption'),
      '#default_value' => $config->get('default_consumption'),
      '#step' => 0.1,
      '#required' => TRUE,
    ];

    $form['default_price_per_liter'] = [
      '#type' => 'number',
      '#title' => $this->t('Default price per liter'),
      '#default_value' => $config->get('default_price_per_liter'),
      '#step' => 0.01,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */

  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $this->config('fuel_calculator.settings')
      ->set('default_distance', $form_state->getValue('default_distance'))
      ->set('default_consumption', $form_state->getValue('default_consumption'))
      ->set('default_price_per_liter', $form_state->getValue('default_price_per_liter'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}