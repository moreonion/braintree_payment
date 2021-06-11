/* global Drupal, jQuery, braintree, ApplePaySession */

import { ButtonElement } from './button-element'

const $ = jQuery

class ApplePayElement extends ButtonElement {
  /**
   * Initializes a new ApplePayElement.
   *
   * @param {JQuery} $element - The element to attach to.
   * @param {object} settings - A settings object.
   */
  constructor ($element, settings) {
    super($element, settings)
    this.applePay = {}
    if (this.checkCompatibility()) {
      this.waitForLibrariesThenInit()
    }
  }

  /**
   * Check if Apple Pay is supported by the browser.
   */
  checkCompatibility () {
    if (typeof ApplePaySession !== 'undefined' && ApplePaySession.supportsVersion(3) && ApplePaySession.canMakePayments()) {
      return true
    }
    else {
      this.$element.append(`<p>${Drupal.t('This browser does not support Apple Pay.')}</p>`)
      return false
    }
  }

  /**
   * Make sure the Braintree libraries have been loaded before using them.
   */
  waitForLibrariesThenInit () {
    if (typeof braintree !== 'undefined' && typeof braintree.client !== 'undefined' && typeof braintree.applePay !== 'undefined') {
      this.initPayButton()
    }
    else {
      window.setTimeout(() => {
        this.waitForLibrariesThenInit()
      }, 100)
    }
  }

  /**
   * Initialize the Apple Pay button.
   */
  initPayButton () {
    braintree.client.create({
      authorization: this.settings.payment_token
    }).then((clientInstance) => {
      return braintree.applePay.create({
        client: clientInstance
      })
    }).then((applePayInstance) => {
      this.applePay.instance = applePayInstance
      const $button = this.generateButton()
      this.renderButton($button)
      $button.on('click', () => {
        this.selectRadio()
        const paymentRequest = applePayInstance.createPaymentRequest(this.settings.requestData)
        const session = this.applePay.session = new ApplePaySession(3, paymentRequest)
        session.onvalidatemerchant = this.validateMerchantHandler.bind(this)
        session.onpaymentauthorized = this.paymentAuthorizedHandler.bind(this)
        session.begin()
      })
    }).catch((err) => {
      console.error(err)
      this.errorHandler(err.message || err)
    })
  }

  /**
   * Generate Apple Pay button markup and styles.
   */
  generateButton () {
    return $(`
    <button
      type="button"
      class="button braintree apple-pay"
      aria-label="Apple Pay"
      lang=${document.documentElement.lang.substring(0, 2)}
    ><span>Apple Pay</span>
    </button>`)
  }

  /**
   * Request a new merchant session.
   */
  validateMerchantHandler (event) {
    this.applePay.instance.performValidation({
      validationURL: event.validationURL,
      displayName: this.settings.displayName
    }).then((merchantSession) => {
      this.applePay.session.completeMerchantValidation(merchantSession)
    }).catch((validationErr) => {
      console.error(validationErr)
      this.errorHandler(validationErr.message || validationErr)
      this.applePay.session.abort()
    })
  }

  /**
   * Finalize the transaction.
   */
  paymentAuthorizedHandler (event) {
    // If requested, address information is accessible in event.payment.
    this.applePay.instance.tokenize({
      token: event.payment.token
    }).then((payload) => {
      this.setNonce(payload.nonce)
      // Dismiss the Apple Pay sheet.
      this.applePay.session.completePayment(ApplePaySession.STATUS_SUCCESS)
      this.submitForm()
    }).catch((tokenizeErr) => {
      console.error(tokenizeErr)
      this.errorHandler(tokenizeErr.message || tokenizeErr)
      this.applePay.session.completePayment(ApplePaySession.STATUS_FAILURE)
    })
  }
}

export { ApplePayElement }
