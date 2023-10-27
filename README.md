# Fuel Calculator Drupal Module

Welcome to the Fuel Calculator module for Drupal! This module offers a simple and efficient way for users to calculate their fuel expenses based on the distance they plan to travel, their vehicle's fuel consumption, and the current price of fuel. This README provides all the necessary information for installing, configuring, and using this module.

## Features

- **Interactive Calculation Form**: Users can easily calculate fuel costs by inputting parameters such as distance, fuel consumption, and fuel price.
- **Default Values Configuration**: Set default values for each parameter, simplifying the process for end-users.
- **URL Parameter Support**: Enable pre-filling of the calculator via URL parameters, convenient for sharing specific calculations.
- **REST API Integration**: Interact with the calculator programmatically, allowing integration into various applications.

## Installation

1. Install the module as per Drupal's standard installation process. For more information, please refer to the official documentation: [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-modules).

2. Once installed, the Fuel Calculator can be accessed at:
```bash
[your-site.url]/fuel-calculator
```
## Configuration

### Setting Default Values

1. Navigate to the configuration page at:
```bash
/admin/config/system/fuel-calculator
```
2. Here, you can set the default values for 'distance', 'consumption', and 'price_per_liter' that the calculator will use in the absence of user input or URL parameters.

### Utilizing URL Parameters

You can pre-populate the calculator's fields by providing the values in the URL as follows:
```bash
[your-site.url]/fuel-calculator?distance=300&consumption=10&price_per_liter=1.5
```
This feature is particularly useful for bookmarks, hyperlinks, or sharing specific calculation parameters.

## REST API Usage

The module comes with REST API support, permitting other services to calculate fuel costs remotely. For easier REST configuration, we recommend installing the REST UI module:
```bash
composer require 'drupal/restui:^1.21'
```

### Authentication

For secure interaction, our API requires a valid X-CSRF-Token, retrievable via:
```bash
[your-site.url]/session/token
```

### Submitting a Calculation

Send a POST request with a JSON body containing your parameters:
```bash
{
  "distance": 200,
  "consumption": 10,
  "price_per_liter": 1.6
}
```
