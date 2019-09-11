/* global Drupal, jQuery */

import { MethodElement } from './method-element'

var $ = jQuery
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
    var $method = $(this).closest('.payment-method-form')
    var pmid = $method.attr('data-pmid')
    var element = new MethodElement($method, settings.braintree_payment['pmid_' + pmid])

    Drupal.payment_handler[pmid] = function (pmid, $method, submitter) {
      element.validate(submitter)
    }
  })
}
