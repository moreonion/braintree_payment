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
  $element = $vars['element'] + [
    '#wrapper_classes' => [],
  ];
  $element['#wrapper_classes'][] = 'braintree-hosted-fields-wrapper';
  $vars['content'] = $element['#children'];
  $vars['attributes'] = $element['#attributes'] ?? [];
  $vars['attributes']['class'] = $element['#wrapper_classes'];
  $vars['attributes']['data-braintree-hosted-fields-field'] = $element['#field_name'];
}
