<?php 
namespace Drupal\fuel_calculator\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\fuel_calculator\FuelCalculatorService;

class FuelCalculatorForm extends FormBase {

    protected $calculator_service;

    public function __construct(FuelCalculatorService $calculator_service) {
        $this->calculator_service = $calculator_service;
    }

    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('fuel_calculator.fuel_calculator_service')
        );
    }

    public function buildForm(array $form, FormStateInterface $form_state) {
        // Load configuration default values.
        $config = \Drupal::config('fuel_calculator.settings');

        $isReset = $form_state->get('reset') === TRUE;

        // Fetch the current request and query parameters.
        $current_request = \Drupal::request();
        $query_params = $current_request->query->all();

        // Determine if the form is being rebuilt after submission.
        $isRebuilding = $form_state->isRebuilding();
    
        $default_distance = $default_consumption = $default_price_per_liter = 0;

        if ($isReset) {
            // If the form is being reset, we use the default values from the configuration.
            $default_distance = 0;
            $default_consumption = 0;
            $default_price_per_liter = 0;

        } else {
            $default_distance = isset($query_params['distance']) && !$isReset ? $query_params['distance'] : $config->get('default_distance');
            $default_consumption = isset($query_params['consumption']) && !$isReset ? $query_params['consumption'] : $config->get('default_consumption');
            $default_price_per_liter = isset($query_params['price_per_liter']) && !$isReset ? $query_params['price_per_liter'] : $config->get('default_price_per_liter');    
        }

        $isInitialLoad = !$isRebuilding && empty($query_params);

        if ($isInitialLoad || (!$isRebuilding && isset($query_params['distance']) && isset($query_params['consumption']) && isset($query_params['price_per_liter']))) {
            // If it's the initial load or we have all necessary parameters, we run the calculation.
            // For the initial load, we use default values.
            $distance = $isInitialLoad ? $default_distance : $query_params['distance'];
            $consumption = $isInitialLoad ? $default_consumption : $query_params['consumption'];
            $price_per_liter = $isInitialLoad ? $default_price_per_liter : $query_params['price_per_liter'];
            
            // Calculate fuel cost.
            try {
                $results = $this->calculator_service->calculateFuelCost($distance, $consumption, $price_per_liter);

                $current_user = \Drupal::currentUser();
                $username = $current_user->getAccountName(); // or use getDisplayName() if you prefer        
                $ip = \Drupal::request()->getClientIp();

                $this->logCalculation($username, $ip, $distance, $consumption, $price_per_liter, $results);
            } catch (\InvalidArgumentException $e) {
                // If we get an invalid argument exception, we set the error message and return.
                $form_state->setErrorByName('distance', $this->t('Invalid argument: @message', ['@message' => $e->getMessage()]));
                return $form;
            }

            // Save these results to use them later in the form.
            $form_state->set('fuel_spent', $results['fuel_spent'] . ' liters');
            $form_state->set('fuel_cost', $results['fuel_cost'] . ' EUR');
    
            // Since we have results, we rebuild the form to reflect these results.
            $form_state->setRebuild();
        }
    
        // Define form fields with the appropriate default values.
        $form['distance'] = [
            '#type' => 'number',
            '#title' => $this->t('Distance travelled (km)'),
            '#default_value' => $default_distance,
            '#required' => TRUE,
        ];
    
        $form['consumption'] = [
            '#type' => 'number',
            '#title' => $this->t('Fuel consumption (l/100km)'),
            '#default_value' => $default_consumption,
            '#required' => TRUE,
            '#step' => 0.1,
        ];
    
        $form['price_per_liter'] = [
            '#type' => 'number',
            '#title' => $this->t('Price per Liter (EUR)'),
            '#default_value' => $default_price_per_liter,
            '#required' => TRUE,
            '#step' => 0.01,
        ];
    
        // Placeholder for the results.
        $form['results_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['id' => 'results-wrapper'],
        ];
    
        // Generate markup for results if they exist in the form state.
        $fuel_spent = $form_state->get('fuel_spent') ?? '';
        $fuel_cost = $form_state->get('fuel_cost') ?? '';
    
        $form['results_wrapper']['fuel_spent'] = [
            '#markup' => '<div class="result-item">' . $this->t('Fuel Spent: ') . '<span class="result-value">' . $fuel_spent . '</span></div>',
        ];
        $form['results_wrapper']['fuel_cost'] = [
            '#markup' => '<div class="result-item">' . $this->t('Fuel Cost: ') . '<span class="result-value">' . $fuel_cost . '</span></div>',
        ];
    
        // Define action buttons.
        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Calculate'),
                '#button_type' => 'primary',
            ],
            'reset' => [
                '#type' => 'submit',
                '#value' => $this->t('Reset'),
                '#submit' => ['::resetForm'],
            ],
        ];
    
        return $form;
    }
    

    public function getFormId() {
        return 'fuel_calculator_form';
    }

    public function resetForm(array &$form, FormStateInterface $form_state) {
        $form_state->set('reset', TRUE);

        $form_state->setValue('distance', 0);
        $form_state->setValue('consumption', 0);
        $form_state->setValue('price_per_liter', 0);

        $form_state->set('fuel_spent', NULL);
        $form_state->set('fuel_cost', NULL);
        $form_state->setRebuild(FALSE);

        $current_user = \Drupal::currentUser()->getAccount();
        $username = $current_user->getDisplayName();
        $ip = \Drupal::request()->getClientIp();
        $this->logReset($username, $ip);
    }
    

    public function validateForm(array &$form, FormStateInterface $form_state) {
        $fuel_consumption = $form_state->getValue('consumption');

        $fuel_consumption = str_replace(',', '.', $fuel_consumption);

        if (!is_numeric($fuel_consumption)) {
            $form_state->setErrorByName('consumption', $this->t('Fuel consumption must be a number'));
        } else {
            if ($fuel_consumption <= 0) {
                $form_state->setErrorByName('consumption', $this->t('Fuel consumption must be greater than 0'));
            }
        }

        $price_per_liter = $form_state->getValue('price_per_liter');

        $price_per_liter = str_replace(',', '.', $price_per_liter);

        if (!is_numeric($price_per_liter)) {
            $form_state->setErrorByName('price_per_liter', $this->t('Price per liter must be a number'));
        } else {
            if ($price_per_liter <= 0) {
                $form_state->setErrorByName('price_per_liter', $this->t('Price per liter must be greater than 0'));
            }
        }
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        // Get the user's current IP address.
        $ip = \Drupal::request()->getClientIp();

        // Get the current user's name
        $current_user = \Drupal::currentUser()->getAccount();
        $username = $current_user->getDisplayName();

        // Get the input values from the form state
        $distance = $form_state->getValue('distance');
        $consumption = $form_state->getValue('consumption');
        $price_per_liter = $form_state->getValue('price_per_liter');

        // Define Service
        $calculator_service = \Drupal::service('fuel_calculator.fuel_calculator_service');

        // Calculate fuel cost.
        try {
            $results = $calculator_service->calculateFuelCost($distance, $consumption, $price_per_liter);
        } catch (\InvalidArgumentException $e) {
            // If we get an invalid argument exception, we set the error message and return.
            $form_state->setErrorByName('distance', $this->t('Invalid argument: @message', ['@message' => $e->getMessage()]));
            return $form;
        }

        $this->logCalculation($username, $ip, $distance, $consumption, $price_per_liter, $results);

        $form_state->setValue('distance', $distance);
        $form_state->setValue('consumption', $consumption);
        $form_state->setValue('price_per_liter', $price_per_liter);
        $form_state->set('fuel_spent', $results['fuel_spent'] . ' liters');
        $form_state->set('fuel_cost', $results['fuel_cost'] . ' EUR');

        $form_state->setRebuild(true);
    }

    /**
     * Log the results of calculation
     * 
     * @param string $username
     * @param string $ip
     * @param float $distance
     * @param float $consumption
     * @param float $price_per_liter
     * @param float $fuel_spent
     * @param float $fuel_cost
     * @param array $results
     */

    protected function logCalculation($username, $ip, $distance, $consumption, $price_per_liter, $results) {
        $log_message = sprintf(
            'User %s with IP %s calculated the fuel cost for a distance of %s km, with a fuel consumption of %s l/100km and a price per liter of %s EUR. The fuel spent was %s liters and the fuel cost was %s EUR.',
            $username,
            $ip,
            $distance,
            $consumption,
            $price_per_liter,
            $results['fuel_spent'],
            $results['fuel_cost']
        );

        \Drupal::logger('fuel_calculator')->notice($log_message);
    }

    protected function logReset($username, $ip) {
        $log_message = sprintf(
            'User %s with IP %s reset the form.',
            $username,
            $ip
        );

        \Drupal::logger('fuel_calculator')->notice($log_message);
    }
}