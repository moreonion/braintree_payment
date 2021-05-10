<?php

namespace Drupal\braintree_payment;

/**
 * Defines the controller class of the Braintree payment method.
 */
class CreditCardController extends BraintreeController {

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

}
