from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

class PaymentHelper:
    TIMEOUT = 10
    add_method = '/method/add/'

    def __init__(self, url, driver):
        """
        :param url: The base URL of the payment services.
        :param driver: The selenium webdriver instance.
        """
        self.url = url
        self.driver = driver

    def addMethod(self, method_url, fields):
        """
        Adds a new payment method.
        :param method_url: The URL name of the payment method (e.g. braintree_payment_credit_card)
        :param fields: A dictionary specifying the values of the fields to be added.
        The keys of the dictionaries are expected to be IDs of text input elements.
        :return: True, if the method was added, false otherwise.
        """
        self.driver.get(self.url + self.add_method + method_url)

        for fieldname in fields:
          field = WebDriverWait(self.driver, self.TIMEOUT).until(
            EC.presence_of_element_located((By.ID, fieldname))
          )
          field.send_keys(fields[fieldname])

        field.submit()

        WebDriverWait(self.driver, self.TIMEOUT).until(
            EC.staleness_of(field)
        )

        return ('Add' not in self.driver.title)
