<?php

use Braintree\ClientToken;
use \Braintree\Configuration;
use Drupal\payment_forms\CreditCardForm;

namespace Drupal\braintree_payment;

/**
 * @file
 * Defines the Credit Card Form on the clientside.
 */
class CreditCardForm extends CreditCardForm {
  static protected $issuers = array(
    'visa'           => 'Visa',
    'mastercard'     => 'MasterCard',
    'jcb'            => 'JCB',
    'discover'       => 'Discover',
    'diners_club'    => 'Diners Club',
  );
  static protected $cvcLabel = array(
    'visa'           => 'CVV2 (Card Verification Value 2)',
    'amex'           => 'CID (Card Identification Number)',
    'mastercard'     => 'CVC2 (Card Validation Code 2)',
    'jcb'            => 'CSC (Card Security Code)',
    'discover'       => 'CID (Card Identification Number)',
    'diners_club'    => 'CSC (Card Security Code)',
  );

  /**
   * Generates a Braintree Client Token.
   */
  public function generateToken($env, $merchant, $pubkey, $privkey) {
    libraries_load('braintree-php');

    Configuration::environment($env);
    Configuration::merchantId($merchant);
    Configuration::publicKey($pubkey);
    Configuration::privateKey($privkey);

    return ClientToken::generate();
  }

  /**
   * Defines the form that shall be rendered.
   */
  public function form(array $form, array &$form_state, \Payment $payment) {
    $form = parent::form($form, $form_state, $payment);

    $form['braintree-payment-nonce'] = array(
      '#type' => 'hidden',
      '#default_value' => '',
    );

    $method = &$payment->method;

    // Generate a token for the current client.
    $payment_token = $this->generateToken(
      'sandbox',
      $method->controller_data['merchant_id'],
      $method->controller_data['public_key'],
      $method->controller_data['private_key']
    );

    // Add token & public key to settings for clientside.
    $settings['braintree_payment'][$method->pmid] = array(
      'payment_token' => $payment_token,
      'pmid' => $method->pmid,
    );

    // Attach client side JS files and settings to javascript settings variable.
    drupal_add_js($settings, 'setting');
    drupal_add_js('https://js.braintreegateway.com/web/3.7.0/js/client.min.js',
      array(
        'type' => 'external',
        'group' => JS_LIBRARY,
      ));
    drupal_add_js('https://js.braintreegateway.com/web/3.7.0/js/hosted-fields.min.js',
      array(
        'type' => 'external',
        'group' => JS_LIBRARY,
      ));
    drupal_add_js(drupal_get_path('module', 'braintree_payment') . '/braintree.js',
      array(
        'type' => 'file',
        'group' => JS_DEFAULT,
      ));

    $ed = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('braintree-extra-data')),
    ) + $this->mappedFields($payment);

    if (!isset($ed['name']) && isset($ed['first_name'])
      && isset($ed['last_name'])) {
      $ed['name'] = $ed['first_name'];
      $ed['name']['#value'] .= ' ' . $ed['last_name']['#value'];
      $ed['name']['#attributes']['data-braintree'] = 'name';
    }
    unset($ed['first_name']);
    unset($ed['last_name']);

    $form['extra_data'] = $ed;
    return $form;
  }

  /**
   * Validation function, real validation on the clientside.
   */
  public function validate(array $element, array &$form_state, \Payment $payment) {
    // Braintree takes care of the real validation, client-side.
    $values = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    $payment->method_data['braintree-payment-nonce'] = $values['braintree-payment-nonce'];
  }

  /**
   * Defines the mapped fields.
   */
  protected function mappedFields(\Payment $payment) {
    $fields = array();
    $field_map = $payment->method->controller_data['field_map'];
    foreach (static::extraDataFields() as $name => $field) {
      $map = isset($field_map[$name]) ? $field_map[$name] : array();
      foreach ($map as $key) {
        if ($value = $payment->contextObj->value($key)) {
          $field['#value'] = $value;
          $fields[$name] = $field;
        }
      }
    }
    return $fields;
  }

  /**
   * Defines additional data fields.
   */
  public static function extraDataFields() {
    $fields = array();
    $f = array(
      'name' => t('Name'),
      'first_name' => t('First name'),
      'last_name' => t('Last name'),
      'address_line1' => t('Address line 1'),
      'address_line2' => t('Address line 2'),
      'address_city' => t('City'),
      'address_state' => t('State'),
      'address_zip' => t('Postal code'),
      'address_country' => t('Country'),
    );
    foreach ($f as $name => $title) {
      $fields[$name] = array(
        '#type' => 'hidden',
        '#title' => $title,
        '#attributes' => array('data-braintree' => $name),
      );
    }
    return $fields;
  }

}
