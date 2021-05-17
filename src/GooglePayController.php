<?php

namespace Drupal\braintree_payment;

/**
 * Defines the controller class of the Braintree payment method.
 */
class GooglePayController extends BraintreeController {

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

}
