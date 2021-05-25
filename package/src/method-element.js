/* global Drupal, jQuery */

const $ = jQuery

class MethodElement {
  /**
  * Initializes a new MethodElement.
  *
  * @param {JQuery} $element - The element to attach to.
  * @param {object} settings - A settings object.
  */
  constructor ($element, settings) {
    this.$element = $element
    this.settings = $.extend(settings, {
      wrapperClasses: ['form-control'],
    })
    this.form_id = this.$element.closest('form').attr('id')
    this.errorHandler = this.clientsideValidationEnabled ? this.clientsideValidationErrorHandler : this.fallbackErrorHandler
  }

  /**
   * Displays a loading animation.
   */
  startLoading () {
    this.$element.addClass('loading')
    $('<div class="loading-wrapper"><div class="throbber"></div></div>').appendTo(this.$element.children('.fieldset-wrapper'))
  }

  /**
   * Removes the loading animation.
   */
  stopLoading () {
    this.$element.find('.loading-wrapper').remove()
    this.$element.removeClass('loading')
  }

  /**
   * Sets the nonce value on the form.
   *
   * @param {string} value - The nonce value.
   */
  setNonce (value) {
    this.$element.find('[name$="[braintree-payment-nonce]"]').val(value)
  }

  /**
   * Displays error messages using Drupal Clientside Validation.
   *
   * @param {object} error - The Braintree error data.
   * @param {JQuery} $field - The field that caused the error.
   */
  clientsideValidationErrorHandler (error, $field = null) {
    const validator = Drupal.myClientsideValidation.validators[this.form_id]
    if ($field && $field.attr('name')) {
      // Trigger clientside validation for respective field.
      const errors = {}
      errors[$field.attr('name')] = error
      // Needed so jQuery validate will find the element when removing errors.
      validator.currentElements.push($field)
      // Trigger validation error.
      validator.showErrors(errors)
    }
    else {
      // The error is not related to a payment field, reconstruct error markup.
      const settings = Drupal.settings.clientsideValidation.forms[this.form_id].general
      const $message = $(`<${settings.errorElement} class="${settings.errorClass}">`).text(error)
      const $wrapper = $('#clientsidevalidation-' + this.form_id + '-errors')
      // Add message to clientside validation wrapper if there is one.
      if ($wrapper.length) {
        const $list = $wrapper.find('ul')
        $message.wrap(`<${settings.wrapper}>`).parent().addClass('braintree-error').appendTo($list)
        $list.show()
        $wrapper.show()
      }
      // Show message above the payment fieldset in want of a better place.
      else {
        $message.addClass('braintree-error').insertBefore(this.$element)
      }
    }
  }

  /**
   * Displays error messages without Drupal Clientside Validation.
   *
   * @param {object} error - The Braintree error data.
   */
  fallbackErrorHandler (error) {
    // Render a message above the form.
    const $message = $('<div class="messages error">').text(error)
    $message.addClass('braintree-error').insertBefore(this.$element.closest('form'))
  }

  /**
   * Remove all validation errors from previous attempts.
   */
  resetValidation () {
    $('.mo-dialog-wrapper').addClass('visible')
    $('.braintree-error').remove()
    if (this.clientsideValidationEnabled()) {
      const $validator = Drupal.myClientsideValidation.validators[this.form_id]
      $validator.prepareForm()
      $validator.hideErrors()
    }
  }

  /**
   * Checks whether clientside validation is enabled for this form.
   */
  clientsideValidationEnabled () {
    return typeof Drupal.clientsideValidation !== 'undefined' &&
           typeof Drupal.myClientsideValidation.validators[this.form_id] !== 'undefined' &&
           typeof Drupal.settings.clientsideValidation.forms[this.form_id] !== 'undefined'
  }
}

export { MethodElement }
