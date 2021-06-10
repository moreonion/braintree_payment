<?php

namespace Drupal\braintree_payment;

/**
 * Defines the controller class of the Braintree google pay payment method.
 */
class ApplePayController extends BraintreeController {

  /**
   * Default values for the controller configuration.
   *
   * @var array
   */
  public $controller_data_defaults = [
    'environment' => 'sandbox',
    'merchant_id' => '',
    'merchant_account_id' => '',
    'apple_pay_display_name' => '',
    'apple_pay_button_color' => 'black',
    'private_key' => '',
    'public_key'  => '',
    'input_settings' => [],
    'enable_recurrent_payments' => 0,
  ];

  /**
   * Create a new controller instance.
   */
  public function __construct() {
    $this->title = t('Braintree Apple Pay');
    parent::__construct();
  }

  /**
   * Get a new payment form.
   *
   * @return ApplePayForm
   *   A new payment form.
   */
  public function paymentForm() {
    return new ApplePayForm();
  }

  /**
   * Get a form for configuring the payment method.
   *
   * @return ApplePayConfigurationForm
   *   A new configuration form.
   */
  public function configurationForm() {
    return new ApplePayConfigurationForm();
  }

}
