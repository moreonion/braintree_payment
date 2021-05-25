<?php

namespace Drupal\braintree_payment;

/**
 * Defines the controller class of the Braintree payment method.
 */
class CreditCardController extends BraintreeController {

  /**
   * Default values for the controller configuration.
   *
   * @var array
   */
  public $controller_data_defaults = [
    'environment' => 'sandbox',
    'merchant_id' => '',
    'merchant_account_id' => '',
    'private_key' => '',
    'public_key'  => '',
    'input_settings' => [],
    'enable_recurrent_payments' => 0,
    'force_liability_shift' => FALSE,
  ];

  /**
   * Create a new controller instance.
   */
  public function __construct() {
    $this->title = t('Braintree Credit Card');
    parent::__construct();
  }

  /**
   * Get a new payment form.
   *
   * @return CreditCardForm
   *   A new payment form.
   */
  public function paymentForm() {
    return new CreditCardForm();
  }

  /**
   * Get a form for configuring the payment method.
   *
   * @return CreditCardConfigurationForm
   *   A new configuration form.
   */
  public function configurationForm() {
    return new CreditCardConfigurationForm();
  }

}
