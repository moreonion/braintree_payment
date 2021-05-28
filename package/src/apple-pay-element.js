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
  initPayButton () {}

  /**
   * Validate the input data.
   *
   * @param {object} submitter - The Drupal form submitter.
   */
  validate (submitter) {
    submitter.ready()
  }
}

export { ApplePayElement }
