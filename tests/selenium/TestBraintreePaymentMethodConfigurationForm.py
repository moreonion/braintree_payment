import unittest
from selenium import webdriver

from drupal_helper import DrupalHelper
from payment_helper import PaymentHelper

class TestBraintreePaymentMethodConfigurationForm(unittest.TestCase):
    def setUp(self):
        self.driver = webdriver.Firefox()
        dh = DrupalHelper('http://pristine-camp.localhost:7000', self.driver)

        self.assertTrue(dh.login('admin', ''), 'Check correctness of login credentials')

        self.payment_helper = PaymentHelper(
            'http://pristine-camp.localhost:7000/admin/config/services/payment/',
            self.driver
        )


    def tearDown(self):
        self.driver.quit()
        pass

    def test_create_payment_method(self):
        card_name = 'TestCard_{}'.format(datetime.now())
        merchant_id = ""
        public_key = ""
        private_key = ""

        fields = {
            'edit-title-specific': card_name,
            'edit-controller-form-merchant-id': merchant_id,
            'edit-controller-form-public-key': public_key,
            'edit-controller-form-private-key': private_key
        }

        self.assertTrue(
            self.payment_helper.addMethod(
                'braintree_payment_credit_card',
                fields
            ),
            'Couldn\'t create payment method, check your keys and uniqueness of name.'
        )

if __name__ == '__main__':
    unittest.main()
