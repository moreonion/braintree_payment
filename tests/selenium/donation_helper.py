from enum import Enum
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

class DonationHelper:
    TIMEOUT = 15
    Action = Enum('Action', 'set_value click')

    def __init__(self, url, driver):
        """
        :param url: The base url of the donation wizard.
        :param driver: The webdriver instance.
        """
        self.url = url
        self.driver = driver

    def create_page(self):
        """
        Creates a new donation page.
        """
        driver = self.driver

        # Open donation page wizard
        driver.get(self.url)

        # Submit first wizard page
        element = WebDriverWait(driver, 10).until(
          EC.presence_of_element_located((By.ID, 'edit-next'))
        )
        element.submit()

        # Wait for second wizard page (form builder) to show up
        WebDriverWait(driver, self.TIMEOUT).until(
            EC.presence_of_element_located((By.ID, "form-builder-positions"))
        )

        # Click on payment methods edit icon
        driver.execute_script('$("a[href*=cid_4]")[1].click()')

        # Wait for payment methods form to show up
        WebDriverWait(driver, self.TIMEOUT).until(
            EC.presence_of_element_located((By.CSS_SELECTOR, "ul.form-builder-tabs.tabs.clearfix"))
        )

        # Click on "Options" tab
        cmd = "$('span[class=fieldset-title]:contains(\"Options\")').click()"
        driver.execute_script(cmd)

        # Wait for "Options" form to show up
        WebDriverWait(driver, self.TIMEOUT).until(
          EC.presence_of_element_located((By.ID, "form-builder-field-configure"))
        )

        # Find the label for our Test Card, get the corresponding checkbox
        # and click it
        driver.execute_script("$('#' + $('label.option.webform-label-processed.ilabel:contains(\"Test Card\")').attr('for')).click()")

        # Click on next
        driver.find_element_by_id('edit-next').click()

        element = WebDriverWait(driver, self.TIMEOUT).until(
          EC.presence_of_element_located((By.ID, 'confirmation-or-thank-you-checkbox'))
        )
        element = driver.find_element_by_id('edit-next')
        element.click()

        element = WebDriverWait(driver, self.TIMEOUT).until(
          EC.presence_of_element_located((By.ID, 'edit-thank-you-node-type--2'))
        )
        element.click()

        element = driver.find_element_by_id('edit-thank-you-node-node-form-title')
        element.send_keys('Thank you for testing')

        driver.find_element_by_id('edit-next').click()

        element = WebDriverWait(driver, self.TIMEOUT).until(
          EC.presence_of_element_located((By.CSS_SELECTOR, ".button-finish"))
        )
        element.click()

        WebDriverWait(driver, self.TIMEOUT).until(
          EC.staleness_of(element)
        )

        return ('Create' not in driver.title)

    def make_donation(self, page_url, recurrence_id, next_button, fields,
        wait_for_submission=True):
        """
        Makes a donation via a donation webform.
        :param page_url: The URL of the donation page.
        :param recurrence_id: The ID of the checkbox and the the array index as
        a tuple for the recurrence of the payment (e.g. (".picker-handle", 0)
        for a single payment which is the first checkbox in the form)
        :param next_button: The ID of the button that moves forward through
        the form (e.g. "edit-next")
        :param fields: A list of dictionaries, each dictionary corresponds with
        a step in the form.
        """
        driver = self.driver
        TIMEOUT = self.TIMEOUT

        driver.get(page_url)

        for fieldname in fields[0]:
          field = WebDriverWait(driver, TIMEOUT).until(
            EC.visibility_of_element_located((By.ID, fieldname))
          )
          field.clear()
          field.send_keys(fields[0][fieldname])

        self.driver.execute_script(
          '$("' + recurrence_id[0] + '")[' + str(recurrence_id[1]) + '].click()'
        )

        self.driver.find_element_by_id(next_button).click()
        WebDriverWait(self.driver, 2)

        for fieldname in fields[1]:
          # for fieldname in step:
          if type(fields[1][fieldname]) is dict:
            if 'action' in step[fieldname]:
              action = step[fieldname]['action']
            else:
              action = self.Action.set_value

            if 'selector' in step[fieldname].keys():
              selector = step[fieldname]['selector']
              value = step[fieldname]['value']
            else:
              selector = By.ID
              value = step[fieldname]
          else:
            action = self.Action.set_value
            selector = By.ID
            value = fields[1][fieldname]

          field = WebDriverWait(driver, TIMEOUT).until(
            EC.visibility_of_element_located(
              (selector, fieldname)
            )
          )
          field.clear()
          field.send_keys(value)

        driver.execute_script(
          '$("[id^=' + next_button + ']").click()'
        )
        WebDriverWait(self.driver, 2)

        for fieldname in fields[2]:
          # for fieldname in step:
          # if step[fieldname].has_key('action'):
          #   action = step[fieldname]['action']
          # else:
          #   action = Action.set_value

          # if step[fieldname].has_key('selector'):
          #   selector = step[fieldname]['selector']
          #   value = step[fieldname]['value']
          # else:
          #   selector = By.ID
          #   value = step[fieldname]
          selector = By.ID
          value = fields[2][fieldname]

          field = WebDriverWait(driver, TIMEOUT).until(
            EC.visibility_of_element_located(
              (selector, fieldname)
            )
          )
          try:
            field.clear()
            field.send_keys(value)
          except Exception as e:
            print('{}: {}'.format(fieldname, value))
            print(e)
            print('Trying again...')

            field = WebDriverWait(driver, TIMEOUT).until(
              EC.visibility_of_element_located(
                (selector, fieldname)
              )
            )
            field.clear()
            field.send_keys(value)

        month_box = "edit-submitted-paymethod-select-payment-method-all-forms-5-expiry-date-month"
        year_box = "edit-submitted-paymethod-select-payment-method-all-forms-5-expiry-date-year"

        xpath = "//select[@id='{}']/option[@value='{}']".format(
          month_box,
          '10'
        )

        field = WebDriverWait(driver, TIMEOUT).until(
          EC.visibility_of_element_located(
            (By.XPATH, xpath)
          )
        )
        field.click()

        xpath = "//select[@id='{}']/option[@value='{}']".format(
          year_box,
          '2019'
        )
        field = WebDriverWait(driver, TIMEOUT).until(
          EC.visibility_of_element_located(
            (By.XPATH, xpath)
          )
        )
        field.click()

        smfield = WebDriverWait(driver, self.TIMEOUT).until(
            EC.element_to_be_clickable((By. ID, 'edit-submit'))
        )
        smfield.click()
        WebDriverWait(self.driver, 2)

        try:
          WebDriverWait(driver, self.TIMEOUT).until(
            EC.title_contains('Thank you')
          )
          return True
        except Exception as e:
          print('Couldn\'t find "Thank you" in title')
          return False
