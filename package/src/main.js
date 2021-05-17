/* global Drupal, jQuery */

import { CreditCardElement } from './credit-card-element'
import { GooglePayElement } from './google-pay-element'

const $ = jQuery
Drupal.behaviors.braintree_payment = {}
Drupal.behaviors.braintree_payment.element_map = {
  'braintree_payment_credit_card': CreditCardElement,
  'braintree_payment_google_pay': GooglePayElement,
}
Drupal.behaviors.braintree_payment.attach = function (context, settings) {
  const behavior = this
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

    if (methodSettings.method in behavior.element_map) {
      const element = new behavior.element_map[methodSettings.method]($method, methodSettings)
      Drupal.payment_handler[pmid] = function (pmid, $method, submitter) {
        element.validate(submitter)
      }
    }
  })
}
