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

    braintree.client.create({
      authorization: settings.payment_token
    }, function(clientErr, clientInstance) {
      if (clientErr) {
        var detail_msg = clientErr.details.originalError.error.message;

        if(detail_msg.length > 0) {
          Drupal.behaviors.braintree_payment.errorHandler(detail_msg);
        } else {
          Drupal.behaviors.braintree_payment.errorHandler(requestErr);
        }

        submitter.error();
        return;
      }

      var data = {
        creditCard: {
          number: $ccn.val(),
          cvv: $cvv.val(),
          expirationDate: $expiry_month.val() + '/' + $expiry_year.val()
        }
      };

      clientInstance.request({
        endpoint: 'payment_methods/credit_cards',
        method: 'post',
        data: data,
        options: {
          validate: true
        }
      }, function (requestErr, response) {
        if (requestErr) {
          var detail_msg = requestErr.details.originalError.error.message;
          if (detail_msg.length > 0) {
            Drupal.behaviors.braintree_payment.errorHandler(detail_msg);
          } else {
            Drupal.behaviors.braintree_payment.errorHandler(requestErr);
          }

          submitter.error();
          return;
        }

        // set the nonce we received
        $nonce.val(response.creditCards[0].nonce);

        // Now get rid of all the creditcard data
        $ccn.val('');
        $cvv.val('');
        $expiry_month.val('');
        $expiry_year.val('');

        // Submit form
        submitter.ready();
      });
    });
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
