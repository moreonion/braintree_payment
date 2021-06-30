<?php

namespace Drupal\braintree_payment;

use Braintree\Exception\Authentication;
use Braintree\Exception\NotFound;
use Braintree\ClientToken;
use Braintree\Configuration;
use Braintree\MerchantAccount;
use Drupal\payment_forms\MethodFormInterface;

/**
 * Defines a configuration form for the Braintree payment method.
 */
class BraintreeConfigurationForm implements MethodFormInterface {

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
        'sandbox' => t('Sandbox'),
      ),
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

    return $form;
  }

  /**
   * Validate the submitted values and put them in the method’s controller data.
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
      // Braintree doesn't give us any meaningful error msg or error code,
      // so we just print that something's wrong.
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
    $method->controller_data = $cd;
  }

}
