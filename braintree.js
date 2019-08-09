(function ($) {
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
    this.form_id = $method.closest('form').attr('id');

    var $ccn = $method.find('[name$="[credit_card_number]"]');
    var $cvv = $method.find('[name$="[secure_code]"]');
    var $expiry_month = $method.find('[name$="[expiry_date][month]"]');
    var $expiry_year = $method.find('[name$="[expiry_date][year]"]');
    var $nonce = $method.find('[name$="[braintree-payment-nonce]"]');

    $('.mo-dialog-wrapper').addClass('visible');
    if (typeof Drupal.clientsideValidation !== 'undefined') {
      $('#clientsidevalidation-' + this.form_id + '-errors ul').empty();
    }
    
    var client;
    var client3ds;

    var data = {
      creditCard: {
        number: $ccn.val(),
        cvv: $cvv.val(),
        expirationDate: $expiry_month.val() + '/' + $expiry_year.val()
      }
    };

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
        data: data,
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
        $nonce.val(response.nonce)
        // Now get rid of all the creditcard data.
        $ccn.val('');
        $cvv.val('');
        $expiry_month.val('');
        $expiry_year.val('');
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
