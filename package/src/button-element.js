import { MethodElement } from './method-element'

class ButtonElement extends MethodElement {
  /**
   * Initializes a new ButtonElement.
   *
   * @param {JQuery} $element - The element to attach to.
   * @param {object} settings - A settings object.
   */
  constructor ($element, settings) {
    super($element, settings)
    this.hidePaymethodSelectRadio()
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
   * Select the hidden paymethod select radio for this payment method.
   */
  selectRadio () {
    if (this.$paymethodRadio) {
      this.$paymethodRadio.filter('input').prop('checked', true).trigger('change')
    }
  }

  /**
   * Render the pay button.
   *
   * @param {JQuery} $button - The pay button element.
   */
  renderButton ($button) {
    if (this.$paymethodRadio) {
      // Set button size to match paymethod select radio labels.
      $button.css('height', this.$paymethodRadio.filter('label').css('height'))
      // Append button after the paymethod select radios.
      this.$paymethodRadio.parent().closest('.paymethod-select-radios').append($button)
    }
    else {
      this.$element.append($button)
    }
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

export { ButtonElement }
