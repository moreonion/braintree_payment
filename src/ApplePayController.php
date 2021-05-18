<?php

namespace Drupal\braintree_payment;

/**
 * Defines the controller class of the Braintree google pay payment method.
 */
class ApplePayController extends BraintreeController {

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

}
