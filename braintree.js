(function ($) {

  function deepSet(obj, keys, value) {
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
      this.form_id = this.$element.closest('form').attr('id');
      this.initFields()
    }
    getStyles() {
      let $element = $('<div class="form-item"><input type="text" /></div>').hide().appendTo(this.$element)
      let styles = window.getComputedStyle($element.find('input').get(0))
      let ret = {
        input: {
          'font': styles.getPropertyValue('font'),
          'line-height': styles.getPropertyValue('line-height'),
        },
      }
      $element.remove()
      return ret
    }
    initFields() {
      braintree.client.create({
        authorization: this.settings.payment_token
      }).then((clientInstance) => {
        this.client = clientInstance
        let fields = {}
        this.$element.find('[data-braintree-hosted-field]').each(function() {
          let name = $(this).attr('data-braintree-hosted-field')
          fields[name] = {
            selector: '#' + $(this).attr('id'),
          }
        })
        return braintree.hostedFields.create({
          client: clientInstance,
          styles: this.getStyles(),
          fields: fields,
        })
      }).then((hostedFieldsInstance) => {
        this.hostedFields = hostedFieldsInstance
        return braintree.threeDSecure.create({
          version: 2,
          client: this.client,
        })
      }).then((threeDSecureInstance) => {
        this.client3ds = threeDSecureInstance
      })
    }
    setNonce(value) {
      this.$element.find('[name$="[braintree-payment-nonce]"]').val(value)
    }
    extraData() {
      var data = {}
      this.$element.find('[data-braintree-name]').each(function() {
        var keys = $(this).attr('data-braintree-name').split('.')
        var value = $(this).val()
        deepSet(data, keys, value)
      })
      return data
    }
    validate(submitter) {
      $('.mo-dialog-wrapper').addClass('visible');
      if (typeof Drupal.clientsideValidation !== 'undefined') {
        $('#clientsidevalidation-' + this.form_id + '-errors ul').empty();
      }

      this.hostedFields.tokenize().then((payload) => {
        return this.client3ds.verifyCard($.extend({}, this.extraData(), {
          nonce: payload.nonce,
          bin: payload.details.bin,
          onLookupComplete: function (data, next) {
            next()
          }
        }))
      }).then((response) => {
        // Put nonce into the hidden field.
        this.setNonce(response.nonce)
        // Submit form
        submitter.ready();
      }).catch((err) => {
        var msg = err.message;
        if(msg.length > 0) {
          this.errorHandler(msg);
        } else {
          this.errorHandler(err);
        }
        submitter.error();
      })
    }
    errorHandler(error) {
      var settings, wrapper, child
      if (typeof Drupal.clientsideValidation !== 'undefined') {
        settings = Drupal.settings.clientsideValidation['forms'][this.form_id];
        wrapper = document.createElement(settings.general.wrapper);
        child = document.createElement(settings.general.errorElement);
        child.className = settings.general.errorClass;
        child.innerHTML = error;
        wrapper.appendChild(child);

        $('#clientsidevalidation-' + this.form_id + '-errors ul')
        .append(wrapper).show()
        .parent().show();
      } else {
        if ($('#messages').length === 0) {
          $('<div id="messages"><div class="section clearfix">' +
            '</div></div>').insertAfter('#header');
        }
        $('<div class="messages error">' + error + '</div>')
          .appendTo("#messages .clearfix");
      }
    }
  }

  Drupal.behaviors.braintree_payment = {};
  Drupal.behaviors.braintree_payment.attach = function(context, settings) {
    var self = this;
    if (!Drupal.payment_handler) {
      Drupal.payment_handler = {};
    }
    $('input[name$="braintree-payment-nonce]"]', context).each(function() {
      var $method = $(this).closest('.payment-method-form')
      var pmid = $method.attr('data-pmid')
      var element = new MethodElement($method, settings.braintree_payment['pmid_' + pmid])

      Drupal.payment_handler[pmid] = function (pmid, $method, submitter) {
        element.validate(submitter)
      }
    })
  };

})(jQuery);
