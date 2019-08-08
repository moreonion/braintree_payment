<?php

namespace Drupal\braintree_payment;

use \Braintree\Configuration;
use \Braintree\Transaction;

/**
 * Defines the controller class of the Braintree payment method.
 */
class CreditCardController extends \PaymentMethodController {
  public $controller_data_defaults = array(
    'environment' => 'sandbox',
    'merchant_id' => '',
    'merchant_account_id' => '',
    'private_key' => '',
    'public_key'  => '',
    'billing_data' => [],
    'enable_recurrent_payments' => 0,
  );

  /**
   * Sets up title and configuration form callbacks.
   */
  public function __construct() {
    $this->title = t('Braintree Credit Card');

    $this->payment_configuration_form_elements_callback = 'payment_forms_payment_form';
    $this->payment_method_configuration_form_elements_callback = 'payment_forms_method_configuration_form';
  }

  /**
   * Returns a new instance of CreditCardForm.
   */
  public function paymentForm() {
    return new CreditCardForm();
  }

  /**
   * Returns a new instance of CreditCardConfigurationForm.
   */
  public function configurationForm() {
    return new CreditCardConfigurationForm();
  }

  /**
   * {@inheritdoc}
   */
  public function validate(\Payment $payment, \PaymentMethod $method, $strict) {
    parent::validate($payment, $method, $strict);

    if (!($library = libraries_detect('braintree-php')) || empty($library['installed'])) {
      throw new \PaymentValidationException(t('The braintree-php library could not be found.'));
    }

    if (empty($method->controller_data['enable_recurrent_payments'])) {
      foreach ($payment->line_items as $line_item) {
        if (!empty($line_item->recurrence) && !empty($line_item->recurrence->interval_unit)) {
          throw new \PaymentValidationException(t('Recurrent payments are disabled for this payment method.'));
        }
      }
    }
  }

  /**
   * Executes a transaction.
   */
  public function execute(\Payment $payment) {
    $this->libraries_load('braintree-php');

    $plan_id = NULL;

    $account_id = $this->setBraintreeSettings($payment);
    $data = [
      'amount' => $payment->totalAmount(TRUE),
      'paymentMethodNonce' => $payment->method_data['braintree-payment-nonce'],
      'billing' => $payment->method_data['billing_data'],
      'options' => [
        'submitForSettlement' => TRUE,
      ],
    ];
    if ($account_id) {
      $data['merchantAccountId'] = $account_id;
    }
    $transaction_result = \Braintree\Transaction::sale($data);

    if ($transaction_result->success &&
      $transaction_result->transaction->status === 'submitted_for_settlement')
    {
      $payment->braintree = [
        'braintree_id' => $transaction_result->transaction->id,
        'type'      => $transaction_result->transaction->paymentInstrumentType,
        'plan_id'   => $plan_id,
      ];
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_SUCCESS));
      $this->entity_save('payment', $payment);
    }
    else {
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

  /**
   * Creates a new customer.
   */
  private function createCustomer(\Payment $payment, $context) {
    return array(
      'firstName' => $context->value('first_name'),
      'lastName' => $context->value('last_name'),
      'email' => $context->value('email'),
    );
  }

  /**
   * Sets the braintree configuration variables.
   */
  public function setBraintreeSettings(\Payment $payment) {
    $cd = $payment->method->controller_data + $this->controller_data_defaults;
    Configuration::environment($cd['environment']);
    Configuration::merchantId($cd['merchant_id']);
    Configuration::publicKey($cd['public_key']);
    Configuration::privateKey($cd['private_key']);
    return $cd['merchant_account_id'];
  }

}
