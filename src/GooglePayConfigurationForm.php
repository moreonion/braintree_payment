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

    $customer_data_form = $method->controller->customerDataForm();
    $form['input_settings'] = [
      '#weight' => 100,
    ] + $customer_data_form->configurationForm($method->controller_data['input_settings'], FALSE, FALSE);

    $form['google_pay_merchant_id'] = [
      '#type' => 'textfield',
      '#title' => t('Google Pay merchant ID'),
      '#default_value' => $cd['google_pay_merchant_id'],
    ];
    $form['google_pay_button_type'] = [
      '#type' => 'select',
      '#title' => t('Google Pay button type'),
      '#description' => t('The "Buy with Google Pay" button renders the card brand network and last four digits when the user has an available card as a payment method. "Donate with Google Pay" requires additional Non-Profit Organization validation by Google.'),
      '#default_value' => $cd['google_pay_button_type'],
      '#options' => array(
        'buy' => t('Buy with Google Pay'),
        'donate' => t('Donate with Google Pay'),
        'plain' => t('Plain Google Pay'),
      ),
    ];
    $form['google_pay_button_color'] = [
      '#type' => 'select',
      '#title' => t('Google Pay button color'),
      '#description' => t('Choose the color with the most contrast to the form background. "Default" uses the current Google default.'),
      '#default_value' => $cd['google_pay_button_color'],
      '#options' => array(
        'default' => t('Default'),
        'black' => t('Black'),
        'white' => t('White'),
      ),
    ];
    return $form;
  }

  /**
   * Validate the submitted values.
   *
   * @param array $element
   *   The Drupal elements array.
   * @param array $form_state
   *   The Drupal form_state array.
   * @param \PaymentMethod $method
   *   The payment method.
   */
  public function validate(array $element, array &$form_state, \PaymentMethod $method) {
    $cd = drupal_array_get_nested_value($form_state['values'], $element['#parents']);

    if ($cd['environment'] == 'production' && empty($cd['google_pay_merchant_id'])) {
      $msg = t('Google Pay merchant ID is required for production.');
      form_error($element['google_pay_merchant_id'], $msg);
    }
    parent::validate($element, $form_state, $method);
  }

}
