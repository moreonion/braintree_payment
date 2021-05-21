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
    const element = this
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
      element.googlePaymentInstance = googlePaymentInstance
      return paymentsClient.isReadyToPay({
        apiVersion: 2,
        apiVersionMinor: 0,
        allowedPaymentMethods: googlePaymentInstance.createPaymentDataRequest().allowedPaymentMethods,
        existingPaymentMethodRequired: true
      })
    }).then((response) => {
      if (response.result) {
        const button = paymentsClient.createButton({
          onClick: function () {
            const paymentDataRequest = element.googlePaymentInstance.createPaymentDataRequest({
              transactionInfo: element.settings.transactionInfo,
            })
            const cardPaymentMethod = paymentDataRequest.allowedPaymentMethods[0]
            cardPaymentMethod.parameters.billingAddressRequired = true
            cardPaymentMethod.parameters.billingAddressParameters = {
              format: 'FULL',
              phoneNumberRequired: true
            }
            paymentsClient.loadPaymentData(paymentDataRequest).then((paymentData) => {
              return element.googlePaymentInstance.parseResponse(paymentData)
            }).then((result) => {
              element.setNonce(result.nonce)
              element.submitForm()
              // Send result.nonce to your server
              // result.type may be either "AndroidPayCard" or "PayPalAccount", and
              // paymentData will contain the billingAddress for card payments
            }).catch((err) => {
              element.errorHandler(err)
            })
          },
        })
        this.$element.append(button)
      }
    })
  }

  submitForm() {
    this.$element.closest('form').find('input[type="submit"]:not([formnovalidate])').click()
  }

  /**
   * Validate the input data.
   *
   * @param {object} submitter - The Drupal form submitter.
   */
  validate (submitter) {
    this.resetValidation()
    const nonce = this.$element.find('[name$="[braintree-payment-nonce]"]').val()
    if (nonce.length > 0) {
      submitter.ready()
    }
    else {
      submitter.error()
    }
  }
}

export { GooglePayElement }
