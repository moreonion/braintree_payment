<?php

namespace Drupal\braintree_payment;

use Braintree\ClientTokenGateway;
use Braintree\Gateway;
use Drupal\little_helpers\ArrayConfig;
use Drupal\payment_context\NullPaymentContext;
use Upal\DrupalUnitTestCase;

/**
 * Test the payment form.
 */
class CreditCardFormTest extends DrupalUnitTestCase {

  /**
   * Create a payment method stub for testing.
   */
  protected function paymentMethodStub() {
    $controller = new CreditCardController();
    $mock_gateway = $this->createMock(Gateway::class);
    $mock_gateway->method('clientToken')->willReturn($this->createMock(ClientTokenGateway::class));
    $controller->setGateway($mock_gateway);
    $method = new \PaymentMethod([
      'controller' => $controller,
      'controller_data' => [],
    ]);
    ArrayConfig::mergeDefaults($method->controller_data, $controller->controller_data_defaults);
    return $method;
  }

  /**
   * Create a payment stub for testing.
   */
  protected function paymentStub() {
    $method = $this->paymentMethodStub();
    $context = $this->createMock(NullPaymentContext::class);
    $payment = new \Payment([
      'description' => 'braintree test payment',
      'currency_code' => 'EUR',
      'method' => $method,
      'contextObj' => $context,
    ]);
    return $payment;
  }

  /**
   * Test rendering the method configration form with defaults.
   */
  public function testConfigFormWithDefaults() {
    $method = $this->paymentMethodStub();
    $form = new CreditCardConfigurationForm();
    $form_state = [];
    $form_array = $form->form([], $form_state, $method);
    $this->assertArrayHasKey('force_liability_shift', $form_array);
  }

  /**
   * Test rendering the form with an empty context.
   */
  public function testFormEmptyContext() {
    $payment = $this->paymentStub();
    $form = $payment->method->controller->paymentForm();
    $form_state = [];
    $element = $form->form([], $form_state, $this->paymentStub());
    $this->assertNotEmpty($element['extra_data']['email']);
  }

}
