<?php

namespace Drupal\braintree_payment;

/**
 * Defines the form rendered when making a payment.
 */
abstract class BraintreeForm {

  const JS_SDK_VERSION = '3.50.0';

  /**
   * Form settings for Braintree payments.
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
  public static function form(array $form, array &$form_state, \Payment $payment) {
    $method = $payment->method;

    $form['braintree-payment-nonce'] = array(
      '#type' => 'hidden',
      '#default_value' => '',
    );

    // Add token & public key to settings for clientside.
    $settings['braintree_payment']['pmid_' . $method->pmid] = array(
      'payment_token' => $method->controller->getClientToken($method),
      'pmid' => $method->pmid,
      'forceLiabilityShift' => !empty($method->conroller_data['force_liability_shift']),
      'method' => $method->controller->name,
    );

    // Attach client side JS files and settings to javascript settings variable.
    $form['#attached']['js'][] = [
      'type' => 'setting',
      'data' => $settings,
    ];
    $form['#attached']['js'] += static::scriptAttachments();

    $form['amount'] = [
      '#type' => 'hidden',
      '#value' => (string) $payment->totalAmount(TRUE),
      '#attributes' => ['data-braintree-name' => 'amount'],
    ];

    return $form;
  }

  /**
   * Base URL for JS files on Braintree.
   *
   * @return string
   *   The JS URL string.
   */
  public static function jsUrl() {
    return 'https://js.braintreegateway.com/web/' . static::JS_SDK_VERSION . '/js';
  }

  /**
   * Javascripts needed for the payment form.
   *
   * @return array
   *   #attached-array with all the needed scripts.
   */
  protected static function scriptAttachments() {
    $base_url = static::jsUrl();
    $js["$base_url/client.min.js"] = [
      'type' => 'external',
      'group' => JS_LIBRARY,
    ];
    $js[drupal_get_path('module', 'braintree_payment') . '/braintree.js'] = [
      'type' => 'file',
      'group' => JS_DEFAULT,
    ];
    return $js;
  }

  /**
   * Store relevant values in the payment’s method_data.
   *
   * Braintree takes care of the real validation, client-side.
   *
   * @param array $element
   *   The Drupal elements array.
   * @param array $form_state
   *   The Drupal form_state array.
   * @param \Payment $payment
   *   The payment object.
   */
  public static function validate(array $element, array &$form_state, \Payment $payment) {
    $values = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    $payment->method_data['braintree-payment-nonce'] = $values['braintree-payment-nonce'];
    $customer_data_form = $payment->method->controller->customerDataForm();
    $payment->method_data['extra_data'] = $customer_data_form::getData($element);
  }

}
