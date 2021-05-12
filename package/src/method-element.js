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
   * Sets a value on a (nested) key.
   *
   * @param {object} obj - The object to update.
   * @param {array} keys - The path of keys to the value.
   * @param value - The new value.
   */
  static deepSet (obj, keys, value) {
    const key = keys.shift()
    if (keys.length > 0) {
      if (typeof obj[key] === 'undefined') {
        obj[key] = {}
      }
      MethodElement.deepSet(obj[key], keys, value)
    }
    else {
      obj[key] = value
    }
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
   * Collects values from extra data fields.
   */
  extraData () {
    const data = {}
    this.$element.find('[data-braintree-name]').each(function () {
      const keys = $(this).attr('data-braintree-name').split('.')
      const value = $(this).val()
      MethodElement.deepSet(data, keys, value)
    })
    return data
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
   * Checks whether clientside validation is enabled for this form.
   */
  clientsideValidationEnabled () {
    return typeof Drupal.clientsideValidation !== 'undefined' &&
           typeof Drupal.myClientsideValidation.validators[this.form_id] !== 'undefined' &&
           typeof Drupal.settings.clientsideValidation.forms[this.form_id] !== 'undefined'
  }
}

export { MethodElement }
