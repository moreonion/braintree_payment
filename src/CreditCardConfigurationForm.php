<?php

use \Braintree\Exception;
use \Braintree\Exception\Authentication;
use \Braintree\ClientToken;
use \Braintree\Configuration;
use \Drupal\payment_forms\MethodFormInterface;

namespace Drupal\braintree_payment;

/**
 * Defines a configuration form for the Braintree payment method.
 */
class CreditCardConfigurationForm implements \Drupal\payment_forms\MethodFormInterface {

  /**
   * Returns a new configuration form.
   */
  public function form(array $form, array &$form_state, \PaymentMethod $method) {
    $cd = $method->controller_data;

    $library = libraries_detect('braintree-php');

    if (empty($library['installed'])) {
      drupal_set_message($library['error message'], 'error', FALSE);
    }

    $form['environment'] = array(
      '#type' => 'select',
      '#title' => t('Environment'),
      '#description' => t('This changes between the production environment (i.e. actual use on a live site) and the sandbox environment (i.e. for testing purposes where no real credit card data is used.)'),
      '#required' => TRUE,
      '#default_value' => $cd['environment'],
      '#options' => array(
        'production' => t('Production'),
        'sandbox' => t('Sandbox')
      )
    );

    $form['merchant_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Merchant ID'),
      '#description' => t('Available from Account / API Keys, Tokenization Keys, Encryption Keys / Client-Side Encryption Keys on braintreegateway.com'),
      '#required' => TRUE,
      '#default_value' => $cd['merchant_id'],
    );

    $form['public_key'] = array(
      '#type' => 'textfield',
      '#title' => t('Public key'),
      '#description' => t('Available from Account / API Keys, Tokenization Keys, Encryption Keys / API Keys on braintreegateway.com'),
      '#required' => TRUE,
      '#default_value' => $cd['public_key'],
    );

    $form['private_key'] = array(
      '#type' => 'textfield',
      '#title' => t('Private key'),
      '#description' => t('Available from Account / API Keys, Tokenization Keys, Encryption Keys / API Keys on braintreegateway.com'),
      '#required' => TRUE,
      '#default_value' => $cd['private_key'],
    );

    $map = $cd['field_map'];
    foreach (CreditCardForm::extraDataFields() as $name => $field) {
      $default = implode(', ', isset($map[$name]) ? $map[$name] : array());
      $form['field_map'][$name] = array(
        '#type' => 'textfield',
        '#title' => $field['#title'],
        '#default_value' => $default,
      );
    }

    return $form;
  }

  /**
   * Validates the configuration form input.
   */
  public function validate(array $element, array &$form_state, \PaymentMethod $method) {
    $cd = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    foreach ($cd['field_map'] as $k => &$v) {
      $v = array_filter(array_map('trim', explode(',', $v)));
    }

    $library = libraries_detect('braintree-php');

    if (empty($library['installed'])) {
      drupal_set_message($library['error message'], 'error', FALSE);
    }

    $loaded = libraries_load('braintree-php');

    // No special key-format, no further validation required.
    // Try to contact Braintree to see if the credentials are correct.
    \Braintree\Configuration::environment($cd['environment']);
    \Braintree\Configuration::merchantId($cd['merchant_id']);
    \Braintree\Configuration::publicKey($cd['public_key']);
    \Braintree\Configuration::privateKey($cd['private_key']);

    try {
      \Braintree\ClientToken::generate();
    }
    catch (\Braintree\Authentication $ex) {
      $values = array(
        '@status' => $ex->getCode(),
        '@message' => $ex->getMessage(),
      );

      // Braintree doesn't give us any meaningful error msg or error code, so we just print that something's wrong.
      $msg = t('Unable to contact Braintree using this set of keys. Please check if your Merchant ID, Public and Private key are correct.');
      form_error($element['public_key'], $msg);
      form_error($element['private_key']);
      form_error($element['merchant_id']);
    }
    catch (Exception $ex) {
      $values = array(
        '@status' => $ex->getCode(),
        '@message' => $ex->getMessage(),
      );

      $msg = t('Unable to contact Braintree using this set of keys (Error #@status): @message.', $values);
      form_error($element['private_key'], $msg);
      form_error($element['public_key']);
      form_error($element['private_key']);
    }

    $method->controller_data = $cd;
  }

}
