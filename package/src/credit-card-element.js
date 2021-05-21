/* global Drupal, jQuery, braintree */

import { MethodElement } from './method-element'

const $ = jQuery

class CreditCardElement extends MethodElement {
  /**
   * Initializes a new CreditCardElement.
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
    if (typeof braintree !== 'undefined' && typeof braintree.client !== 'undefined' && typeof braintree.hostedFields !== 'undefined' && braintree.threeDSecure !== 'undefined') {
      this.initFields()
    }
    else {
      window.setTimeout(() => {
        this.waitForLibrariesThenInit()
      }, 100)
    }
  }

  /**
   * Copy CSS property values from a fake input to hosted fields.
   */
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

  /**
   * Initialize empty containers with Braintree hosted fields (iframes for form input).
   */
  initFields () {
    this.startLoading()
    braintree.client.create({
      authorization: this.settings.payment_token
    }).then((clientInstance) => {
      this.client = clientInstance
      this.$wrappers = this.$element.find('.braintree-hosted-fields-wrapper')
      const fields = {}
      this.$wrappers.each(function () {
        const $this = $(this)
        const name = $this.data('braintreeHostedFieldsField')
        const settings = {
          container: this,
        }
        const $input = $this.children('input, select')
        if ($input.get(0).tagName === 'SELECT') {
          settings.select = true
          $this.addClass('select-input')
        }
        else {
          settings.placeholder = this.getAttribute('placeholder')
          $this.addClass('text-input')
        }
        fields[name] = settings
        $this.css({
          'height': $input.outerHeight(),
          'box-sizing': 'border-box',
        })
      })
      return braintree.hostedFields.create({
        client: clientInstance,
        styles: this.getStyles(),
        fields: $.extend(true, {}, this.settings.fields, fields),
      })
    }).then((hostedFieldsInstance) => {
      this.hostedFields = hostedFieldsInstance
      // The type attributes conflict with jquery.validate 1.11. Remove them.
      this.$wrappers.find('iframe').removeAttr('type')
      this.$wrappers.addClass('braintree-hosted-fields-processed')
      this.$wrappers.addClass(this.settings.wrapperClasses.join(' '))
      return braintree.threeDSecure.create({
        version: 2,
        client: this.client,
      })
    }).then((threeDSecureInstance) => {
      this.client3ds = threeDSecureInstance
      this.stopLoading()
    })
  }

  /**
   * Validate the input data.
   *
   * @param {object} submitter - The Drupal form submitter.
   */
  validate (submitter) {
    this.resetValidation()
    this.$wrappers.removeClass('invalid')
    this.hostedFields.tokenize().then((payload) => {
      return this.client3ds.verifyCard($.extend({}, this.extraData(), {
        nonce: payload.nonce,
        bin: payload.details.bin,
        onLookupComplete: function (data, next) {
          next()
        }
      }))
    }).then((response) => {
      const info3ds = response.threeDSecureInfo
      if (!info3ds.liabilityShifted && (info3ds.liabilityShiftPossible || this.settings.forceLiabilityShift)) {
        // Liability shift didn’t occur.
        this.errorHandler(Drupal.t('Card verification failed. Please choose another form of payment.'))
        submitter.error()
      }
      else {
        // Everything good: Set nonce and submit the form.
        this.setNonce(response.nonce)
        submitter.ready()
      }
    }).catch((err) => {
      if (err.code === 'HOSTED_FIELDS_FIELDS_INVALID') {
        for (const key in err.details.invalidFields) {
          const field = err.details.invalidFields[key]
          field.classList.add('invalid')
          const $label = $(`label[for='${$('input, select', $(field)).attr('id')}']`)
          if (err.details.invalidFieldKeys.length === 1) {
            // This should work for multiple invalid fields too, but they all get attached to the
            // first invalid field and replace the previous message → bug in clientside_validation?
            this.errorHandler(Drupal.t('Invalid @field_name', { '@field_name': $label.text() }), $(field))
          }
          else {
            this.errorHandler(Drupal.t('Invalid @field_name', { '@field_name': $label.text() }))
          }
        }
      }
      else {
        const msg = err.message
        if (msg.length > 0) {
          this.errorHandler(msg)
        }
        else {
          this.errorHandler(err)
        }
      }
      submitter.error()
    })
  }
}

export { CreditCardElement }
