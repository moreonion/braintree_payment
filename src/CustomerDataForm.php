<?php

namespace Drupal\braintree_payment;

use Drupal\little_helpers\ArrayConfig;
use Drupal\little_helpers\ElementTree;

/**
 * Defines the form rendered when making a payment.
 */
class CustomerDataForm {

  /**
   * Default field input settings.
   *
   * @return array
   *   Field settings.
   */
  public static function defaultSettings() {
    return [
      'email' => [
        'enabled' => TRUE,
        'display' => 'hidden',
        'keys' => ['email'],
        'required' => FALSE,
      ],
      'billing_address' => [
        'first_name' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['first_name', 'given_name'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'last_name' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['last_name'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'company' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['company'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'street_address' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['street_address', 'address_line2'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'address_line2' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['first_name', 'given_name'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'country' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['country'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'postcode' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['postcode', 'zip_code'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'city' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['city'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'region' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['region'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
      ],
      'shipping_address' => [
        'given_name' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'surname' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'street_address' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'extended_address' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'line3' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'locality' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'region' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'postal_code' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'country' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
      ],
    ];
  }

  /**
   * Generate the form elements for the customer data.
   *
   * @param array $settings
   *   Form settings.
   * @param object $context
   *   The payment context.
   */
  public function form(array $settings, $context) {
    ArrayConfig::mergeDefaults($settings, static::defaultSettings());
    $data_fieldset = static::extraElements();
    $data_fieldset['#settings'] = $settings;

    // Recursively set #settings and remove #required.
    ElementTree::applyRecursively($data_fieldset, function (&$element, $key, &$parent) {
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
          $element += ['#default_value' => ''];
          $element['#type'] = 'hidden';
          $element['#value'] = $element['#default_value'];
        }
      }
    });

    return $data_fieldset;
  }

  /**
   * Get the settings configuration form.
   *
   * @param array $settings
   *   Form settings.
   */
  public function configurationForm(array $settings) {
    ArrayConfig::mergeDefaults($settings, static::defaultSettings());
    $form['#type'] = 'container';
    // Configuration for extra data elements.
    $extra = static::extraElements();
    $extra['#settings_element'] = &$form;
    $extra['#settings_defaults'] = $settings;
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
          '#options' => static::displayOptions($required),
          '#default_value' => $defaults['display'],
          '#id' => $display_id,
          '#states' => ['visible' => ["#$enabled_id" => ['checked' => TRUE]]],
        ];
        if (empty($parent['#settings_root'])) {
          $fieldset['display_other'] = [
            '#type' => 'radios',
            '#title' => t('Display when other fields in the same fieldset are visible.'),
            '#options' => static::displayOptions($required),
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
      '#braintree_php_field' => 'customer.email',
    ];

    $fields['billing_address'] = [
      '#type' => 'fieldset',
      '#title' => t('Billing address'),
    ];
    $fields['billing_address']['first_name'] = [
      '#type' => 'textfield',
      '#title' => t('First name'),
      '#braintree_field' => 'billingAddress.firstName',
      '#braintree_php_field' => 'billing.firstName',
    ];
    $fields['billing_address']['last_name'] = [
      '#type' => 'textfield',
      '#title' => t('Last name'),
      '#braintree_field' => 'billingAddress.lastName',
      '#braintree_php_field' => 'billing.lastName',
    ];
    $fields['billing_address']['company'] = [
      '#type' => 'textfield',
      '#title' => t('Company'),
      '#braintree_field' => 'billingAddress.company',
      '#braintree_php_field' => 'billing.company',
    ];
    $fields['billing_address']['street_address'] = [
      '#type' => 'textfield',
      '#title' => t('Address line 1'),
      '#braintree_field' => 'billingAddress.streetAddress',
      '#braintree_php_field' => 'billing.streetAddress',
    ];
    $fields['billing_address']['address_line2'] = [
      '#type' => 'textfield',
      '#title' => t('Address line 2'),
      '#braintree_field' => 'billingAddress.extendedAddress',
      '#braintree_php_field' => 'billing.extendedAddress',
    ];
    $fields['billing_address']['country'] = [
      '#type' => 'select',
      '#options' => country_get_list(),
      '#title' => t('Country'),
      '#braintree_field' => 'billingAddress.countryCodeAlpha2',
      '#braintree_php_field' => 'billing.countryCodeAlpha2',
    ];
    $fields['billing_address']['postcode'] = [
      '#type' => 'textfield',
      '#title' => t('Postal code'),
      '#braintree_field' => 'billingAddress.postalCode',
      '#braintree_php_field' => 'billing.postalCode',
    ];
    $fields['billing_address']['city'] = [
      '#type' => 'textfield',
      '#title' => t('City/Locality'),
      '#braintree_field' => 'billingAddress.locality',
      '#braintree_php_field' => 'billing.locality',
    ];
    $fields['billing_address']['region'] = [
      '#type' => 'textfield',
      '#title' => t('Region/State'),
      '#braintree_field' => 'billingAddress.region',
      '#braintree_php_field' => 'billing.region',
    ];

    $fields['shipping_address'] = [
      '#type' => 'fieldset',
      '#title' => t('Shipping address'),
    ];
    $fields['shipping_address']['given_name'] = [
      '#type' => 'textfield',
      '#title' => t('Given name'),
      '#braintree_field' => 'additionalInformation.shippingGivenName',
      '#braintree_php_field' => 'shipping.firstName',
    ];
    $fields['shipping_address']['surname'] = [
      '#type' => 'textfield',
      '#title' => t('Surname'),
      '#braintree_field' => 'additionalInformation.shippingSurname',
      '#braintree_php_field' => 'shipping.lastName',
    ];
    $fields['shipping_address']['street_address'] = [
      '#type' => 'textfield',
      '#title' => t('Street address'),
      '#braintree_field' => 'additionalInformation.shippingAddress.streetAddress',
      '#braintree_php_field' => 'shipping.streetAddress',
    ];
    $fields['shipping_address']['extended_address'] = [
      '#type' => 'textfield',
      '#title' => t('Extended address'),
      '#braintree_field' => 'additionalInformation.shippingAddress.extendedAddress',
      '#braintree_php_field' => 'shipping.extendedAddress',
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
      '#braintree_php_field' => 'shipping.locality',
    ];
    $fields['shipping_address']['region'] = [
      '#type' => 'textfield',
      '#title' => t('Region'),
      '#braintree_field' => 'additionalInformation.shippingAddress.region',
      '#braintree_php_field' => 'shipping.region',
    ];
    $fields['shipping_address']['postal_code'] = [
      '#type' => 'textfield',
      '#title' => t('Postal code'),
      '#braintree_field' => 'additionalInformation.shippingAddress.postalCode',
      '#braintree_php_field' => 'shipping.postalCode',
    ];
    $fields['shipping_address']['country'] = [
      '#type' => 'select',
      '#title' => t('Country'),
      '#braintree_field' => 'additionalInformation.shippingAddress.countryCodeAlpha2',
      '#braintree_php_field' => 'shipping.countryCodeAlpha2',
      '#options' => country_get_list(),
    ];

    return $fields;
  }

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
   * Extract customer data from a submitted form element.
   */
  public static function getData($element) {
    $extra_data = [];
    ElementTree::applyRecursively($element, function (&$element, $key, &$parent) use (&$extra_data) {
      if (isset($element['#braintree_php_field'])) {
        drupal_array_set_nested_value($extra_data, explode('.', $element['#braintree_php_field']), $element['#value']);
      }
    });
    return $extra_data;
  }

}
