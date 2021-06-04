/* global braintree, google */

import { MethodElement } from './method-element'

class GooglePayElement extends MethodElement {
  /**
   * Initializes a new GooglePayElement.
   *
   * @param {JQuery} $element - The element to attach to.
   * @param {object} settings - A settings object.
   */
  constructor ($element, settings) {
    super($element, settings)
    this.hidePaymethodSelectRadio()
    this.waitForLibrariesThenInit()
  }

  /**
   * Hide the paymethod select radio for this payment method.
   */
  hidePaymethodSelectRadio () {
    const pmid = this.$element.data('pmid')
    const $radio = this.$element.closest('form').find(`[name*="[paymethod_select]"][value=${pmid}]`)
    if ($radio.length) {
      const $label = $radio.siblings(`label[for="${$radio.attr('id')}"]`)
      this.$paymethodRadio = $radio.add($label).hide()
    }
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
        if (this.$paymethodRadio) {
          // Set button size to match paymethod select radio labels.
          $(button).css('height', this.$paymethodRadio.filter('label').css('height'))
          // Append button after the paymethod select radios.
          this.$paymethodRadio.parent().closest('.paymethod-select-radios').append(button)
        }
        else {
          this.$element.append(button)
        }
      }
    })
  }

  /**
   * Callback function for the Google Pay button.
   */
  showPaymentForm () {
    this.$paymethodRadio.filter('input').prop('checked', true).trigger('change')
    const paymentDataRequest = this.googlePaymentInstance.createPaymentDataRequest({
      transactionInfo: this.settings.transactionInfo,
    })
    const cardPaymentMethod = paymentDataRequest.allowedPaymentMethods[0]
    cardPaymentMethod.parameters.billingAddressRequired = true
    cardPaymentMethod.parameters.billingAddressParameters = {
      format: 'FULL',
      phoneNumberRequired: true
    }
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

  /**
   * Submit the surrounding form.
   */
  submitForm () {
    // As a heuristic assume that the first submit button without formnovalidate
    // is the one we should trigger.
    this.$element.closest('form').find('[type="submit"]:not([formnovalidate])').first().click()
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
