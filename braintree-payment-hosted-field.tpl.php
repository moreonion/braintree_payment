<?php

/**
 * @file
 * Displays a wrapper for a branitree hosted field.
 *
 * Available variables:
 *   - $element: The renderable array for the element.
 *   - $atttributes: The attributes created by the pre-processors.
 *   - $content: The rendered content of the element.
 *
 * @see template_process_braintree_payment_hosted_field()
 *
 * @ingroup themeable
 */
?>
<div <?php echo drupal_attributes($attributes) ?>><?php echo $content; ?></div>
