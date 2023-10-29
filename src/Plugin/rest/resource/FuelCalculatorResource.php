<?php

namespace Drupal\fuel_calculator\Plugin\rest\resource;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\fuel_calculator\FuelCalculatorService;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a Fuel Calculator Resource
 *
 * @RestResource(
 *  id = "fuel_calculator_resource",
 *  label = @Translation("Fuel Calculator Resource"),
 *  uri_paths = {
 *      "canonical" = "/fuel-calculator-api",
 *      "create" = "/fuel-calculator-api"
 *  }
 * )
 */
class FuelCalculatorResource extends ResourceBase
{
  /**
   * The fuel calculator service.
   * @var FuelCalculatorService
   */

  protected FuelCalculatorService $fuelCalculatorService;

  /**
   * The logger factory.
   * @var LoggerChannelFactoryInterface
   */

  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructs a new FuelCalculatorResource object.
   *
   * @param FuelCalculatorService $fuel_calculator_service
   * The fuel calculator service.
   *
   * @param LoggerChannelFactoryInterface $logger_factory
   * The logger factory.
   */

  protected AccountProxyInterface $current_user;

  public function __construct(
    array                         $configuration,
                                  $plugin_id,
                                  $plugin_definition,
    array                         $serializer_formats,
    LoggerInterface               $logger,
    FuelCalculatorService         $fuel_calculator_service,
    LoggerChannelFactoryInterface $logger_factory,
    AccountProxyInterface         $current_user
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->fuelCalculatorService = $fuel_calculator_service;
    $this->loggerFactory = $logger_factory;
    $this->current_user = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ResourceBase|FuelCalculatorResource|ContainerFactoryPluginInterface|static
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('fuel_calculator'),
      $container->get('fuel_calculator.fuel_calculator_service'),
      $container->get('logger.factory'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param Request $request
   * The request object.
   *
   * @return ResourceResponse
   * The response containing the results.
   */

  public function post(Request $request): ResourceResponse
  {
    $data = json_decode($request->getContent(), TRUE);

    // Validate presence of necessary parameters.
    $required_params = ['distance', 'consumption', 'price_per_liter'];
    foreach ($required_params as $param) {
      if (!isset($data[$param])) {
        return new ResourceResponse([
          'error' => sprintf('The parameter %s is required.', $param)
        ], 400);
      }

      if (!is_numeric($data[$param])) {
        return new ResourceResponse([
          'error' => sprintf('The parameter %s must be a number.', $param)
        ], 400);
      }

      if ($data[$param] <= 0) {
        return new ResourceResponse([
          'error' => sprintf('The parameter %s must be greater than 0.', $param)
        ], 400);
      }
    }

    // Calculate the fuel cost.
    try {
      $results = $this->fuelCalculatorService->calculateFuelCost(
        $data['distance'],
        $data['consumption'],
        $data['price_per_liter']
      );
    } catch (InvalidArgumentException $e) {
      return new ResourceResponse([
        'error' => $e->getMessage()
      ], 400);
    }

    // Logging
    $ip = $request->getClientIp();
    $current_user = $this->current_user;
    $username = $current_user->isAuthenticated() ? $current_user->getAccountName() : 'Anonymous';

    $logger = $this->loggerFactory->get('fuel_calculator');

    $log_message = sprintf(
      'User %s with IP %s calculated the fuel cost for a distance of %s km, with a fuel consumption of %s l/100km and a price per liter of %s EUR. The fuel spent was %s liters and the fuel cost was %s EUR.',
      $username,
      $ip,
      $data['distance'],
      $data['consumption'],
      $data['price_per_liter'],
      $results['fuel_spent'],
      $results['fuel_cost']
    );
    $logger->notice($log_message);

    return new ResourceResponse($results);
  }
}
