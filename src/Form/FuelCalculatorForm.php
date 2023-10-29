<?php

namespace Drupal\fuel_calculator\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\fuel_calculator\FuelCalculatorService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a fuel calculator form.
 */
class FuelCalculatorForm extends FormBase {
    use LoggerChannelTrait;

    /**
     * The fuel calculator service.
     *
     * @var FuelCalculatorService
     */
    protected FuelCalculatorService $calculatorService;

    /**
     * The configuration factory.
     *
     * @var ConfigFactoryInterface
     */
    protected $configFactory;

    /**
     * The current user.
     *
     * @var AccountProxyInterface
     */
    protected AccountProxyInterface $currentUser;

    /**
     * The current request.
     *
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * Constructs a new FuelCalculatorForm.
     *
     * @param FuelCalculatorService $calculator_service
     *   The fuel calculator service.
     * @param ConfigFactoryInterface $config_factory
     *   The configuration factory.
     * @param AccountProxyInterface $current_user
     *   The current user.
     * @param RequestStack $request_stack
     *   The current request.
     */
    public function __construct(
        FuelCalculatorService $calculator_service,
        ConfigFactoryInterface $config_factory,
        AccountProxyInterface $current_user,
        RequestStack $request_stack
    ) {
        $this->calculatorService = $calculator_service;
        $this->configFactory = $config_factory;
        $this->currentUser = $current_user;
        $this->requestStack = $request_stack;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): self {
        return new static(
            // Load the service required to construct this class.
            $container->get('fuel_calculator.fuel_calculator_service'),
            // Load the service required to construct this class.
            $container->get('config.factory'),
            // Load the service required to construct this class.
            $container->get('current_user'),
            // Load the service required to construct this class.
            $container->get('request_stack')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'fuel_calculator_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {
        // Load configuration default values.
        $config = $this->configFactory->get('fuel_calculator.settings');

        $isReset = $form_state->get('reset') === TRUE;

        // Fetch the current request and query parameters.
        $current_request = $this->requestStack->getCurrentRequest();
        $query_params = $current_request->query->all();

        // Determine if the form is being rebuilt after submission.
        $isRebuilding = $form_state->isRebuilding();

        $default_values = [
            'distance' => $config->get('default_distance'),
            'consumption' => $config->get('default_consumption'),
            'price_per_liter' => $config->get('default_price_per_liter'),
        ];

        if ($isReset) {
            // If the form is being reset, we use the default values from the configuration.
            $default_distance = 0;
            $default_consumption = 0;
            $default_price_per_liter = 0;
        } else {
            $default_distance = $this->getQueryParamOrDefault('distance', $default_values['distance']);
            $default_consumption = $this->getQueryParamOrDefault('consumption', $default_values['consumption']);
            $default_price_per_liter = $this->getQueryParamOrDefault('price_per_liter', $default_values['price_per_liter']);
        }

        $isInitialLoad = !$isRebuilding && empty($query_params);

        if ($isInitialLoad || (!$isRebuilding && isset($query_params['distance']) && isset($query_params['consumption']) && isset($query_params['price_per_liter']))) {
            // If it's the initial load, or we have all necessary parameters, we run the calculation.
            // For the initial load, we use default values.
            $distance = $isInitialLoad ? $default_distance : $query_params['distance'];
            $consumption = $isInitialLoad ? $default_consumption : $query_params['consumption'];
            $price_per_liter = $isInitialLoad ? $default_price_per_liter : $query_params['price_per_liter'];

            // Calculate fuel cost.
            try {
                $results = $this->calculatorService->calculateFuelCost($distance, $consumption, $price_per_liter);

                $current_user = Drupal::currentUser();
                $username = $current_user->getAccountName(); // or use getDisplayName() if you prefer
                $ip = Drupal::request()->getClientIp();

                $this->logCalculation($username, $ip, $distance, $consumption, $price_per_liter, $results);
            } catch (InvalidArgumentException $e) {
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

        $form['distance'] = $this->createNumberField('distance', 'Distance travelled (km)', $default_distance);
        $form['consumption'] = $this->createNumberField('consumption', 'Fuel consumption (l/100km)', $default_consumption, TRUE, 0.1);
        $form['price_per_liter'] = $this->createNumberField('price_per_liter', 'Price per Liter (EUR)', $default_price_per_liter, TRUE, 0.01);

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

    /**
     * Reset the form
     *
     * @param array $form
     *   The form array
     * @param FormStateInterface $form_state
     *   The form state
     *
     * @return void
     */
    public function resetForm(array &$form, FormStateInterface $form_state)
    {
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


    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
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

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        // Get the user's current IP address.
        $ip = Drupal::request()->getClientIp();

        // Get the current user's name
        $current_user = Drupal::currentUser()->getAccount();
        $username = $current_user->getDisplayName();

        // Get the input values from the form state
        $distance = $form_state->getValue('distance');
        $consumption = $form_state->getValue('consumption');
        $price_per_liter = $form_state->getValue('price_per_liter');

        // Define Service
        $calculator_service = Drupal::service('fuel_calculator.fuel_calculator_service');

        // Calculate fuel cost.
        try {
            $results = $calculator_service->calculateFuelCost($distance, $consumption, $price_per_liter);
        } catch (InvalidArgumentException $e) {
            // If we get an invalid argument exception, we set the error message and return.
            $form_state->setErrorByName('distance', $this->t('Invalid argument: @message', ['@message' => $e->getMessage()]));
            return;
        }

        $this->logCalculation($username, $ip, $distance, $consumption, $price_per_liter, $results);

        $form_state->setValue('distance', $distance);
        $form_state->setValue('consumption', $consumption);
        $form_state->setValue('price_per_liter', $price_per_liter);
        $form_state->set('fuel_spent', $results['fuel_spent'] . ' liters');
        $form_state->set('fuel_cost', $results['fuel_cost'] . ' EUR');

        $form_state->setRebuild();
    }

    /**
     * Log the results of calculation
     *
     * @param string $username
     *   The username of the user who submitted the form
     * @param string $ip
     *   The IP address of the user who submitted the form
     * @param float $distance
     *   The distance traveled
     * @param float $consumption
     *   The fuel consumption per 100km
     * @param float $price_per_liter
     *   The price per liter
     * @param array $results
     *   The results of the calculation
     *
     * @return void
     */

    protected function logCalculation(string $username, string $ip, float $distance, float $consumption, float $price_per_liter, array $results): void
    {
        $log_message = sprintf(
            'User %s with IP %s calculated the fuel cost for a distance of %s km, with a fuel consumption of %s l/100km and a price per liter of %s EUR. The fuel spent was %s liters and the fuel cost was %s EUR.',
            $this->t($username),
            $ip,
            $distance,
            $consumption,
            $price_per_liter,
            floatval($results['fuel_spent']),
            floatval($results['fuel_cost'])
        );

        $this->getLogger('fuel_calculator')->notice($log_message);
    }

    /**
     * Log the reset of the form
     *
     * @param string $username
     *   The username of the user who reset the form
     * @param string $ip
     *   The IP address of the user who reset the form
     */

    protected function logReset(string $username, string $ip): void
    {
        $log_message = sprintf('User %s with IP %s reset the form.', $username, $ip);
        $this->getLogger('fuel_calculator')->notice($log_message);
    }


    /**
     * Get the query parameter or default value
     *
     * @param string $param
     *   The query parameter
     * @param string $default
     *   The default value
     *
     * @return string
     *   The query parameter or default value
     */
    protected function getQueryParamOrDefault(string $param, string $default, $isReset = FALSE): string
    {
        $current_request = $this->requestStack->getCurrentRequest();
        $query_params = $current_request->query->all();

        return isset($query_params[$param]) && !$isReset ? $query_params[$param] : $default;
    }

    /**
     * Create a number field
     *   The name of the field
     * @param string $title
     *   The title of the field
     * @param string $default_value
     *   The default value of the field
     * @param boolean $required
     *   Whether the field is required
     * @param float|null $step
     *   The step of the field
     *
     * @return array
     *   The number field
     */
    protected function createNumberField(string $name, string $title, $default_value, bool $required = TRUE, float $step = NULL ): array
    {
        return [
            '#type' => 'number',
            '#title' => $this->t($title),
            '#default_value' => $default_value,
            '#required' => $required,
            '#step' => $step,
        ];
    }
}
