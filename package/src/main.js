/* global Drupal, jQuery */

import { CreditCardElement } from './credit-card-element'

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
    const methodSettings = settings.braintree_payment['pmid_' + pmid]

    let element = {}
    switch (methodSettings.method) {
      case 'braintree_payment_credit_card':
        element = new CreditCardElement($method, methodSettings)
        break
    }

    Drupal.payment_handler[pmid] = function (pmid, $method, submitter) {
      element.validate(submitter)
    }
  })
}
