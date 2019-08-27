[![Build Status](https://travis-ci.com/moreonion/braintree_payment.svg?branch=7.x-2.x)](https://travis-ci.com/moreonion/braintree_payment) [![codecov](https://codecov.io/gh/moreonion/braintree_payment/branch/7.x-2.x/graph/badge.svg)](https://codecov.io/gh/moreonion/braintree_payment)

# Braintree payment

This module implements a [payment](https://www.drupal.org/project/payment) method for [braintree](https://www.braintreepayments.com/) credit card payments.

## Features

* One-time payments via credit card.
* PCI SAQ-A compliance through use of hosted fields.
* 3DSv2 support.


# Requirements

* PHP 7.0+
* Drupal 7
* [libraries](https://www.drupal.org/project/libraries)
* [little_helpers](https://www.drupal.org/project/little_helpers) ≥ 2.0-alpha6
* [payment](https://www.drupal.org/project/payment) ≥ 1.10
* [payment_context](https://www.drupal.org/project/payment_context)
* [payment_controller_data](https://www.drupal.org/project/payment_controller_data)
* [payment_forms](https://www.drupal.org/project/payment_forms) ≥ 2.0-beta1
* [xautoload](https://www.drupal.org/project/xautoload) ≥ 5.0
* [braintree/braintree_php:3](https://packagist.org/packages/braintree/braintree_php)
* A payment_context aware payment context like [webform_paymethod_select](https://www.drupal.org/project/webform_paymethod_select).


# Usage

1. Copy the braintree_php libary to an apropriate library folder.
2. Install and enable the braintree_payment module (ie. `drush en braintree_payment`)
2. Create and configure a payment method in `admin/config/services/payment`.
