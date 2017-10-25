(function ($) {
  Drupal.behaviors.braintree_payment = {};
  Drupal.behaviors.braintree_payment.attach = function(context, settings) {
    if($('input[name$="braintree-payment-nonce]"]', context).length > 0) {
      this.settings = settings.braintree_payment;
      if (!Drupal.payment_handler) {
        Drupal.payment_handler = {};
      }

      for (var pmid in this.settings) {
        Drupal.payment_handler[pmid] = function(pmid, $method, submitter) {
          Drupal.behaviors.braintree_payment.validateHandler(pmid, $method, submitter);
        };
      }
    }
  };

  Drupal.behaviors.braintree_payment.validateHandler = function(pmid, $method, submitter) {
    this.form_id = $method.closest('form').attr('id');

    var ccn_name = '[name$="['+ pmid + '][credit_card_number]"]';
    var cvv_name = '[name$="['+ pmid + '][secure_code]"]';

    var ccn = $method.find(ccn_name)[0].value;
    var cvv = $method.find(cvv_name)[0].value;

    var expiry_month_name = '[name$="[' + pmid + '][expiry_date][month]"]';
    var expiry_year_name = '[name$="[' + pmid + '][expiry_date][year]"]';

    var expiry_month = $method.find(expiry_month_name)[0];
    var expiry_year = $method.find(expiry_year_name)[0];
    var expiry_date = expiry_month.value + '/' + expiry_year.value;

    $('.mo-dialog-wrapper').addClass('visible');
    if (typeof Drupal.clientsideValidation !== 'undefined') {
      $('#clientsidevalidation-' + this.form_id + '-errors ul').empty();
    }

    var getField = function(name) {
      if (name instanceof Array) { name = name.join(']['); }
      return $method.find('[name$="[' + name + ']"]');
    };

    braintree.client.create({
      authorization: this.settings[pmid].payment_token
    }, function(clientErr, clientInstance) {
      var data = {
        creditCard: {
          number: ccn,
          cvv: cvv,
          expirationDate: expiry_date
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
        getField(pmid + '][braintree-payment-nonce').val(response.creditCards[0].nonce);

        // Now get rid of all the creditcard data
        ccn = '';
        cvv = '';
        expiry_month = ''
        expiry_year = ''
        expiry_date = ''
        $(ccn_name).val('');
        $(cvv_name).val('');
        $(expiry_month_name).val('');
        $(expiry_year_name).val('');

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
