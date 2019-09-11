<?php

namespace Drupal\braintree_payment;

use Braintree\Exception\Authentication;
use Braintree\Exception\NotFound;
use Braintree\ClientToken;
use Braintree\Configuration;
use Braintree\MerchantAccount;
use Drupal\little_helpers\ElementTree;
use Drupal\payment_forms\MethodFormInterface;

/**
 * Defines a configuration form for the Braintree payment method.
 */
class CreditCardConfigurationForm implements MethodFormInterface {

  /**
   * Get display options for a customer data field.
   */
  public static function displayOptions($required) {
    $display_options = [
      'ifnotset' => t('Show field if it is not available from the context.'),
      'always' => t('Always show the field - prefill with context values.'),
    ];
    if (!$required) {
      $display_options['hidden'] = t('Don’t display, use values from context if available.');
    }
    return $display_options;
  }

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

    $form['force_liability_shift'] = [
      '#type' => 'checkbox',
      '#title' => t('Refuse payments without liability shift'),
    ];

    $form['input_settings']['#type'] = 'container';
    // Configuration for extra data elements.
    $extra = CreditCardForm::extraElements();
    $extra['#settings_element'] = &$form['input_settings'];
    $extra['#settings_defaults'] = $cd['input_settings'];
    $extra['#settings_root'] = TRUE;
    ElementTree::applyRecursively($extra, function (&$element, $key, &$parent) {
      if (!$key) {
        // Skip the root element.
        return;
      }
      else {
        $element['#settings_defaults'] = $parent['#settings_defaults'][$key];
      }
      if (in_array($element['#type'], ['fieldset', 'container'])) {
        $fieldset = [
          '#collapsible' => TRUE,
          '#collapsed' => TRUE,
        ] + $element;
      }
      else {
        $defaults = $element['#settings_defaults'];
        $fieldset = [
          '#type' => 'fieldset',
          '#title' => $element['#title'],
          '#collapsible' => TRUE,
          '#collapsed' => TRUE,
        ];
        $required = !empty($element['#required']);
        $defaults['required'] = $defaults['required'] || $required;
        $enabled_id = drupal_html_id('controller_data_enabled_' . $key);
        $fieldset['enabled'] = [
          '#type' => 'checkbox',
          '#title' => t('Enabled: Make this field available for Advanced Fraud Protection purposes.'),
          '#default_value' => $defaults['enabled'],
          '#id' => $enabled_id,
        ];
        $display_id = drupal_html_id('controller_data_display_' . $key);
        $fieldset['display'] = [
          '#type' => 'radios',
          '#title' => t('Display'),
          '#options' => CreditCardConfigurationForm::displayOptions($required),
          '#default_value' => $defaults['display'],
          '#id' => $display_id,
          '#states' => ['visible' => ["#$enabled_id" => ['checked' => TRUE]]],
        ];
        if (empty($parent['#settings_root'])) {
          $fieldset['display_other'] = [
            '#type' => 'radios',
            '#title' => t('Display when other fields in the same fieldset are visible.'),
            '#options' => CreditCardConfigurationForm::displayOptions($required),
            '#default_value' => $defaults['display_other'],
            '#states' => [
              'invisible' => ["#$display_id" => ['value' => 'always']],
              'visible' => ["#$enabled_id" => ['checked' => TRUE]],
            ],
          ];
        }
        $fieldset['required'] = array(
          '#type' => 'checkbox',
          '#title' => t('Required'),
          '#states' => ['disabled' => ["#$display_id" => ['value' => 'hidden']]],
          '#default_value' => $defaults['required'],
          '#access' => !$required,
          '#states' => ['visible' => ["#$enabled_id" => ['checked' => TRUE]]],
        );
        $fieldset['keys'] = array(
          '#type' => 'textfield',
          '#title' => t('Context keys'),
          '#description' => t('When building the form these (comma separated) keys are used to ask the Payment Context for a (default) value for this field.'),
          '#default_value' => implode(', ', $defaults['keys']),
          '#element_validate' => ['_braintree_payment_validate_comma_separated_keys'],
          '#states' => ['visible' => ["#$enabled_id" => ['checked' => TRUE]]],
        );
      }
      $parent['#settings_element'][$key] = &$fieldset;
      $element['#settings_element'] = &$fieldset;
    });
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
