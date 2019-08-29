/* global Drupal, jQuery, braintree */

var $ = jQuery

function deepSet (obj, keys, value) {
  var key = keys.shift()
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
    this.settings = settings
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
    let ret = {}
    let $element = $('<div class="form-item"><input type="text" class="default" /><input type="text" class="error" /><select><option>One</option></select></div>').hide().appendTo(this.$element)
    styles = window.getComputedStyle($element.find('input.default').get(0))
    ret['input'] = {
      'color': styles.getPropertyValue('color'),
      'font': styles.getPropertyValue('font'),
      'line-height': styles.getPropertyValue('line-height'),
    }
    styles = window.getComputedStyle($element.find('input.error').get(0))
    ret['input.invalid'] = {
      'color': styles.getPropertyValue('color'),
    }
    styles = window.getComputedStyle($element.find('select').get(0))
    ret['select'] = {
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
  initFields () {
    this.startLoading()
    braintree.client.create({
      authorization: this.settings.payment_token
    }).then((clientInstance) => {
      this.client = clientInstance
      this.$wrappers = this.$element.find('.braintree-hosted-fields-wrapper')
      let fields = {}
      this.$wrappers.each(function () {
        let $this = $(this)
        let name = $this.data('braintreeHostedFieldsField')
        let settings = {
          container: this,
        }
        let $input = $this.children('input, select')
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
      this.$wrappers.addClass('braintree-hosted-fields-processed')
      return braintree.threeDSecure.create({
        version: 2,
        client: this.client,
      })
    }).then((threeDSecureInstance) => {
      this.client3ds = threeDSecureInstance
      this.stopLoading()
    })
  }
  setNonce (value) {
    this.$element.find('[name$="[braintree-payment-nonce]"]').val(value)
  }
  extraData () {
    var data = {}
    this.$element.find('[data-braintree-name]').each(function () {
      var keys = $(this).attr('data-braintree-name').split('.')
      var value = $(this).val()
      deepSet(data, keys, value)
    })
    return data
  }
  validate (submitter) {
    $('.mo-dialog-wrapper').addClass('visible')
    if (typeof Drupal.clientsideValidation !== 'undefined') {
      $('#clientsidevalidation-' + this.form_id + '-errors ul').empty()
    }

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
      let info3ds = response.threeDSecureInfo
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
          err.details.invalidFields[key].classList.add('invalid')
        }
      }
      var msg = err.message
      if (msg.length > 0) {
        this.errorHandler(msg)
      }
      else {
        this.errorHandler(err)
      }
      submitter.error()
    })
  }
  errorHandler (error) {
    var settings, wrapper, child
    if (typeof Drupal.clientsideValidation !== 'undefined') {
      settings = Drupal.settings.clientsideValidation['forms'][this.form_id]
      wrapper = document.createElement(settings.general.wrapper)
      child = document.createElement(settings.general.errorElement)
      child.className = settings.general.errorClass
      child.innerHTML = error
      wrapper.appendChild(child)

      $('#clientsidevalidation-' + this.form_id + '-errors ul')
        .append(wrapper).show()
        .parent().show()
    }
    else {
      if ($('#messages').length === 0) {
        $('<div id="messages"><div class="section clearfix"></div></div>').insertAfter('#header')
      }
      $('<div class="messages error">' + error + '</div>').appendTo('#messages .clearfix')
    }
  }
}

export { MethodElement }
