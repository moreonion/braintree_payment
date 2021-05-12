/* global Drupal, jQuery, braintree */

const $ = jQuery

function deepSet (obj, keys, value) {
  const key = keys.shift()
  if (keys.length > 0) {
    if (typeof obj[key] === 'undefined') {
      obj[key] = {}
    }
    deepSet(obj[key], keys, value)
  }
  else {
    obj[key] = value
  }
}

class MethodElement {
  constructor ($element, settings) {
    this.$element = $element
    this.settings = $.extend(settings, {
      wrapperClasses: ['form-control'],
    })
    this.form_id = this.$element.closest('form').attr('id')
    this.waitForLibrariesThenInit()
  }

  waitForLibrariesThenInit () {
    if (typeof braintree !== 'undefined' && typeof braintree.client !== 'undefined' && typeof braintree.hostedFields !== 'undefined' && braintree.threeDSecure !== 'undefined') {
      this.initFields()
    }
    else {
      window.setTimeout(() => {
        this.waitForLibrariesThenInit()
      }, 100)
    }
  }

  getStyles () {
    let styles
    const ret = {}
    const $element = $('<div class="form-item"><input type="text" class="default" /><input type="text" class="error" /><select><option>One</option></select></div>').hide().appendTo(this.$element)
    styles = window.getComputedStyle($element.find('input.default').get(0))
    ret.input = {
      'color': styles.getPropertyValue('color'),
      'font': styles.getPropertyValue('font'),
      'line-height': styles.getPropertyValue('line-height'),
    }
    styles = window.getComputedStyle($element.find('input.error').get(0))
    ret['input.invalid'] = {
      'color': styles.getPropertyValue('color'),
    }
    styles = window.getComputedStyle($element.find('select').get(0))
    ret.select = {
      'font': styles.getPropertyValue('font'),
    }
    $element.remove()
    return ret
  }

  startLoading () {
    this.$element.addClass('loading')
    $('<div class="loading-wrapper"><div class="throbber"></div></div>').appendTo(this.$element.children('.fieldset-wrapper'))
  }

  stopLoading () {
    this.$element.find('.loading-wrapper').remove()
    this.$element.removeClass('loading')
  }

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
      this.deepSet(data, keys, value)
    })
    return data
  }

  /**
   * Display error messages.
   * @param {object} error - The Braintree error data.
   * @param {jquery} $field - The field that caused the error.
   */
  errorHandler (error, $field = null) {
    // Trigger clientside validation for respective field.
    if (this.clientsideValidationEnabled()) {
      const validator = Drupal.myClientsideValidation.validators[this.form_id]
      if ($field && $field.attr('name')) {
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
    // Without clientside validation render a message above the form.
    else {
      const $message = $('<div class="messages error">').text(error)
      $message.addClass('braintree-error').insertBefore(this.$element.closest('form'))
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
