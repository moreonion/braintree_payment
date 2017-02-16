from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

class DrupalHelper:
    def __init__(self, url, driver):
        """
        :param url: The URL pointing to the base of your drupal installation.
        :param driver: The selenium webdriver instance.
        """
        self.url = url
        self.driver = driver

    def login(self, username, password, relative_path="/user/"):
        """
        :param username: The username of the account.
        :param password: The password of the account.
        :param relative_path: The path to the login page (defaults to /user/).
        :return: True if login was successful, else false.
        """
        self.driver.get(self.url + relative_path)

        title = self.driver.find_element_by_id('page-title')

        userfield = self.driver.find_element_by_id('edit-name')
        userfield.send_keys(username)

        passfield = self.driver.find_element_by_id('edit-pass')
        passfield.send_keys(password)

        passfield.submit()
        WebDriverWait(self.driver, 10).until(
            EC.staleness_of(title)
        )

        return (username in self.driver.title)
