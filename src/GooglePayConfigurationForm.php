<?php

namespace Drupal\braintree_payment;

/**
 * Defines a configuration form for the Braintree GooglePay payment method.
 */
class GooglePayConfigurationForm extends BraintreeConfigurationForm {

  /**
   * Form elements for the configuration form.
   *
   * @param array $form
   *   The Drupal form array.
   * @param array $form_state
   *   The Drupal form_state array.
   * @param \PaymentMethod $method
   *   The Stripe payment method.
   *
   * @return array
   *   The updated form array.
   */
  public function form(array $form, array &$form_state, \PaymentMethod $method) {
    $cd = $method->controller_data;
    $form = parent::form($form, $form_state, $method);
    $form['google_pay_merchant_id'] = [
      '#type' => 'textfield',
      '#title' => t('Google Pay merchant ID'),
      '#default_value' => $cd['google_pay_merchant_id'],
      '#required' => TRUE,
    ];
    return $form;
  }

}
