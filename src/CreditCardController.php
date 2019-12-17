<?php

namespace Drupal\braintree_payment;

use Braintree\Gateway;
use Braintree\Transaction;

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
    'force_liability_shift' => FALSE,
    'input_settings' => [
      'email' => [
        'enabled' => TRUE,
        'display' => 'hidden',
        'keys' => ['email'],
        'required' => FALSE,
      ],
      'billing_address' => [
        'first_name' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['first_name', 'given_name'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'last_name' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['last_name'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'company' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['company'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'street_address' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['street_address', 'address_line2'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'address_line2' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['first_name', 'given_name'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'country' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['country'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'postcode' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['postcode', 'zip_code'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'city' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['city'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'region' => [
          'enabled' => TRUE,
          'display' => 'hidden',
          'keys' => ['region'],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
      ],
      'shipping_address' => [
        'given_name' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'surname' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'street_address' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'extended_address' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'line3' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'locality' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'region' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'postal_code' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
        'country' => [
          'enabled' => FALSE,
          'display' => 'hidden',
          'keys' => [],
          'required' => FALSE,
          'display_other' => 'hidden',
        ],
      ],
    ],
    'enable_recurrent_payments' => 0,
  );

  protected $gateway = NULL;

  /**
   * Sets up title and configuration form callbacks.
   */
  public function __construct() {
    $this->title = t('Braintree Credit Card');

    $this->payment_configuration_form_elements_callback = 'payment_forms_payment_form';
    $this->payment_method_configuration_form_elements_callback = 'payment_forms_method_configuration_form';
  }

  /**
   * Set the braintree gateway to use.
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
   * Request a new client token.
   */
  public function getClientToken(\PaymentMethod $method) {
    $data = [];
    if (!empty($method->controller_data['merchant_account_id'])) {
      $data['merchantAccountId'] = $method->controller_data['merchant_account_id'];
    }
    return $this->getGateway($method)->clientToken()->generate($data);
  }

  /**
   * Executes a transaction.
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
      entity_save('payment', $payment);

      $message =
        '@method payment method encountered an error while contacting ' .
        'the braintree server. The status code "@status" and the error ' .
        'message "@message". (pid: @pid, pmid: @pmid)';
      $variables = array(
        '@status'   => $result->code,
        '@message'  => $result->message,
        '@pid'      => $payment->pid,
        '@pmid'     => $payment->method->pmid,
        '@method'   => $payment->method->title_specific,
      );

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
