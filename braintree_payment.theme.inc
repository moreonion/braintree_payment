<?php

/**
 * @file
 * Theme processing functions for braintree_payment.
 */

/**
 * Prepares variables for braintree hosted field templates.
 *
 * @param array $vars
 *   An associative array containing:
 *   - element: The rendered form API element.
 */
function template_process_braintree_payment_hosted_field(array &$vars) {
  $vars['attributes'] = $vars['element']['#attributes'] ?? [];
  $vars['attributes'] += [
    'id' => $vars['element']['#id'] ?? drupal_html_id('braintree-hosted-field'),
    'data-braintree-hosted-field' => $vars['element']['#field_name'],
  ];
  $vars['attributes']['class'][] = 'braintree-payment-hosted-field-iframe-wrapper';
  $vars['attributes']['class'][] = 'text-input';
}
