<?php

namespace Drupal\braintree_payment;

/**
 * Defines a configuration form for the Braintree ApplePay payment method.
 */
class ApplePayConfigurationForm extends BraintreeConfigurationForm {

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

    $customer_data_form = $method->controller->customerDataForm();
    $form['input_settings'] = [
      '#weight' => 100,
    ] + $customer_data_form->configurationForm($method->controller_data['input_settings'], FALSE, FALSE);

    $form['apple_pay_display_name'] = [
      '#type' => 'textfield',
      '#title' => t('Recipient name displayed to users'),
      '#default_value' => $cd['apple_pay_display_name'] ?: variable_get('site_name'),
      '#maxlength' => 128,
      '#required' => TRUE,
    ];
    return $form;
  }

}
