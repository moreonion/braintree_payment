<?php

namespace Drupal\braintree_payment;

use Drupal\payment_forms\PaymentFormInterface;

/**
 * Defines the form rendered when making a payment.
 */
class ApplePayForm implements PaymentFormInterface {

  /**
   * Add form elements for Braintree Apple Pay payments.
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
    $form = BraintreeForm::form($form, $form_state, $payment);

    // Add extra data.
    $customer_data_form = $payment->method->controller->customerDataForm();
    $form['extra_data'] = [
      '#weight' => 100,
    ] + $customer_data_form->form($payment->method->controller_data['input_settings'], $payment->contextObj);

    // Additional JS.
    $base_url = BraintreeForm::jsUrl();
    $js_options = ['type' => 'external', 'group' => JS_LIBRARY];
    $form['#attached']['js'] += [
      "$base_url/client.min.js" => $js_options,
      "$base_url/apple-pay.min.js" => $js_options,
    ];
    $pmid = $payment->method->pmid;
    $cd = $payment->method->controller_data;
    $settings = &$form['#attached']['js'][0]['data']['braintree_payment']["pmid_$pmid"];
    $settings['requestData'] = [
      'total' => [
        'type' => 'final',
        'amount' => (string) $payment->totalAmount(TRUE),
        'label' => $cd['apple_pay_display_name'],
      ],
    ];
    $settings['displayName'] = $cd['apple_pay_display_name'];

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
