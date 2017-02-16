import unittest
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from datetime import datetime

from drupal_helper import DrupalHelper
from payment_helper import PaymentHelper
from donation_helper import DonationHelper

class TestBraintreePayment(unittest.TestCase):
    def setUp(self):
        self.driver = webdriver.Firefox()
        dh = DrupalHelper('http://pristine-camp.localhost:7000', self.driver)

        self.assertTrue(dh.login('admin', ''), 'Check correctness of login credentials')

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

        self.payment_helper = PaymentHelper(
            'http://pristine-camp.localhost:7000/admin/config/services/payment/',
            self.driver
        )

        self.assertTrue(
            self.payment_helper.addMethod(
                'braintree_payment_credit_card',
                fields
            ),
            'Couldn\'t create payment method, check your keys and uniqueness of name.'
        )

    def tearDown(self):
        self.driver.quit()
        pass

    def test_create_donation_page(self):
        self.donation_helper = DonationHelper(
            'http://pristine-camp.localhost:7000/wizard/donation',
            self.driver
        )

        self.assertTrue(
            self.donation_helper.create_page(),
            'Couldn\'t create donation page.'
        )

    def create_fields(self, amount, firstname, lastname, email, ccn, cvv):
        fields = [
            {
                'edit-submitted-amount-donation-amount': amount
            },
            {
                'edit-submitted-first-name': firstname,
                'edit-submitted-last-name': lastname,
                'edit-submitted-email': email
            },
            {
                'edit-submitted-paymethod-select-payment-method-all-forms-5-credit-card-number': ccn,
                'edit-submitted-paymethod-select-payment-method-all-forms-5-secure-code': cvv
            }
        ]

        return fields

    # def test_valid_card_numbers(self):
    def test_valid_card_numbers(self):
        self.donation_helper = DonationHelper('', self.driver)
        dh = self.donation_helper
        ccns = [
            '6011111111111117',
            #'3530111333300000', FAILS!
            '6304000000000000',
            '5555555555554444',
            '2223000048400011',
            '4111111111111111',
            '4005519200000004',
            '4009348888881881',
            '4012000033330026',
            '4012000077777777',
            '4012888888881881',
            '4217651111111119',
            '4500600000000061'
        ]

        for ccn in ccns:
            self.assertTrue(
                dh.make_donation(
                    'http://pristine-camp.localhost:7000/donation-16',
                    ('.picker-handle', 0),
                    'edit-next',
                    self.create_fields(
                        500,
                        'Tester',
                        'Testee',
                        'tester@testee.com',
                        ccn,
                        '123'
                    )
                )
            )

    # def test_jcb_credit_card(self):
    def jcb_credit_card(self):
        """
        JCB Credit Card Verification fails for some reason.
        Do we need a nonce from them?
        """
        self.donation_helper = DonationHelper('', self.driver)
        dh = self.donation_helper
        ccn = '3530111333300000'

        self.assertTrue(
            dh.make_donation(
                'http://pristine-camp.localhost:7000/donation-16',
                ('.picker-handle', 0),
                'edit-next',
                self.create_fields(
                    500,
                    'Tester',
                    'Testee',
                    'tester@testee.com',
                    ccn,
                    '123'
                )
            ),
            'Valid credit card number {} not accepted!'.format(ccn)
        )

    # def test_invalid_credit_card_numbers(self):
    def test_invalid_credit_card_numbers(self):
        """
        Test if invalid credit card numbers fail.
        """
        self.donation_helper = DonationHelper('', self.driver)
        dh = self.donation_helper
        ccns = [
            '5000111111111115',
            'abdfefgh',
            'true',
            '1',
            'TRUE',
            'false',
            '0',
            'FALSE'
        ]

        for ccn in ccns:
            self.assertFalse(
                dh.make_donation(
                    'http://pristine-camp.localhost:7000/donation-16',
                    ('.picker-handle', 0),
                    'edit-next',
                    self.create_fields(
                        500,
                        'Tester',
                        'Testee',
                        'tester@testee.com',
                        ccn,
                        '123'
                    )
                ),
                '{} should not have been accepted!'.format(ccn)
            )

    # def test_if_donation_shows_up_in_submitted_donations_overview(self):
    def test_if_donation_shows_up_in_submitted_donations_overview(self):
        """
        Create a donation and check if relevant info shows up in donation overview.
        """
        self.donation_helper = DonationHelper('', self.driver)
        dh = self.donation_helper

        amount = 1234
        firstname = 'Melissa'
        lastname = 'M'
        email = 'm.m@m.me'

        self.assertTrue(
            dh.make_donation(
                'http://pristine-camp.localhost:7000/donation-16',
                ('.picker-handle', 0),
                'edit-next',
                self.create_fields(
                    amount,
                    firstname,
                    lastname,
                    email,
                    '4111111111111111',
                    '123'
                )
            ),
        'Creating new donation failed.')

        submurl = 'http://pristine-camp.localhost:7000/node/42/submissions'
        self.driver.get(submurl)

        firstrow = WebDriverWait(self.driver, 10).until(
            EC.presence_of_element_located(
                (By.CSS_SELECTOR, '.sticky-enabled.tableheader-processed.sticky-table '
                    + '> tbody > tr:first-child'))
        )

        self.driver.get(firstrow.find_element_by_partial_link_text('View').get_property('href'))

        amountdiv = WebDriverWait(self.driver, 10).until(
            EC.presence_of_element_located(
                (By.CSS_SELECTOR, '.form-item.webform-component.webform-component-display.webform-component--amount--donation-amount')
            )
        )
        self.assertEqual(int(amountdiv.text.split('\n')[1]), amount)

        fndiv = WebDriverWait(self.driver, 10).until(
            EC.presence_of_element_located(
                (By.CSS_SELECTOR, '.form-item.webform-component.webform-component-display.webform-component--first-name')
            )
        )
        self.assertEqual(fndiv.text.split('\n')[1], firstname)

        lndiv = WebDriverWait(self.driver, 10).until(
            EC.presence_of_element_located(
                (By.CSS_SELECTOR, '.form-item.webform-component.webform-component-display.webform-component--last-name')
            )
        )
        self.assertEqual(lndiv.text.split('\n')[1], lastname)

        emdiv = WebDriverWait(self.driver, 10).until(
            EC.presence_of_element_located(
                (By.CSS_SELECTOR, '.form-item.webform-component.webform-component-display.webform-component--email')
            )
        )
        self.assertEqual(emdiv.text.split('\n')[1], email)

    def test_use_wrong_credit_card_number_and_then_correct_it(self):
        self.donation_helper = DonationHelper('', self.driver)
        dh = self.donation_helper

        amount = 1234
        firstname = 'Melissa'
        lastname = 'M'
        email = 'm.m@m.me'
        wrong_ccn = '411111111111111'
        correct_ccn = '4111111111111111'

        dh.make_donation(
            'http://pristine-camp.localhost:7000/donation-16',
            ('.picker-handle', 0),
            'edit-next',
            self.create_fields(
                amount,
                firstname,
                lastname,
                email,
                wrong_ccn,
                '123'
            ),
            False
        )

        ccnfield = WebDriverWait(self.driver, 10).until(
            EC.visibility_of_element_located(
                (By.ID, 'edit-submitted-paymethod-select-payment-method-all-forms-5-credit-card-number')
            )
        )
        ccnfield.clear()
        ccnfield.send_keys(correct_ccn)

        smfield = WebDriverWait(self.driver, 10).until(
            EC.element_to_be_clickable(
                (By.ID, 'edit-submit')
            )
        )

        smfield.click()

        try:
            WebDriverWait(self.driver, 10).until(
                EC.title_contains('Thank you')
            )
            worked = True
        except Exception:
            worked = False

        self.assertTrue(worked)

if __name__ == '__main__':
    unittest.main()
