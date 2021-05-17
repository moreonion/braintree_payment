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

export { GooglePayElement }
