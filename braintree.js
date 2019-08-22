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
    initFields() {
      braintree.client.create({
        authorization: this.settings.payment_token
      }).then((clientInstance) => {
        this.client = clientInstance;
        return braintree.threeDSecure.create({
          version: 2,
          client: clientInstance,
        })
      }).then((threeDSecureInstance) => {
        this.client3ds = threeDSecureInstance
      })
    }
    readCardData() {
      var month = this.$element.find('[name$="[expiry_date][month]"]').val()
      var year = this.$element.find('[name$="[expiry_date][year]"]').val()
      return {
        number: this.$element.find('[name$="[credit_card_number]"]').val(),
        cvv: this.$element.find('[name$="[secure_code]"]').val(),
        expirationDate: `${month}/${year}`,
      }
    }
    clear() {
      this.$element.find('[name$="[expry_date][month]"]').val('')
      this.$element.find('[name$="[expry_date][year]"]').val('')
      this.$element.find('[name$="[credit_card_number]"]').val('')
      this.$element.find('[name$="[secure_code]"]').val('')
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

      this.client.request({
        endpoint: 'payment_methods/credit_cards',
        method: 'post',
        data: {creditCard: this.readCardData()},
        options: {
          validate: true
        }
      }).then((response) => {
        return this.client3ds.verifyCard($.extend({}, this.extraData(), {
          nonce: response.creditCards[0].nonce,
          bin: response.creditCards[0].details.bin,
          onLookupComplete: function (data, next) {
            next()
          }
        }))
      }).then((response) => {
        // Put nonce into the hidden field.
        this.setNonce(response.nonce)
        // Now get rid of all the creditcard data.
        this.clear()
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
