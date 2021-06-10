<?php

namespace Drupal\braintree_payment;

use Drupal\payment_forms\CreditCardForm as _CreditCardForm;

/**
 * Defines the form rendered when making a payment.
 */
class CreditCardForm extends _CreditCardForm {

  /**
   * Reset credit card issuers.
   *
   * @var array
   */
  static protected $issuers = [];

  /**
   * Reset CVS labels.
   *
   * @var array
   */
  static protected $cvcLabel = [];

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
    $form['credit_card_number'] = [
      '#process' => array_merge(['braintree_payment_hosted_fields_process'], element_info('textfield')['#process']),
      '#field_name' => 'number',
      '#wrapper_classes' => ['cc-number'],
      '#parents' => ['cc-number'],
    ] + $form['credit_card_number'];
    $form['secure_code'] = [
      '#process' => array_merge(['braintree_payment_hosted_fields_process'], element_info('textfield')['#process']),
      '#field_name' => 'cvv',
      '#wrapper_classes' => ['cc-cvv'],
      '#parents' => ['cc-cvv'],
    ] + $form['secure_code'];
    $form['expiry_date']['month'] = [
      '#process' => array_merge(['braintree_payment_hosted_fields_process'], element_info('select')['#process']),
      '#field_name' => 'expirationMonth',
      '#wrapper_classes' => ['cc-month'],
      '#parents' => ['cc-month'],
    ] + $form['expiry_date']['month'];
    $form['expiry_date']['year'] = [
      '#process' => array_merge(['braintree_payment_hosted_fields_process'], element_info('select')['#process']),
      '#field_name' => 'expirationYear',
      '#wrapper_classes' => ['cc-year'],
      '#parents' => ['cc-year'],
    ] + $form['expiry_date']['year'];

    // Add extra data.
    $customer_data_form = $payment->method->controller->customerDataForm();
    $form['extra_data'] = ['#weight' => 100] + $customer_data_form->form($payment->method->controller_data['input_settings'], $payment->contextObj);

    // Additional JS.
    $base_url = BraintreeForm::jsUrl();
    $form['#attached']['js'] += [
      "$base_url/hosted-fields.min.js" => [
        'type' => 'external',
        'group' => JS_LIBRARY,
      ],
      "$base_url/three-d-secure.min.js" => [
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
