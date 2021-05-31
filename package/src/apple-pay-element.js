/* global Drupal, jQuery, braintree, ApplePaySession */

import { MethodElement } from './method-element'

const $ = jQuery

class ApplePayElement extends MethodElement {
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
      this.$element.append($button)
      $button.on('click', () => {
        const paymentRequest = applePayInstance.createPaymentRequest(this.settings.requestData)
        const session = this.applePay.session = new ApplePaySession(3, paymentRequest)
        session.onvalidatemerchant = this.validateMerchantHandler
        session.onpaymentauthorized = this.paymentAuthorizedHandler
        session.begin()
      })
    }).catch((err) => {
      this.errorHandler(err.message || err)
    })
  }

  /**
   * Generate Apple Pay button markup and styles.
   */
  generateButton () {
    const $button = $(`
    <button
      type="button"
      class="button apple-pay"
      aria-label="Apple Pay"
      lang=${document.documentElement.lang.substring(0, 2)}
    ><span>Apple Pay</span>
    </button>`)
    const $styles = `<style type="text/css">
    @supports (-webkit-appearance: -apple-pay-button) {
      button.apple-pay {
        -webkit-appearance: -apple-pay-button;
        -apple-pay-button-style: ${this.settings.buttonColor};
        width: 100%;
      }
      button.apple-pay span {
        visibility: hidden;
      }
    }
    </style>`
    $('head').append($styles)
    return $button
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
      this.errorHandler(validationErr.message || validationErr)
      this.applePay.session.abort()
    })
  };

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
    }).catch((tokenizeErr) => {
      this.errorHandler(tokenizeErr.message || tokenizeErr)
      this.applePay.session.completePayment(ApplePaySession.STATUS_FAILURE)
    })
  };

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

export { ApplePayElement }
