<?php

namespace Drupal\braintree_payment;

use Braintree\Gateway;
use Braintree\Transaction;

/**
 * Defines the controller class of the Braintree payment method.
 */
class BraintreeController extends \PaymentMethodController {

  public $controller_data_defaults = [
    'environment' => 'sandbox',
    'merchant_id' => '',
    'merchant_account_id' => '',
    'private_key' => '',
    'public_key'  => '',
    'force_liability_shift' => FALSE,
    'input_settings' => [],
    'enable_recurrent_payments' => 0,
  ];

  protected $gateway = NULL;

  /**
   * Create a new controller instance.
   */
  public function __construct() {
    $this->payment_configuration_form_elements_callback = 'payment_forms_payment_form';
    $this->payment_method_configuration_form_elements_callback = 'payment_forms_method_configuration_form';
  }

  /**
   * Set the braintree gateway to use.
   *
   * @param Braintree\Gateway $gateway
   *   The gateway.
   */
  public function setGateway(Gateway $gateway) {
    $this->gateway = $gateway;
  }

  /**
   * Get braintree gateway based on the controller settings.
   *
   * @param \PaymentMethod $method
   *   The payment method to get the API-client for.
   *
   * @return \Braintree\Gateway
   *   A configured braintree gateway for this payment method.
   */
  public function getGateway(\PaymentMethod $method) {
    if (!$this->gateway) {
      $cd = $method->controller_data;
      $this->gateway = new Gateway([
        'environment' => $cd['environment'],
        'merchantId' => $cd['merchant_id'],
        'publicKey' => $cd['public_key'],
        'privateKey' => $cd['private_key'],
      ]);
    }
    return $this->gateway;
  }

  /**
   * Get a customer data form.
   *
   * @return CustomerDataForm
   *   A new customer data form.
   */
  public function customerDataForm() {
    return new CustomerDataForm();
  }

  /**
   * Get a form for configuring the payment method.
   *
   * @return BraintreeConfigurationForm
   *   A new configuration form.
   */
  public function configurationForm() {
    return new BraintreeConfigurationForm();
  }

  /**
   * Check whether this payment method is available for a payment.
   *
   * @param \Payment $payment
   *   The payment to validate.
   * @param \PaymentMethod $method
   *   The payment method to check against.
   * @param bool $strict
   *   Whether to validate everything a payment for this method needs.
   *
   * @throws PaymentValidationException
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
   * Request a new client token.
   *
   * @param \PaymentMethod $method
   *   The payment method to use.
   */
  public function getClientToken(\PaymentMethod $method) {
    $data = [];
    if (!empty($method->controller_data['merchant_account_id'])) {
      $data['merchantAccountId'] = $method->controller_data['merchant_account_id'];
    }
    return $this->getGateway($method)->clientToken()->generate($data);
  }

  /**
   * Execute the payment transaction.
   *
   * @param \Payment $payment
   *   The payment to execute.
   *
   * @return bool
   *   Whether the payment was successfully executed or not.
   */
  public function execute(\Payment $payment) {
    $this->libraries_load('braintree-php');

    $data = [
      'amount' => $payment->totalAmount(TRUE),
      'paymentMethodNonce' => $payment->method_data['braintree-payment-nonce'],
      'options' => [
        'submitForSettlement' => TRUE,
      ],
    ] + $payment->method_data['extra_data'];
    if (!empty($payment->method->controller_data['merchant_account_id'])) {
      $data['merchantAccountId'] = $payment->method->controller_data['merchant_account_id'];
    }
    $result = $this->getGateway($payment->method)->transaction()->sale($data);

    if ($result->success && $result->transaction->status === 'submitted_for_settlement') {
      $payment->braintree = [
        'braintree_id' => $result->transaction->id,
        'type' => $result->transaction->paymentInstrumentType,
        'threeds_status' => $result->transaction->threeDSecureInfo->status,
      ];
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_SUCCESS));
      $this->entity_save('payment', $payment);
    }
    else {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_FAILED));
      $this->entity_save('payment', $payment);

      $variables['@method'] = $payment->method->title_specific;
      $variables['@pid'] = $payment->pid;
      $variables['@pmid'] = $payment->method->pmid;
      if (!empty($result->transaction)) {
        $message = "@method — Transaction status: {$result->transaction->status} (pid: @pid, pmid: @pmid)";
      }
      else {
        $message = '@method — Got an error response for a transaction sale request: @errors (pid: @pid, pmid: @pmid)';
        $variables['@errors'] = implode("\n", array_map(function ($error) {
          return "{$error->code} - {$error->message}";
        }, $result->errors->deepAll()));
      }
      $this->watchdog('braintree_payment', $message, $variables, WATCHDOG_ERROR);
    }
  }

  /**
   * This method is "overloaded" to enable mocking in testing scenarios.
   */
  protected function watchdog($scope, $msg, $variables, $log_level) {
    return watchdog($scope, $msg, $variables, $log_level);
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

}
