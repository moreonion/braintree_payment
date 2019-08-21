(function ($) {

  class MethodElement {
    constructor ($element) {
      this.$element = $element
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
  }

  Drupal.behaviors.braintree_payment = {};
  Drupal.behaviors.braintree_payment.attach = function(context, settings) {
    if($('input[name$="braintree-payment-nonce]"]', context).length > 0) {
      if (!Drupal.payment_handler) {
        Drupal.payment_handler = {};
      }
      var self = this;
      for (var key in settings.braintree_payment) {
        var pmid = settings.braintree_payment[key].pmid;
        Drupal.payment_handler[pmid] = function(pmid, $method, submitter) {
          self.validateHandler(settings.braintree_payment['pmid_' + pmid], $method, submitter);
        };
      }
    }
  };

  Drupal.behaviors.braintree_payment.validateHandler = function(settings, $method, submitter) {
    var element = new MethodElement($method)
    this.form_id = $method.closest('form').attr('id');

    $('.mo-dialog-wrapper').addClass('visible');
    if (typeof Drupal.clientsideValidation !== 'undefined') {
      $('#clientsidevalidation-' + this.form_id + '-errors ul').empty();
    }
    
    var client;
    var client3ds;

    function errorHandler(err) {
      var msg = err.message;
      if(msg.length > 0) {
        Drupal.behaviors.braintree_payment.errorHandler(msg);
      } else {
        Drupal.behaviors.braintree_payment.errorHandler(err);
      }
      submitter.error();
    }

    braintree.client.create({
      authorization: settings.payment_token
    }).then(function (clientInstance) {
      client = clientInstance;
      return braintree.threeDSecure.create({
        version: 2,
        client: clientInstance,
      })
    }).then(function (threeDSecureInstance) {
      return {
        client: client,
        client3ds: threeDSecureInstance,
      }
    }).then(function (c) {
      return c.client.request({
        endpoint: 'payment_methods/credit_cards',
        method: 'post',
        data: {creditCard: element.readCardData()},
        options: {
          validate: true
        }
      }).then(function (response) {
        return c.client3ds.verifyCard({
          amount: $method.find('input[data-braintree-name="amount"]').val(),
          nonce: response.creditCards[0].nonce,
          bin: response.creditCards[0].details.bin,
          email: '',
          billingAddress: {
          },
          onLookupComplete: function (data, next) {
            next()
          }
        })
      })
      .then(function (response) {
        // Put nonce into the hidden field.
        element.setNonce(response.nonce)
        // Now get rid of all the creditcard data.
        element.clear()
        // Submit form
        submitter.ready();
      }).catch(errorHandler);
    }).catch(errorHandler);
  };

  Drupal.behaviors.braintree_payment.errorHandler = function(error) {
    var settings, wrapper, child;
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
  };
})(jQuery);
