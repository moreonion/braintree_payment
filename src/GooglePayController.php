<?php

namespace Drupal\braintree_payment;

/**
 * Defines the controller class of the Braintree google pay payment method.
 */
class GooglePayController extends BraintreeController {

  /**
   * Default values for the controller configuration.
   *
   * @var array
   */
  public $controller_data_defaults = [
    'environment' => 'sandbox',
    'merchant_id' => '',
    'merchant_account_id' => '',
    'google_pay_merchant_id' => '',
    'private_key' => '',
    'public_key'  => '',
    'force_liability_shift' => FALSE,
    'input_settings' => [],
    'enable_recurrent_payments' => 0,
  ];

  /**
   * Create a new controller instance.
   */
  public function __construct() {
    $this->title = t('Braintree Google Pay');
    parent::__construct();
  }

  /**
   * Get a new payment form.
   *
   * @return GooglePayForm
   *   A new payment form.
   */
  public function paymentForm() {
    return new GooglePayForm();
  }

  /**
   * Get a form for configuring the payment method.
   *
   * @return GooglePayConfigurationForm
   *   A new configuration form.
   */
  public function configurationForm() {
    return new GooglePayConfigurationForm();
  }

}
