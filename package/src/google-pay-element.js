/* global Drupal, jQuery, braintree, google */

import { MethodElement } from './method-element'

const $ = jQuery

class GooglePayElement extends MethodElement {
  /**
   * Initializes a new GooglePayElement.
   *
   * @param {JQuery} $element - The element to attach to.
   * @param {object} settings - A settings object.
   */
  constructor ($element, settings) {
    super($element, settings)
    this.waitForLibrariesThenInit()
  }

  /**
   * Make sure the Braintree libraries have been loaded before using them.
   */
  waitForLibrariesThenInit () {
    if (typeof google !== 'undefined' && typeof google.payments !== 'undefined' && typeof google.payments.api !== 'undefined' && typeof braintree !== 'undefined' && typeof braintree.googlePayment !== 'undefined') {
      this.initPayButton()
    }
    else {
      window.setTimeout(() => {
        this.waitForLibrariesThenInit()
      }, 100)
    }
  }

  /**
   * Initialize the Google Pay button.
   */
  initPayButton () {
    const paymentsClient = new google.payments.api.PaymentsClient({
      environment: 'TEST' // Or 'PRODUCTION'
    })
    braintree.client.create({
      authorization: this.settings.payment_token
    }).then((clientInstance) => {
      return braintree.googlePayment.create({
        client: clientInstance,
        googlePayVersion: 2,
        googleMerchantId: 'merchant-id-from-google',
      })
    }).then((googlePaymentInstance) => {
      return paymentsClient.isReadyToPay({
        // see https://developers.google.com/pay/api/web/reference/object#IsReadyToPayRequest for all options
        apiVersion: 2,
        apiVersionMinor: 0,
        allowedPaymentMethods: googlePaymentInstance.createPaymentDataRequest().allowedPaymentMethods,
        existingPaymentMethodRequired: true
      })
    }).then((response) => {
      if (response.result) {
        const button = paymentsClient.createButton({
          onClick: () => {
            console.log('TODO click handler')
          },
        })
        this.$element.append(button)
      }
    })
  }

  /**
   * Validate the input data.
   *
   * @param {object} submitter - The Drupal form submitter.
   */
  validate (submitter) {
    submitter.ready()
  }
}

export { GooglePayElement }
