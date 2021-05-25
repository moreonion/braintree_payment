<?php

namespace Drupal\braintree_payment;

/**
 * Defines a configuration form for the Braintree Credit Card payment method.
 */
class CreditCardConfigurationForm extends BraintreeConfigurationForm {

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
    $form['force_liability_shift'] = [
      '#type' => 'checkbox',
      '#title' => t('Refuse payments without liability shift'),
      '#default_value' => $cd['force_liability_shift'],
    ];
    return $form;
  }

}
