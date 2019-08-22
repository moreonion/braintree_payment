<?php

namespace Drupal\braintree_payment;

use Braintree\ClientToken;
use Braintree\Configuration;
use Drupal\little_helpers\ElementTree;
use Drupal\payment_forms\CreditCardForm as _CreditCardForm;

/**
 * @file
 * Defines the Credit Card Form on the clientside.
 */
class CreditCardForm extends _CreditCardForm {

  const JS_SDK_VERSION = '3.50.0';

  static protected $issuers = [];
  static protected $cvcLabel = [];

  /**
   * Defines the form that shall be rendered.
   */
  public function form(array $form, array &$form_state, \Payment $payment) {
    $method = $payment->method;
    $form = parent::form($form, $form_state, $payment);
    unset($form['issuer']);

    $form['braintree-payment-nonce'] = array(
      '#type' => 'hidden',
      '#default_value' => '',
    );

    // Add token & public key to settings for clientside.
    $settings['braintree_payment']['pmid_' . $method->pmid] = array(
      'payment_token' => $method->controller->getClientToken($method),
      'pmid' => $method->pmid,
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

    $data_fieldset = static::extraElements();
    $data_fieldset['#settings'] = $method->controller_data['input_settings'];

    // Recursively set #settings and remove #required.
    ElementTree::applyRecursively($data_fieldset, function(&$element, $key, &$parent) {
      if ($key) {
        $element['#settings'] = $parent['#settings'][$key];
      }
      $element['#controller_required'] = !empty($element['#required']);
      unset($element['#required']);
      if (!empty($element['#braintree_field'])) {
        $element['#attributes']['data-braintree-name'] = $element['#braintree_field'];
      }
      $element['#user_visible'] = FALSE;

    });

    // Set default values from context.
    if ($context = $payment->contextObj) {
      ElementTree::applyRecursively($data_fieldset, function (&$element, $key) use ($context) {
        if (!in_array($element['#type'], ['container', 'fieldset'])) {
          foreach ($element['#settings']['keys'] as $k) {
            if ($value = $context->value($k)) {
              $element['#default_value'] = $value;
              break;
            }
          }
        }
      });
    }

    $display = function ($element, $key, $mode = 'display') {
      $d = $element['#settings'][$mode];
      return ($d == 'always') || (empty($element['#default_value']) && $d == 'ifnotset');
    };

    // Set visibility.
    ElementTree::applyRecursively($data_fieldset, function (&$element, $key, &$parent) use ($display) {
      $element += ['#access' => FALSE];
      $is_container = in_array($element['#type'], ['fieldset', 'container']);
      if (!$is_container) {
        $element['#access'] = $element['#settings']['enabled'];
      }
      // If an element is accessible its parent should be visible too.
      if ($parent && $element['#access']) {
        $parent['#access'] = TRUE;
      }

      if (!$is_container) {
        $element['#user_visible'] = $display($element, $key, 'display');
      }
      if ($element['#user_visible'] && $parent) {
        $parent['#user_visible'] = TRUE;
      }
    }, TRUE);
    // Reset visibility if there are visible elements in the same fieldset.
    ElementTree::applyRecursively($data_fieldset, function (&$element, $key, &$parent) use ($display) {
      if ($parent && $parent['#user_visible']) {
        // Give child elements of visible fieldsets a chance to be displayed.
        if ($element['#type'] != 'fieldset' && !$element['#user_visible']) {
          $element['#user_visible'] = $display($element, $key, 'display_other');
        }
      }
    });
    // Transform elements that should not be visible for the user.
    ElementTree::applyRecursively($data_fieldset, function (&$element, $key, &$parent) use ($display) {
      if ($key && !$element['#user_visible']) {
        if ($element['#type'] == 'fieldset') {
          $element['#type'] = 'container';
        }
        else {
          $element['#type'] = 'hidden';
          $element['#value'] = $element['#default_value'];
        }
      }
    });

    $form['extra_data'] = ['#weight' => 100] + $data_fieldset;
    return $form;
  }

  /**
   * Javascripts needed for the payment form.
   *
   * @return array
   *   #attached-array with all the needed scripts.
   */
  protected static function scriptAttachments() {
    $base_url = 'https://js.braintreegateway.com/web';
    $version = static::JS_SDK_VERSION;
    $js["$base_url/$version/js/client.min.js"] = [
      'type' => 'external',
      'group' => JS_LIBRARY,
    ];
    $js["$base_url/$version/js/hosted-fields.min.js"] = [
      'type' => 'external',
      'group' => JS_LIBRARY,
    ];
    $js["$base_url/$version/js/three-d-secure.min.js"] = [
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
   * Validation function, real validation on the clientside.
   */
  public function validate(array $element, array &$form_state, \Payment $payment) {
    // Braintree takes care of the real validation, client-side.
    $values = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    $payment->method_data['braintree-payment-nonce'] = $values['braintree-payment-nonce'];

    $bd = [];
    foreach ($element['billing_data'] as $field) {
      if (!empty($field['#controller_required']) && empty($field['#value'])) {
        form_error($field, t('!name field is required.', array('!name' => $field['#title'])));
      }
      // Only pass non-empty fields in method data.
      if (!empty($field['#value'])) {
        $bd[$field['#braintree_field']] = $field['#value'];
      }
    }
    // Always provide at least an empty address.
    $payment->method_data['billing_data'] = $bd + [
      'company' => '',
      'countryCodeAlpha2' => '',
      'extendedAddress' => '',
      'firstName' => '',
      'lastName' => '',
      'locality' => '',
      'postalCode' => '',
      'region' => '',
      'streetAddress' => '',
    ];
  }

  /**
   * Defines additional data fields.
   *
   * @return array
   *   A form-API style array defining fields that map to the braintree billing
   *   data using the #braintree_field attribute.
   */
  public static function extraElements() {
    require_once DRUPAL_ROOT . '/includes/locale.inc';

    $fields = [
      '#type' => 'container',
    ];

    $fields['email'] = [
      '#type' => 'textfield',
      '#title' => t('Email address'),
      '#braintree_field' => 'email',
    ];

    $fields['billing_address'] = [
      '#type' => 'fieldset',
      '#title' => t('Billing address'),
    ];
    $fields['billing_address']['first_name'] = [
      '#type' => 'textfield',
      '#title' => t('First name'),
      '#braintree_field' => 'billingAddress.firstName',
    ];
    $fields['billing_address']['last_name'] = [
      '#type' => 'textfield',
      '#title' => t('Last name'),
      '#braintree_field' => 'billingAddress.lastName',
    ];
    $fields['billing_address']['company'] = [
      '#type' => 'textfield',
      '#title' => t('Company'),
      '#braintree_field' => 'billingAddress.company',
    ];
    $fields['billing_address']['street_address'] = [
      '#type' => 'textfield',
      '#title' => t('Address line 1'),
      '#braintree_field' => 'billingAddress.streetAddress',
    ];
    $fields['billing_address']['address_line2'] = [
      '#type' => 'textfield',
      '#title' => t('Address line 2'),
      '#braintree_field' => 'billingAddress.extendedAddress',
    ];
    $fields['billing_address']['country'] = [
      '#type' => 'select',
      '#options' => country_get_list(),
      '#title' => t('Country'),
      '#braintree_field' => 'billingAddress.countryCodeAlpha2',
    ];
    $fields['billing_address']['postcode'] = [
      '#type' => 'textfield',
      '#title' => t('Postal code'),
      '#braintree_field' => 'billingAddress.postalCode',
    ];
    $fields['billing_address']['city'] = [
      '#type' => 'textfield',
      '#title' => t('City/Locality'),
      '#braintree_field' => 'billingAddress.locality',
    ];
    $fields['billing_address']['region'] = [
      '#type' => 'textfield',
      '#title' => t('Region/State'),
      '#braintree_field' => 'billingAddress.region',
    ];

    $fields['shipping_address'] = [
      '#type' => 'fieldset',
      '#title' => t('Shipping address'),
    ];
    $fields['shipping_address']['given_name'] = [
      '#type' => 'textfield',
      '#title' => t('Given name'),
      '#braintree_field' => 'additionalInformation.shippingGivenName',
    ];
    $fields['shipping_address']['surname'] = [
      '#type' => 'textfield',
      '#title' => t('Surname'),
      '#braintree_field' => 'additionalInformation.shippingSurname',
    ];
    $fields['shipping_address']['street_address'] = [
      '#type' => 'textfield',
      '#title' => t('Street address'),
      '#braintree_field' => 'additionalInformation.shippingAddress.streetAddress',
    ];
    $fields['shipping_address']['extended_address'] = [
      '#type' => 'textfield',
      '#title' => t('Extended address'),
      '#braintree_field' => 'additionalInformation.shippingAddress.extendedAddress',
    ];
    $fields['shipping_address']['line3'] = [
      '#type' => 'textfield',
      '#title' => t('Address line 3'),
      '#braintree_field' => 'additionalInformation.shippingAddress.line3',
    ];
    $fields['shipping_address']['locality'] = [
      '#type' => 'textfield',
      '#title' => t('Locality (city)'),
      '#braintree_field' => 'additionalInformation.shippingAddress.locality',
    ];
    $fields['shipping_address']['region'] = [
      '#type' => 'textfield',
      '#title' => t('Region'),
      '#braintree_field' => 'additionalInformation.shippingAddress.region',
    ];
    $fields['shipping_address']['postal_code'] = [
      '#type' => 'textfield',
      '#title' => t('Postal code'),
      '#braintree_field' => 'additionalInformation.shippingAddress.postalCode',
    ];
    $fields['shipping_address']['country'] = [
      '#type' => 'select',
      '#title' => t('Country'),
      '#braintree_field' => 'additionalInformation.shippingAddress.countryCodeAlpha2',
      '#options' => country_get_list(),
    ];

    return $fields;
  }

  /**
   * Check whether a specific field should be displayed.
   */
  protected function shouldDisplay($field, $display) {
    return ($display == 'always') || (empty($field['#default_value']) && $display == 'ifnotset');
  }

}
