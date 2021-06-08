/* global jQuery, braintree, google */

import { ButtonElement } from './button-element'

const $ = jQuery

class GooglePayElement extends ButtonElement {
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
    if (typeof google !== 'undefined' && typeof google.payments !== 'undefined' && typeof google.payments.api !== 'undefined' && typeof braintree !== 'undefined' && typeof braintree.client !== 'undefined' && typeof braintree.googlePayment !== 'undefined') {
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
    this.paymentsClient = new google.payments.api.PaymentsClient({
      environment: this.settings.sandbox ? 'TEST' : 'PRODUCTION',
    })
    braintree.client.create({
      authorization: this.settings.payment_token
    }).then((clientInstance) => {
      return braintree.googlePayment.create({
        client: clientInstance,
        googlePayVersion: 2,
        googleMerchantId: this.settings.googlePayMerchantId,
      })
    }).then((googlePaymentInstance) => {
      this.googlePaymentInstance = googlePaymentInstance
      return this.paymentsClient.isReadyToPay({
        apiVersion: 2,
        apiVersionMinor: 0,
        allowedPaymentMethods: googlePaymentInstance.createPaymentDataRequest().allowedPaymentMethods,
        existingPaymentMethodRequired: true
      })
    }).then((response) => {
      if (response.result) {
        // Use an explicit closure because the click handler explicitly binds
        // `this` to the triggering element.
        const element = this
        const button = this.paymentsClient.createButton({
          buttonSizeMode: 'fill',
          buttonType: this.settings.googlePayButtonType,
          buttonColor: this.settings.googlePayButtonColor,
          buttonLocale: document.documentElement.lang.substring(0, 2),
          onClick: function () {
            element.showPaymentForm()
          },
        })
        this.renderButton($(button))
      }
    })
  }

  /**
   * Callback function for the Google Pay button.
   */
  showPaymentForm () {
    this.selectRadio()
    const paymentDataRequest = this.googlePaymentInstance.createPaymentDataRequest({
      transactionInfo: this.settings.transactionInfo,
    })
    const cardPaymentMethod = paymentDataRequest.allowedPaymentMethods[0]
    this.paymentsClient.loadPaymentData(paymentDataRequest).then((paymentData) => {
      return this.googlePaymentInstance.parseResponse(paymentData)
    }).then((result) => {
      // result.type may be either "AndroidPayCard" or "PayPalAccount", and
      // paymentData will contain the billingAddress for card payments
      this.setNonce(result.nonce)
      this.submitForm()
    }).catch((err) => {
      this.resetValidation()
      this.errorHandler(err.statusMessage || err.statusCode)
    })
  }
}

export { GooglePayElement }
