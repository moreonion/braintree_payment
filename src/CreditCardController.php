<?php

namespace Drupal\braintree_payment;

class CreditCardController extends \PaymentMethodController implements \Drupal\webform_paymethod_select\PaymentRecurrentController {
  public $controller_data_defaults = array(
    'merchant_id' => '',
    'private_key' => '',
    'public_key'  => '',
    'field_map' => [],
    'enable_recurrent_payments' => 0,
  );

  public function __construct() {
    $this->title = t('Braintree Credit Card');

    $this->payment_configuration_form_elements_callback = 'payment_forms_payment_form';
    $this->payment_method_configuration_form_elements_callback = 'payment_forms_method_configuration_form';
  }

  public function paymentForm() {
    return new CreditCardForm();
  }

  public function configurationForm() {
    return new CreditCardConfigurationForm();
  }

  /**
   * {@inheritdoc}
   */
  function validate(\Payment $payment, \PaymentMethod $method, $strict) {
    parent::validate($payment, $method, $strict);

    // @TODO: Which version?
    /* if (version_compare($library['version'], '3', '<')) { */
    /*   throw new \PaymentValidationException(t('stripe_payment needs at least version 3 of the stripe-php library (installed: @version).', array('@version' => $library['version']))); */
    /* } */
    if (!($library = libraries_detect('braintree-php')) || empty($library['installed'])) {
      throw new \PaymentValidationException(t('The braintree-php library could no tbe found.'));
    }

    // @TODO: Recurring payments
    if ($payment->contextObj && ($interval = $payment->contextObj->value('donation_interval'))) {
      if (empty($method->controller_data['enable_recurrent_payments']) && in_array($interval, ['m', 'y'])) {
        throw new \PaymentValidationException(t('Recurrent payments are disabled for this payment method.'));
      }
    }
  }

  public function execute(\Payment $payment) {
    $this->libraries_load('braintree-php');
    watchdog('braintree_info', 'blablabla', WATCHDOG_ERROR);

    $context = $payment->contextObj;
    $plan_id = NULL;

    // @TODO: How much data do we want to store?
    $customer = $this->createCustomer($payment, $context);
    watchdog('braintree_info', json_encode($customer), WATCHDOG_ERROR);

    $this->setBraintreeSettings(
      'sandbox', //@TODO: Change to production
      $payment->method->controller_data['merchant_id'],
      $payment->method->controller_data['public_key'],
      $payment->method->controller_data['private_key']
    );
    watchdog('braintree_info', json_encode(get_object_vars($payment)), WATCHDOG_ERROR);

    // @TODO: Merchant ID should set the currency?
    $transaction_result = \Braintree\Transaction::sale([
      'amount' => $payment->totalAmount(0),
      'paymentMethodNonce' => $payment->method_data['braintree-payment-nonce'],
      'customer' => $customer,
      'options' => [
        //@TODO: I guess we want to settle the transaction
        //https://developers.braintreepayments.com/reference/general/statuses#authorized
        'submitForSettlement' => true
      ]
    ]);

    watchdog('braintree_info', json_encode(get_object_vars($transaction_result)), WATCHDOG_ERROR);

    //@TODO: We don't store customers payment methods:
    //https://developers.braintreepayments.com/reference/request/transaction/sale/php#new-customer-with-new-payment-method
    if($transaction_result->success &&
      $transaction_result->transaction->status === 'submitted_for_settlement')
    {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_SUCCESS));
      $this->entity_save('payment', $payment);

      $params = array(
        'pid'       => $payment->pid,
        'braintree_id' => $transaction_result->transaction->id,
        'type'      => $transaction_result->transaction->paymentInstrumentType,
        'plan_id'   => $plan_id,
      );
      if(!$this->drupal_write_record('braintree_payment', $params)) {
        watchdog('braintree_payment', 'Record creation failed', WATCHDOG_ERROR);
      }
    } else {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_FAILED));
      entity_save('payment', $payment);

      $message =
        '@method payment method encountered an error while contacting ' .
        'the braintree server. The status code "@status" and the error ' .
        'message "@message". (pid: @pid, pmid: @pmid)';
      $variables = array(
        '@status'   => $transaction_result->code,
        '@message'  => $transaction_result->message,
        '@pid'      => $payment->pid,
        '@pmid'     => $payment->method->pmid,
        '@method'   => $payment->method->title_specific,
      );

      drupal_set_message($transaction_result->message);
      watchdog('braintree_payment', $message, $variables, WATCHDOG_ERROR);
    }
  }

  /**
   * This method is "overloaded" to enable mocking in testing scenarios.
   */
  protected function drupal_set_message($msg) {
    return drupal_set_message($msg);
  }

  /**
   * This method is "overloaded" to enable mocking in testing scenarios.
   */
  protected function watchdog($scope, $msg, $log_level) {
    return watchdog($scope, $msg, $log_level);
  }

  /**
   * This method is "overloaded" to enable mocking in testing scenarios.
   */
  protected function drupal_write_record($table, $params) {
    return drupal_write_record($table, $params);
  }

  /**
   * This method is "overloaded" to enable mocking in testing scenarios.
   */
  protected function entity_save($entity_name, $entity_data) {
    return entity_save($entity_name, $entity_data);
  }

  /**
   * This method is "overloaded" to enable mocking in testing scenarios.
   */
  protected function libraries_load($library) {
    return libraries_load($library);
  }

  private function createCustomer(\Payment $payment, $context){
    return array(
      'firstName' => $context->value('first_name'),
      'lastName' => $context->value('last_name'),
      'email' => $context->value('email')
    );
  }

  private function setBraintreeSettings($env, $merch, $pub, $priv) {
    \Braintree\Configuration::environment($env);
    \Braintree\Configuration::merchantId($merch);
    \Braintree\Configuration::publicKey($pub);
    \Braintree\Configuration::privateKey($priv);
  }
}
