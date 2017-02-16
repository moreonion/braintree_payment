import unittest
from selenium import webdriver

from drupal_helper import DrupalHelper
from donation_helper import DonationHelper

class TestBraintreePaymentDonationPages(unittest.TestCase):
    def setUp(self):
        self.driver = webdriver.Firefox()
        dh = DrupalHelper('http://pristine-camp.localhost:7000', self.driver)

        self.assertTrue(dh.login('admin', ''), 'Check correctness of login credentials')

        self.donation_helper = DonationHelper(
            'http://pristine-camp.localhost:7000/wizard/donation',
            self.driver
        )


    def tearDown(self):
        # self.driver.quit()
        pass

    def test_create_donation_page(self):
        self.assertTrue(
            self.donation_helper.create_page(),
            'Couldn\'t create donation page.'
        )

if __name__ == '__main__':
    unittest.main()
