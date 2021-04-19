/* global Drupal, jQuery */

import { MethodElement } from './method-element'

const $ = jQuery
Drupal.behaviors.braintree_payment = {}
Drupal.behaviors.braintree_payment.attach = function (context, settings) {
  if (!Drupal.payment_handler) {
    Drupal.payment_handler = {}
  }
  $('input[name$="braintree-payment-nonce]"]', context).each(function () {
    if (!document.body.contains(this)) {
      // Guard against running for unmounted elements.
      return
    }
    const $method = $(this).closest('.payment-method-form')
    const pmid = $method.attr('data-pmid')
    const element = new MethodElement($method, settings.braintree_payment['pmid_' + pmid])

    Drupal.payment_handler[pmid] = function (pmid, $method, submitter) {
      element.validate(submitter)
    }
  })
}
