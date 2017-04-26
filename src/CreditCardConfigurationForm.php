<?php

namespace Drupal\braintree_payment;

use \Braintree\Exception\Authentication;
use \Braintree\Exception\NotFound;
use \Braintree\ClientToken;
use \Braintree\Configuration;
use \Braintree\MerchantAccount;
use \Drupal\payment_forms\MethodFormInterface;

/**
 * Defines a configuration form for the Braintree payment method.
 */
class CreditCardConfigurationForm implements \Drupal\payment_forms\MethodFormInterface {

  /**
   * Returns a new configuration form.
   */
  public function form(array $form, array &$form_state, \PaymentMethod $method) {
    $cd = $method->controller_data
      + $method->controller->controller_data_defaults;

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

    $form['merchant_account_id'] = [
      '#type' => 'textfield',
      '#title' => t('Merchant account ID'),
      '#description' => t("Payments are sent to this account. Leave empty to use the merchant's default account"),
      '#default_value' => $cd['merchant_account_id'],
    ];

    $display_options = array(
      'ifnotset' => t('Show field if it is not available from the context.'),
      'always' => t('Always show the field - prefill with context values.'),
      'hidden' => t("Don't display, use values from context if available."),
    );

    // Allow configuration for billing data fields.
    $stored = $cd['billing_data'];
    foreach (CreditCardForm::extraDataFields() as $name => $field) {
      $defaults = isset($stored[$name]) ? $stored[$name] : [];
      $defaults += [
        'display' => 'ifnotset',
        'keys' => [$name],
        'mandatory' => FALSE,
      ];
      $form['billing_data'][$name] = [
        '#type' => 'fieldset',
        '#title' => $field['#title'],
      ];

      $id = drupal_html_id('controller_data_' . $name);
      $form['billing_data'][$name]['display'] = array(
        '#type' => 'select',
        '#title' => t('Display'),
        '#options' => $display_options,
        '#default_value' => $defaults['display'],
        '#id' => $id,
      );
      $form['billing_data'][$name]['required'] = array(
        '#type' => 'checkbox',
        '#title' => t('Required'),
        '#states' => array(
          'disabled' => array(
            "#$id" => array('value' => 'hidden'),
          ),
        ),
        '#default_value' => $defaults['required'],
      );
      $form['billing_data'][$name]['keys'] = array(
        '#type' => 'textfield',
        '#title' => t('Context keys'),
        '#description' => t('When building the form these (comma separated) keys are used to get a (default) value for this field from the Payment Context.'),
        '#default_value' => implode(', ', $defaults['keys']),
      );
    }

    return $form;
  }

  /**
   * Validates the configuration form input.
   */
  public function validate(array $element, array &$form_state, \PaymentMethod $method) {
    $cd = drupal_array_get_nested_value($form_state['values'], $element['#parents']);

    $library = libraries_detect('braintree-php');
    if (empty($library['installed'])) {
      drupal_set_message($library['error message'], 'error', FALSE);
    }

    $loaded = libraries_load('braintree-php');

    // No special key-format, no further validation required.
    // Try to contact Braintree to see if the credentials are correct.
    Configuration::environment($cd['environment']);
    Configuration::merchantId($cd['merchant_id']);
    Configuration::publicKey($cd['public_key']);
    Configuration::privateKey($cd['private_key']);

    try {
      ClientToken::generate();
      if ($cd['merchant_account_id']) {
        MerchantAccount::find($cd['merchant_account_id']);
      }
    }
    catch (Authentication $e) {
      // Braintree doesn't give us any meaningful error msg or error code, so we just print that something's wrong.
      $msg = t('Unable to contact Braintree using this set of keys. Please check if your Merchant ID, Public and Private key are correct.');
      form_error($element['public_key'], $msg);
      form_error($element['private_key']);
      form_error($element['merchant_id']);
    }
    catch (NotFound $e) {
      form_error($element['merchant_account_id'], t('No such account for this braintree merchant.'));
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

    foreach ($cd['billing_data'] as &$field) {
      $field += ['keys' => ''];
      $field['keys'] = array_map('trim', explode(',', $field['keys']));
    }

    $method->controller_data = $cd;
  }

}
