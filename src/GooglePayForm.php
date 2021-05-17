<?php

namespace Drupal\braintree_payment;

use Drupal\payment_forms\CreditCardForm as _CreditCardForm;

/**
 * Defines the form rendered when making a payment.
 */
class GooglePayForm extends _CreditCardForm {

  /**
   * Add form elements for Braintree credit card payments.
   *
   * @param array $form
   *   The Drupal form array.
   * @param array $form_state
   *   The Drupal form_state array.
   * @param \Payment $payment
   *   The payment object.
   *
   * @return array
   *   The updated form array.
   */
  public function form(array $form, array &$form_state, \Payment $payment) {
    $form = parent::form($form, $form_state, $payment);
    $form = BraintreeForm::form($form, $form_state, $payment);

    // Override payment fields.
    unset($form['issuer']);
    unset($form['credit_card_number']);
    unset($form['secure_code']);
    unset($form['expiry_date']);

    // Additional JS
    $base_url = BraintreeForm::jsUrl();
    $form['#attached']['js'] += [
      "https://pay.google.com/gp/p/js/pay.js" => [
        'type' => 'external',
        'group' => JS_LIBRARY,
      ],
      "$base_url/google-payment.min.js" => [
        'type' => 'external',
        'group' => JS_LIBRARY,
      ],
    ];

    return $form;
  }

  /**
   * Store relevant values in the payment’s method_data.
   *
   * @param array $element
   *   The Drupal elements array.
   * @param array $form_state
   *   The Drupal form_state array.
   * @param \Payment $payment
   *   The payment object.
   */
  public function validate(array $element, array &$form_state, \Payment $payment) {
    BraintreeForm::validate($element, $form_state, $payment);
  }

}
