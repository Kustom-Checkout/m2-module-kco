12.0.23 / 2026-04-22
==================

* KUSTOM-83 Fix Magento cancel status if klarna order is cancelled

12.0.22 / 2026-04-10
==================

* [KUSTOM-76] Fix Shipping amount does not match for order between Kustom and Magento
* [KUSTOM-58][KUSTOM-77][KUSTOM-82][KUSTOM-83][KUSTOM-84] Fix Orders instantly cancelled by api because of uncaught exceptions

12.0.21 / 2026-02-24
==================

* KUSTOM-54 Fix type error in B2b::isB2bCustomer

12.0.20 / 2026-02-19
==================

* KUSTOM-49 Fix error on logging address data into file

12.0.19 / 2026-01-16
==================

* KUSTOM-35 fix currency loading on order placement

12.0.16 / 2025-06-03
==================

  * PPP-2089 Updated version because of version dependency updates

12.0.15 / 2025-05-21
==================

  * PPP-2055 Compatibility with AC 2.4.8 and PHP 8.4

12.0.14 / 2025-04-23
==================

  * PPP-2060 Updated version because of new dependencies

12.0.13 / 2025-04-03
==================

  * PPP-1860 Simplified repository classes for database abstractions

12.0.12 / 2025-03-26
==================

  * PPP-1580 Added Plugins API functionality and hiding KCO for PSPs

12.0.11 / 2025-02-11
==================

  * PPP-1983 Increased version because of new dependencies

12.0.10 / 2025-01-22
==================

  * PPP-1954 Fix database connection pooling issue

12.0.9 / 2025-01-14
==================

  * PPP-1958 Increased version because of dependency version change

12.0.8 / 2024-12-03
==================

  * PPP-1878 Added check in the order creation step if the order was already created based on the quote ID

12.0.7 / 2024-11-05
==================

  * PPP-1856 Increased version because of the module version dependencies

12.0.6 / 2024-10-18
==================

  * PPP-1714 Simplify composer.json files

12.0.5 / 2024-09-26
==================

  * PPP-1521 Using the store instance to fetch the locale
  * PPP-1637 Readded the ability to enable and disable the file logging in the settings.

12.0.4 / 2024-08-21
==================

  * PPP-1014 Deprecated Klarna\Base\Helper\KlarnaConfig
  * PPP-1606 Refactor the Logger/Model/Logger class
  * PPP-1632 Added timestamps to the database table.

12.0.3 / 2024-08-12
==================

  * PPP-1604 Updated the version because of new versions of the dependencies

12.0.2 / 2024-07-26
==================

  * PPP-1553 Make the extension compatible with Adobe Commerce app assurance program requirements
  * PPP-1575 Improve KP performance by using a different approach to place order

12.0.1 / 2024-07-15
==================

  * PPP-1513 Added validation to check if on the Klarna side the same items are registered compared to the quote
  * PPP-1514 Optimized CSRF handling

12.0.0 / 2024-06-20
==================

* PPP-1437 Updated the admin UX and changed internally the API credentials handling

11.0.23 / 2024-07-03
==================

  * PPP-1551 Increased version because of new Klarna dependencies

11.0.22 / 2024-05-30
==================

  * PPP-1488 Fix isKpEnabled method in the KCO module

11.0.21 / 2024-04-24
==================

  * PPP-1391 Added support for Adobe Commerce 2.4.7 and PHP 8.3

11.0.20 / 2024-04-11
==================

  * PPP-1385 Increased version because of new Klarna dependencies

11.0.19 / 2024-03-30
==================

  * PPP-1013 Using instead of \Klarna\Base\Helper\ConfigHelper logic from other classes to get back Klarna specific configuration values.
  * PPP-1312 Adjusted call for sending the plugin version through the API header

11.0.18 / 2024-03-15
==================

  * PPP-1305 +Updated the coding style to fix the marketplace warnings.

11.0.17 / 2024-03-04
==================

  * PPP-916 Retrieve and add more debugging related data to the admin support request form.
  * PPP-1277 Removed the usage of the Zend\Uri\UriFactory class

11.0.15 / 2024-02-01
==================

  * PPP-1086 Fix broken KCO workflow if no order was yet created in the confirmation callback

11.0.14 / 2024-01-19
==================

  * PPP-748 Moved shipping method update logic from KCO to the Base module

11.0.13 / 2024-01-19
==================

  * PPP-1058 Increased version because of a dependency version change

11.0.12 / 2024-01-05
==================

  * PPP-960 Loading the latest active KCO quote from the databse based on the quote_id
  * PPP-1015 Moved the logic of Klarna\Base\Model\Config to new namespaces

11.0.11 / 2023-11-15
==================

  * PPP-929 Increased the version because of a new version of the Logger module

11.0.10 / 2023-09-27
==================

  * PPP-664 Fixed not triggered validations in the validation callback

11.0.9 / 2023-08-25
==================

  * PPP-59 Add m2-klarna package version to User-Agent
  * PPP-171 Fixed the case when changing the country the correct taxes are used
  * PPP-313 Fixed using a old quote when checking if Klarna Shipping Assistant is used

11.0.8 / 2023-08-01
==================

  * PPP-575 Increased the version because of new dependency versions in the composer.json file

11.0.7 / 2023-07-14
==================

  * MAGE-4141 Map Magento supported locales (BPC 47) with Klarna supported ones (RFC1766)
  * MAGE-4228 Removed the composer caret version range for Klarna dependencies

11.0.6 / 2023-05-24
==================

  * MAGE-4236 Increased the version because of new Klarna composer depencies

11.0.5 / 2023-05-22
==================

  * MAGE-3857 Adjusted the usage of the new place of the Klarna\Kco\Controller\Api\CsrfAbstract class

11.0.4 / 2023-04-21
==================

  * MAGE-4201 Not using on ajax calls ".success" and ".fail" anymore sinc does not exist anymore in Magento 2.4.6

11.0.3 / 2023-04-03
==================

  * MAGE-4164 Updated the version

11.0.2 / 2023-03-28
==================

  * MAGE-4162 Added support for PHP 8.2

11.0.1 / 2023-03-28
==================

  * MAGE-4150 Sanitizing and stripping the tags for the KCO failure url

11.0.0 / 2023-03-09
==================

* MAGE-76 Refactored Model Base/Model/Fpt and moved the logic to new locations and adjusted the calls.
* MAGE-3890 Fixed the HTTP type for the PaymentStatus action
* MAGE-4062 Removed deprecated methods
* MAGE-4063 #Removd deprecated classes
* MAGE-4064 Removed deprecated traits
* MAGE-4066 Removed the Objectmanager workaround for public API class contructors
* MAGE-4068 Do not using anymore in all controllers the parent Magento\Framework\App\Action\Action class
* MAGE-4074 Removed KSA logic in the shipping method update controller action
* MAGE-4075 Removed not needed events
* MAGE-4077 Added "declare(strict_types=1);" to all production class files
* MAGE-4078 Added abstract class to handle CSRF
* MAGE-4084 Indicating the payment code when fetching payment specific configurations from the Base module
* MAGE-4085 Removed the usage of \Klarna\Base\Model\Api\BuilderFactory
* MAGE-4086 Simplified logic when checkingif a sales rule with the rule "apply to shipping" is used
* MAGE-4087 Moved \Klarna\Base\Model\Api\Parameter to the orderline module and adjusted the calls
* MAGE-4089 Refactored \Klarna\Kco\Model\Checkout\Type\Kco

10.1.13 / 2023-01-05
==================

  * MAGE-4100 Removed the update of the attribute _isScopePrivate in the success block

10.1.12 / 2022-10-24
==================

  * MAGE-4061 Updated the dependencies

10.1.11 / 2022-10-20
==================

  * MAGE-4049 Using the correct configuration path when fetching the B2B flag

10.1.10 / 2022-09-27
==================

  * MAGE-3994 Removed the association between a guest and registered customer when placing the order
  * MAGE-3996 Simplified \Klarna\Kco\Model\Api\Rest\Service\Checkout by reducing redundant logic.
  * MAGE-4000 Not using the store value anymore when getting back the orderline instance classes
  * MAGE-4002 Removed the call to the class Klarna\Base\Model\Api\Validator.
  * MAGE-4008 Moved logic from \Klarna\Kco\Model\Checkout\Type\Kco::isValidKcoConfiguration() to \Klarna\Kco\Model\Checkout\Configuration\ApiValidation
  * MAGE-4009 Refactored Klarna\Kco\Model\Checkout\Address by moving the logic to new classes
  * MAGE-4010 Optimized the class \Klarna\Kco\Model\Payment\Kco
  * MAGE-4011 Removed the methods isExpired and redirectAjaxRequest from the class \Klarna\Kco\Model\Responder\Ajax
  * MAGE-4015 Not showing the company logo for B2B orders

10.1.9 / 2022-09-14
==================

  * MAGE-1636 Added setting for indicating the a list of allowed billing countries
  * MAGE-2981 Handling DHL settings also when creating the update request
  * MAGE-3987 Refactored the update of the selected shipping method for KCO what improves the performance in this respective workflow.

10.1.8 / 2022-09-01
==================

  * MAGE-3434 Improved the execution checks in the plugins
  * MAGE-3621 Improved the software design and performance of the quote address update
  * MAGE-3712 Using constancts instead of magic numbers

10.1.7 / 2022-08-18
==================

  * MAGE-3961 Updated the dependencies

10.1.6 / 2022-08-12
==================

  * MAGE-3640 Add "Klarna" prefix on the invoice
  * MAGE-3838 Changed the position of the menu item on the admin payment page
  * MAGE-3876 Reordered translations and set of missing translations
  * MAGE-3910 Updated the copyright text
  * MAGE-3920 Add orderline processor integration test
  * MAGE-3923 Remove not needed composer.json entries

10.1.5 / 2022-07-11
==================

  * MAGE-3888 Removed object creations via "new ..."
  * MAGE-3620 Using the new location of the ITEM_TYPE_SHIPPING constant location
  * MAGE-3886 Removed legacy code regarding the fetching of shipping rates

10.1.4 / 2022-06-23
==================

  * MAGE-488 Throwing exception when trying to load a Klarna quote with the Klarna order id and it could not be found
  * MAGE-555 Created unit tests for Model\ResourceModel\Quote
  * MAGE-870 Created unit tests for Plugin\Helper\KlarnaConfigPlugin, Plugin\AddPaymentStatusButton and Plugin\CheckoutHelperPlugin
  * MAGE-3386 Showing the real error message when the Klarna update request fails on actions on the checkout page
  * MAGE-3726 Add logging entries to the order history table from the confirmation and push callback after the order was created
  * MAGE-3728 Handling the exception when no KSA entry was found in the database
  * MAGE-3866 Saving the used mid in the table klarna_core_order when creating the entry

10.1.3 / 2022-06-13
===================

  * MAGE-3785 Fix PHP requirements so that it matches the PHP requirement from Magento 2.4.4
  * MAGE-3332 Removed the dependency to ramsey/uuid
  * MAGE-3841 Centralized the onboarding link url text in the Base module

10.1.2 / 2022-05-31
===================

  * MAGE-3855 Bump version because of updated dependencies

10.1.1 / 2022-05-09
===================

  * MAGE-3694 Add integration test
  * MAGE-3599 Moved settings to the admin payment page
  * MAGE-563 Refactor QuoteRepository
  * MAGE-3720 Add minicart reload on the success page

10.1.0 / 2022-03-01
==================

  * Move from klarna/m2-marketplace

9.3.1 / 2021-10-25
==================

  * MAGE-2734 Add logging when KSA is enabled on the API but disabled on the shop
  * MAGE-2856 fixed newsletter signup checkbox
  * MAGE-3272 Showing the error message in the validation and confirmation action
  * MAGE-3304 Removed not needed KSA logic

9.3.0 / 2021-09-07
==================

  * MAGE-2956 KSA: Fixed discount applied on shipping usage for the new KSA logic
  * MAGE-3087 KSA: Use Klarna's version of order instead of internal one
  
9.2.2 / 2021-08-02
==================

  * MAGE-2822 Fix PHP argument error when an invalid Klarna order id is used for updating the order status
  * MAGE-3133 Fix issue when a customer is on a external page and the cart changed while the customer is there

9.2.1 / 2021-04-08
==================

  * MAGE-2924 Fix not logged failed requests for Logs+
  * MAGE-2982 Fix handling error message on a Klarna api 404 error

9.2.0 / 2021-03-09
==================

  * MAGE-2147 Move observer logic to main classes
  * MAGE-2342 Support for non-US merchants using shop setting "excluding tax" for catalog prices and shipping fees
  * MAGE-2727 Add support for Logs++
  * MAGE-2852 Fix issue with low inventory configurable products breaking orders
  * MAGE-2916 Fix different shipping reference and name between the order creation and ordermanagement requests

9.1.8 / 2021-02-10
==================

  * MAGE-2922 Fix issue with row total calculation for KSA

9.1.7 / 2021-02-08
==================

  * MAGE-2915 Fix issue with 9.1.6 release

9.1.6 / 2021-02-08
==================

  * MAGE-2583 Fix Not existing cart usage
  * MAGE-2609 Fix post order update of Klarna order fails with 403
  * MAGE-2689 Fix error logging of failed acknowledge call
  * MAGE-2850 Fix notification callback URL

9.1.5 / 2020-12-22
==================

  * MAGE-2756 Readded logic from 8.x to update the Magento quote

9.1.4 / 2020-12-17
==================

  * MAGE-2706 Fix issue with shipping and canceled orders

9.1.3 / 2020-11-23
==================

  * MAGE-2483 Remove bad translation
  * MAGE-2512 Fix issue with using store codes in URL and using external payment methods
  * MAGE-2548 Add plugin for fixing the "email is required" issue when applying a coupon
  * MAGE-2660 Fix issue with canceling orders with Klarna when order doesn't exist in Magento

9.1.2 / 2020-08-28
==================

  * MAGE-2329 Fix issue with orderline for giftcards having the wrong name

9.1.1 / 2020-08-26
==================

  * MAGE-2293 Code cleanup of Model/Checkout/Type/Kco to remove "else"
  * MAGE-2341 Fix composer v2 warnings
  * MAGE-2403 Add special handling for iDEAL logic back

9.1.0 / 2020-08-12
==================

  * MAGE-551 Improve error message when both KCO and KP are enabled
  * MAGE-1460 Add support for Digital Products when using KSS
  * MAGE-1988 Reduce API calls required when using KSS
  * MAGE-2055 Add support for PHP 7.4
  * MAGE-2106 Move MFTF tests to a new module
  * MAGE-2248 Change updateInProgress JS variable to be a observable
  * MAGE-2255 Add support for Magento 2.4

9.0.2 / 2020-06-04
==================

  * MAGE-2018 Update CSRF usage in the frontend GET controller actions
  * MAGE-2058 Add set of MFTF tests for bundled products
  * MAGE-2062 Add set of MFTF tests for grouped products
  * MAGE-2064 Add set of MFTF tests for downloadable products
  * MAGE-2065 Add set of MFTF tests for registered customers
  * MAGE-2068 Fix set-payment-information javascript callback error
  * MAGE-2072 Add set of MFTF order management tests
  * MAGE-2078 Add set of MFTF virtual product tests
  * MAGE-2099 Fix MFTF iframe interaction
  * MAGE-2124 Update admin Klarna labels for the payment configuration

9.0.1 / 2020-05-14
==================

  * MAGE-2084 Fix issue with applied coupon codes

9.0.0 / 2020-04-23
==================

  * Performance and usability loading improvements for the checkout page
  * Added MFTF tests and suites
  * MAGE-978 Remove abstract controller class Klarna\Kco\Controller\Klarna\Action
  * MAGE-979 Remove abstract controller class Klarna\Kco\Controller\Api\BaseAction
  * MAGE-980 Remove abstract controller class Klarna\Kco\Controller\Api\Action
  * MAGE-981 Add class for generic controller action handling
  * MAGE-1447 Defined sensitive and environment specific fields
  * MAGE-1452 Fix issue causing free orders on KCOv3 to default to "Pending"
  * MAGE-1517 Avoid the recollecting of the totals by mainly calling the collectTotals() inside the quote save operation
  * MAGE-1594 Prevent issue with products being disabled while customer is on checkout page
  * MAGE-1654 Fix issue with shipping addresses when using KSS
  * MAGE-1655 Product discount is listed on each order line instead of being a separate order line
  * MAGE-1716 Add the error message popup has now a more descriptive information when an error occurred in the Klarna address update callback
  * MAGE-1728 Remove KCO V2 logic
  * MAGE-1742 Fix issue that no error message was shown when an error happened while updating the Klarna order via ajax
  * MAGE-1762 Remove the text "v3" in the admin api endpoint configuration
  * MAGE-1822 Remove class Klarna\Kco\Model\Provider\Base\Address
  * MAGE-1823 Remove class Klarna\Kco\Observer\SetShippingInIframeUrl
  * MAGE-1826 Remove Klarna Shipping Service quote update in the reload summary action
  * MAGE-1827 Change name of the refresh addresses controller action and adjusted the paths to it
  * MAGE-1829 Move the KCO observer logic from the Backend module to the KCO module
  * MAGE-1830 Remove in the confirmation action workflow the quote update and the call of the collectTotals() method on it
  * MAGE-1834 Change name of the reload summary controller action and adjusted the paths to it
  * MAGE-1836 Remove dead and unused public methods in different classes
  * MAGE-1852 Rename the kco.js to events.js and klarna.js to iframe.js
  * MAGE-1853 Add http type controller interface to the controllers
  * MAGE-1858 Move most of the Klarna Shipping Service logic from KCO to its own module (KSS)
  * MAGE-1862 Fix push action functionality in admin order page was not shown and did not worked
  * MAGE-1863 Remove the validation in the confirmation action workflow
  * MAGE-1865 Add validation of the response in the address and shipping method update
  * MAGE-1872 Fix infinite loop when using Klarna Payments
  * MAGE-1888 Fix Minicart quantity counter on success page
  * MAGE-1893 Add KSS admin setting flag
  * MAGE-1917 Fix coupon applied to shipping functionality when using Klarna Shipping Service
  * MAGE-1966 Fix wrong selected shipping method when changing country
  * MAGE-1993 Fix exception logging issue when using the Klarna production environment

8.1.3 / 2020-04-17
==================

  * MAGE-1661 Fix wrong selected shipping method when changing country
  * MAGE-1774 Fix Minicart quantity counter on success page
  * MAGE-1851 Fix coupon applied to shipping functionality when using Klarna Shipping Service
  * MAGE-1861 Fix push action functionality in admin order page was not shown and did not worked
  * MAGE-1909 Fix missing shipping address issue
  * MAGE-1995 Update system.xml to work with 2.3.5 changes

8.1.2 / 2020-03-09
==================

  * MAGE-1777 Removed MFTF suite due to issues running the tests in all environments
  * MAGE-1859 Fix infinite loop when placing the order on the native checkout

8.1.1 / 2020-02-07
==================

  * MAGE-1447 Defined sensitive and environment specific fields
  * MAGE-1766 Fix issue with Magento 2.3.4

8.1.0 / 2020-02-04
==================

  * MAGE-1452 Fix issue causing free orders on KCOv3 to default to "Pending"
  * MAGE-1594 Prevent issue with products being disabled while customer is on checkout page
  * MAGE-1607 Wrong link to merchant portal from Magento admin
  * MAGE-1654 Fix issue with shipping addresses when using KSS

8.0.0 / 2019-11-18
==================

  * Update module to handle renamed core and ordermanagement modules
  * MAGE-867 Only clean up shipping address when shipping_address index is created
  * MAGE-1220 Fix issue with shipping discounts
  * MAGE-1232 Fix issue with shipping discounts when using KSS
  * MAGE-1324 Fix issue with cleaning up empty shipping addresses
  * MAGE-1357 Fix issue with custom options and skus
  * MAGE-1456 Fix issue with no content instead of empty content for raw response
  * MAGE-1520 Enable PHP 7.3 support
  * MAGE-1531 Fix new Magento Coding Standards changes

7.3.0 / 2019-10-03
==================

  * MAGE-588 Clarified comment for setting that allows guest to reach KCO
  * MAGE-791 Fix issue with coupons that affect shipping
  * MAGE-796 Save and show company name in customer address
  * MAGE-796 Save company ID to customer when custom attribute exists
  * MAGE-1156 Fix issue with "Undefined class constant ERROR_MESSAGES_KEY"
  * MAGE-1211 Improve workflow for customer who don't return to the merchant from a bank gateway

7.2.1 / 2019-06-28
==================

  * MAGE-576 Remove "title" configuration option as it is unused
  * MAGE-740 Fix missing return in view/frontend/web/js/action/get-totals.js
  * MAGE-789 Solved issues with dynamic shipping options, eg. table rate

7.2.0 / 2019-06-19
==================

  * MAGE-270 Add additional admin validation checks
  * MAGE-272 Add support for Klarna Shipping Service
  * MAGE-504 Fix issue with Packstation on KCOv2 DACH API
  * MAGE-692 Completed translations for all phrases. Covering da_DK, de_AT, de_DE, fi_FI, nl_NL, nb_NO and sv_SE.
  * MAGE-827 Fix redirect to cart when placing order issue using KCOv2

7.1.0 / 2019-04-30
==================

  * MAGE-471 Fix issue with shipping methods not updating
  * MAGE-482 Fix issue with region name not being saved with addresses
  * MAGE-487 Add da_DK translations
  * MAGE-487 Add de_DE translations
  * MAGE-487 Add fi_FI translations
  * MAGE-487 Add nb_NO translations
  * MAGE-487 Add nl_NL translations
  * MAGE-487 Add sv_SE translations

7.0.1 / 2019-03-26
==================

  * MAGE-277 Hid all Klarna settings on the store view level
  * MAGE-312 Add missing translations to en_US base
  * MAGE-318 Show specific error message if both KCO and KP are enabled
  * MAGE-552 Fixed wrong attribute usage for B2B customers

7.0.0 / 2019-02-22
==================

  * MAGE-232 Fix wrong KCO enabled check
  * MAGE-327 Remove the check for collecting the totals because it doesn't work in all cases
  * MAGE-375 Check if the order is expired and when it is create a new order
  * MAGE-403 Fix issue that sometimes displays the confirmation widget instead of checkout
  * MAGE-405 Fix issue with company name being set
  * MAGE-408 Fix issue with correct checkbox being shown

7.0.0-alpha / 2019-02-05
========================

  * MAGE-98 Implement new 2.3.x interfaces for webhooks
  * MAGE-103 Refactor Logging
  * MAGE-105 Refactor abstract class Model\Api\Builder
  * MAGE-168 Fix logging exception
  * MAGE-223 MFTF test for KCO
  * MAGE-232 Improve validation notices in Magento admin
  * MAGE-251 Switch to Marketplace coding standards
  * PI-472 Add more logging to validate callback
  * PI-491 Fix issue with shipping Rate in iframe not matching Magento Order summary
  * PPI-512 Save postcode & region to local storage
  * PPI-531 Refactor Helper class Address
  * PPI-532 Refactor Helper class CartHelper
  * PPI-533 Refactor Helper class ApiHelper
  * PPI-534 Refactor Helper class Responder
  * PPI-535 Refactor Helper class Shipping
  * PPI-536 Refactor Helper class Checkout
  * PPI-538 Refactor Type/Kco object - Address
  * PPI-539 Refactor Type/Kco object - Shipping
  * PPI-540 Refactor Type/Kco object - Checkbox
  * PPI-541 Refactor Type/Kco object - Session
  * PPI-542 Refactor Type/Kco object - Action
  * PPI-572 Remove reference of "isTotalCollector"
  * PPI-618 Refactor Model/Api/Kasper.php

6.3.1 / 2018-12-05
==================

  * MAGE-45 Ensure JS code doesn't run if KCO is disabled
  * MAGE-123 Change NIN and phone to be required by default
  * MAGE-125 Fixed wrong method name call.
  * PPI-593 Single place to enable/disable Klarna Checkout

6.3.0 / 2018-11-01
==================

  * PI-536 Fix css issue as close button covers shipping option
  * PI-509 Add organisation information to the quote
  * PPI-474 Change code to check store config

6.2.0 / 2018-10-17
==================

  * PI-355 Fix can not choose the payment options in KCO
  * PI-396 Add support for enabling National identification number as mandatory
  * PI-465 Fix error with log function parameter misplaced
  * PI-496 Add logging the content of "extra"
  * PI-507 Remove merchant portal link in confirmation email
  * PI-507 Stop sending Magento default confirmation email while KCO enabled
  * PPI-420 Add display of payment method to admin order view
  * PPI-467 Add better logging on Ajax failures
  * PPI-498 Removed onboarding span in admin order view
  * PPI-500 Add support for PHP 7.2
  * PPI-500 Remove unneeded constants that break 2.3
  * PPI-505 Update shipping template
  * PPI-533 Removed the usage of the _* method from the success block - using now an event for it.
  * PPI-536 Refactor LayoutProcessorPlugin to reduce coupling
  * Replace parseurl with zend http parse

6.1.2 / 2018-09-24
==================

  * Add better error messaging

6.1.1 / 2018-09-21
==================

  * Fix message unauthorized for Magento Marketplace QA team

6.1.0 / 2018-08-16
==================

  * PPI-402 Add support for "validate" and "save" actions on checkboxes

6.0.1 / 2018-08-15
==================

  * PI-426 Fix for incorrect shipping value showing

6.0.0 / 2018-08-14
==================

  * Refactor code to use renamed shared modules
  * PI-198 Fixes for Gift Wrapping
  * PI-254 Fix order with discount fails
  * PI-287 Fix "Cannot complete order" with PayPal
  * PI-422 Fix error message being displayed when no address was entered
  * PPI-317 Add support for Fixed Product Tax
  * PPI-402 Add support for multiple additional checkboxes
  * PPI-403 Using the onboarding model.
  * PPI-419 Move functionality from DACH module
  * PPI-449 Feedback from Magento for 2.2.6 release

5.0.5 / 2018-05-25
==================

  * PPI-394 Fix missing imports

5.0.4 / 2018-05-24
==================

  * PPI-394 Remove CommonController trait

5.0.3 / 2018-05-18
==================

  * PPI-413 Remove isConfigFlag method in favor of direct calling ConfigHelper
  * PI-306 Fix translation support for external methods
  * PPI-349 Add cancellation_terms URL (for DE/AT)
  * PI-198 Fix issue with tax on gift wrap applied on order level
  * PPI-390 Change post check to return 404 instead of exception
  * PI-286 Fix auto_focus setting is not visible in admin
  * PPI-394 Fix minor master branch issues
  * PPI-389 Fix handling for empty billing address
  * PPI-395 Fix no shipping methods available for entered address error with virtual quote
  * PPI-392 Fix tax not recalculating when address changed
  * PPI-375 Fix call to a member function getLoggedInGroups() on null

5.0.2 / 2018-04-20
==================

  * Fix after plugins on 2.1
  * Fix issue related to core module updates

5.0.1 / 2018-04-12
==================

  * Add support for gift wrapping
  * Bundled Extensions Program updates
  * Add unit testing support
  * Move customer prefil notice stuff into KCO module from DACH module
  * Allow telephone to be set as optional

4.3.8 / 2018-04-10
==================

  * Fix di:compile issues

4.3.7 / 2018-04-09
==================

  * Fix error when no shipping rates available and instead display message to customer

4.3.6 / 2018-04-05
==================

  * PI-236 Fix issue with region lookups when billing and shipping country are different
  * PI-225 Fix issue with 'Carrier with such method not found' message

4.3.5 / 2018-03-27
==================

  * Fix PI-239 redirect to cart
  * Fix shipping switch order total

4.3.4 / 2018-03-22
==================

  * Fix carrier with such method not found issue
  * Add ability to override saving of quote during validate callback

4.3.3 / 2018-03-08
==================

  * Remove saving of quote to fix FK errors

4.3.2 / 2018-02-01
==================

  * Fix for when shipping method code contains multiple underscores

4.3.1 / 2018-01-24
==================

  * Refactor ApiHelper class
  * Add B2B Support
  * Move base admin config stuff to to core module
  * Change API version labels to specify Klarna Checkout

4.2.0 / 2017-12-20
==================

  * Allow to disable giftcards, storecredit, and rewards
  * Pass error message to validateFailed controller
  * Remove 'Klarna Checkout' title from success page
  * Update success page to show more of native Magento portion
  * Fix XML validation issue due to use of 'unset'

4.1.2 / 2017-11-15
==================

  * Add listener for any input changes in shipping additional block
  * Revert "Fix XML validation issue"

4.1.1 / 2017-11-14
==================

  * Fix XML validation issue

4.1.0 / 2017-11-13
==================

  * Add RefreshAddresses controller to update billing and shipping addresses in checkout
  * Fix issue with billing address saved as shipping address
  * Fix issue with shipping country different than billing country
  * Change labels for API versions
  * Fix for shipping in iframe not updating correctly
  * Add support for disabling shipping in iframe in markets that support it
  * Move payment configuration section into 'Recommended' section
  * Add additional shipping block to checkout sidebar below shipping methods

4.0.0 / 2017-10-30
==================

  * Remove json wrapping as it is now handled in Service class
  * Update to 3.0 of klarna/module-kco-core
  * Fix for if KCO is disabled not messing up regular checkout layout on EE

3.0.0 / 2017-10-04
==================

  * Move Enterprise support into core module instead of having an add-on

2.3.3 / 2017-10-04
==================

  * Fix check of shipping address different than billing

2.3.2 / 2017-09-28
==================

  * Remove dependencies that are handled by klarna/module-kco-core module

2.3.1 / 2017-09-18
==================

  * Exclude tests as well as Tests from composer package

2.3.0 / 2017-09-11
==================

  * Refactor code to non-standard directory structure to make Magento Marketplace happy 😢
  * Fix CSS for hiding shipping method from summary area
  * Update code with fixes from MEQP2

2.2.6 / 2017-08-25
==================

  * Fix issue with customer address failing validation during place order
  * Fix to handle for shipping method not being set. Also better array conversion

2.2.5 / 2017-08-24
==================

  * Add try/catch to handle for KcoConfigProvider being called on cart page

2.2.4 / 2017-08-22
==================

  * Refactor to not cancel orders when getting redirect URL fails

2.2.3 / 2017-08-22
==================

  * Remove require-dev section as it is handled by core module

2.2.2 / 2017-08-14
==================

  * Fix nordics/dach check

2.2.1 / 2017-08-10
==================

  * Add support for care_of -> company

2.2.0 / 2017-08-10
==================

  * Reduce the number of quote saves that occur during checkout
  * Save quote using resource model instead of repository
  * Change validate to include a message when redirecting to validateFailed
  * Change to use placeOrder instead of submit. Also removed unneeded code
  * Move dispatch of success event to success controller to avoid any errors from blocking order creation

2.1.3 / 2017-08-09
==================

  * Fix street_address2 handling
  * Add support for house_extension

2.1.2 / 2017-08-08
==================

  * Move canceling of order to observer
  * Hide shipping rate description from side bar

2.1.1 / 2017-08-08
==================

  * If confirmation failed but order was created, cancel order

2.1.0 / 2017-08-04
==================

  * Return response with error message instead of throwing exception
  * Send 302 instead of 301 to avoid caching
  * Add failure_url setting to allow redirecting to somewhere other than the cart

2.0.6 / 2017-07-10
==================

  * Fix error logging

2.0.5 / 2017-07-07
==================

  * Remove duplicate reference to jsonHelper
  * Log exception to klarna logs before throwing it

2.0.4 / 2017-07-05
==================

  * Remove 'google' iframe as it was debugging code

2.0.3 / 2017-06-27
==================

  * Update name from Klarna AB to Klarna Bank AB (publ)

2.0.2 / 2017-06-05
==================

  * PPI-303 Fix missing GA code on success page
  * Add more logging to exception handler

2.0.1 / 2017-05-15
==================

  * Remove duplicate config setting

2.0.0 / 2017-05-01
==================

  * Add support for new DACH version
  * Set gender and DOB on customer when creating them via merchant checkbox
  * Remove 'Payment from...' admin settings to resolve PPI-77
  * Move initialize command to KCO module and fix transactionId setting
  * Add support for setting gender on customer
  * Disable editing order to resolve PPI-202
  * Add index to klarna_checkout_id field
  * Remove check on merchant_prefill and have this done in each builder instead
  * Add reporting proper error when a 401 is encountered
  * Fix tests directory in composer.json
  * Update license header
  * Refactor klarna.xml to use options inside api_version
  * Add method_code to event data
  * Add image URL to product item in API call
  * Add more descriptive error message to validation failure
  * Move validateTotal method to CommonController trait in core
  * Refactor to use promise instead of jQuery deferred
  * Handle for EE modules that try to disable module
  * Move Version class to Core module
  * Move credential configs to core module
  * Add dispatch of kco only event
  * Add Magento Edition to version string
  * Update dependency requirements to 2.0
  * Change code to pull composer package version for UserAgent
  * Update constructor to set prefix to kco for use in events
  * Change event to use klarna prefix instead of kco
  * Update copyright years
  * Move orderline classes to Core module
  * Add type cast to int to resolve strict comparison issues
  * Change getActiveByQuote to not save newly created quotes
  * Add missing getId method to interface
  * Fix order of exception handling
  * Remove unused controller
  * Move CommonController triat to core as it is used by multiple modules
  * Relocate quote to kco module
  * Move payment info block to core module
  * Fix PPI-150 by moving when events fire and including order in event
  * Fix shipping_title to be string instead of phrase object
  * Add CHANGELOG.md
  * Update provide version of virtual package
  * Add call to set user-agent.  Bump required version of core

1.1.3 / 2017-01-13
==================

  * Change StoreInterface to StoreManagerInterface in constructor to solve for 2.1.3 issues
  * Update constructor for ApiHelper due to parent class changes
  * Fix tests directory name in gitattributes file

1.1.2 / 2016-12-23
==================

  * Add gitattributes file to exclude items from composer packages
  * Change success page to say 'thank you' instead of 'klarna success' per feedback from Johannes C
  * Add border radius to design section

1.1.1 / 2016-11-11
==================

  * Use correct interface for BC support of M 2.0

1.1.0 / 2016-11-11
==================

  * Set preference for QuoteInterface
  * Rename region for use with DACH module
  * Remove dependency on monolog as not needed since we have psr/log
  * Support for partial capture/refund with discount for Kasper and blocking for Kred
  * Initial porting of partial payment stuff from M1 module

1.0.0-rc3 / 2016-10-29
======================

  * Redirect to 404 if KCO not enabled/allowed

1.0.0-rc2 / 2016-10-27
======================

  * Move shipping methods to sidebar per PPI-98
  * Change suggest value to a description per spec

1.0.0-rc1 / 2016-10-26
======================

  * Fix PPI-116 from using store zip
  * Add getTotals wrapper to suspend/resume iframe
  * Add translation stuff
  * Fix for PPI-83 display totals in sidebar
  * Fix for PPI-103 for dealing with downloadable products
  * Add call to getTotals to reloadContainer
  * Remove loader
  * Change jquery to use dollar sign
  * Add call to update Magento with address info
  * Move selectShippingMethod call into action JS
  * Move location of shipping methods
  * Refactor messages into own JS file
  * Fix reload summary to trigger Klarna update
  * Add country lookup controller
  * Fix posting of address
  * Potential fix for PPI-77
  * Allow multi-selects to be 'empty'
  * Add shipping method selection above iframe if shipping-in-iframe is disabled
  * Fix external payments only working if enabled at default level for PPI-75
  * Fix for PPI-75 external payments
  * Add support for allow/deny guest checkout independent of Magento setting
  * Add guest user to group list
  * Fix missing method call for PPI-68
  * Fix multiselect options.  Also fixes PPI-69
  * Change how logged in check occurs
  * Refactor common code into a Trait for controllers
  * Fix issue with customer being logged out in backend
  * Fix for customer not exists during merchant checkbox create account validation observer
  * Fix for prepopulating addresses
  * Fix for logged in customer checkout
  * Remove static references to ObjectManager
  * Remove check for email belonging to registered user to avoid errors when checking out as guest
  * Fix module name reference
  * Get config for store instead of default
  * Update validate method to make more sense.  Should also fix PPI-58
  * Fix for entity not set error on place order
  * Change handling of customer in session/quote partial fix of PPI-58
  * Change comparison to allow for difference in data type
  * Fix for PPI-61 and simplified fix for PPI-59
  * Fix for PPI-59 (issue with AssociateGuestOrderWithRegisteredCustomer observer)
  * Update translations
  * Add English translations file
  * Allow cancel of payment
  * Override getOptions call to add in Kasper specific options
  * Update user agent
  * Fix tax calculation in Magento sidebar as well as random address overwrite bug
  * Fix retrieveAddress AJAX call
  * Fix duplicate shipping rates
  * Fix shipping rate calculations
  * Fix merchant_reference numbers
  * Refactor to use traits
  * Refactor builders to cleanup code and remove duplication
  * Add BuilderFactory class to replace usage of ObjectManager
  * Heavy refactor of Helper classes
  * Default push and notification URLs to disabled but allow override via event
  * Move notification controller to OM
  * Move push controller to OM
  * Add check for missing country_id back
  * Fix location of referenced CONST
  * Fix for discount showing in order line for PPI-32
  * Change to ensure store is passed to default country lookup
  * Change OM to be loaded via DI instead of using ObjectManager
  * Fix issue with duplicate shipping methods
  * Fix comparison of 'unselected' state
  * Add messages block to checkout
  * Change how order lines are represented
  * Change to klarna.xml structure
  * Remove DI reference as no longer relevant
  * Updates for Kred support
  * Fix address lookup
  * Throw exception if problem loading checkout.  Should only show when in developer mode
  * Fix for module running in multiple stores with different API endpoints
  * Remove reference to type variable
  * Fix for class that was migrated and refactored in core module
  * Fix year
  * Add virtual package to provide list
  * Move om module from require to suggest
  * More migration of classes from kco to om module
  * Additional refacoring to move classes to OM and Core modules
  * Refactor base service class into separate module and remove OM
  * Fix XML for Magento 2.0.x
  * Fix for EventManager in Magento 2.0.x
  * Fix error message to use correct syntax for variable substitution
  * Create klarnacheckout template and change checkout pages to use it
  * Refactor code to work better with Magento 2
  * Change from use statement to fully qualified name to avoid name collision
  * Remove isDefault method call as does not exist in Magento 2.0
  * Fix missing import
  * Refactor payment method to work with both Magento 2.0 and Magento 2.1
  * Fix to work on correct array
  * Refactor to implement code instead of extending core class since class is moved between 2.0 and 2.1
  * Refactor so that order confirmation email works
  * Clear cart on success page
  * Fix 404 issue as class had wrong name internally
  * Add discount to item
  * Refactor to use CONST for version in API URLs
  * Fix error in notification callback controller
  * Fix capture payment
  * Refactor API logging
  * Handle for empty address a little better
  * Fix fetch transaction info call
  * First pass at refactoring due to ECG code sniffs
  * Add ECG coding standards to composer.json
  * Cleanup payment capture functionality
  * Fix observers
  * Refactor how merchant checkboxes are handled
  * Refactor payment method into Command objects
  * Refactor success controller to match closer to Onepage success controller
  * Fix iframe totals not calculating correctly
  * Fix checkout to update summary with shipping info
  * Minor bug fixes and cleanup
  * Rewrite JS to use requirejs and knockoutjs
  * Remove duplicate title
  * Fix shipping method update
  * Fix 'empty cart' issue by looking up quote in API controllers instead of taking from checkoutSession
  * Refactor API controllers as access to checkout helper is needed in all of them
  * Add error message if klarna checkout can't load
  * Payment info block in order view
  * Add Success page
  * Move interfaces to Api namespace
  * Fix quote table name for FK reference
  * Fix dom processor to handle for copyright notice in XML comment
  * Fix missing copyright notices
  * Initial working checkout
  * Order Management API
  * Refactoring to use guzzel client
  * Inject klarna config into Orderline collector
  * Add injection of module verison number into user-agent
  * Add guzzle based rest client
  * Remove old rest client classes
  * Add converted backend classes
  * Update existing classes
  * Add custom logger
  * Add all events/observers
  * Add all controllers
  * Update routes file
  * Move sales_quote_save_before event to global scope
  * Move event.xml to frontend area
  * Update module dependencies
  * Rename Exception class
  * Update Klarna Checkout controller
  * System config sections
  * Update class names in klarna.xml file
  * Working klarna.xml config stuff
  * Fix klarna config file loading
  * Add klarna_check_if_quote_has_changed Observer
  * Add generic Exception object
  * Models for order & quote tables
  * Inject klarna config into helpers
  * Converted helper
  * Fix issue with empty cart detection
  * Converted Checkout helper from M1 module
  * Add checkout.js to Klarna Checkout page
  * Create tables
  * Update source/backend models and enable payments section
  * Add converted config source models from M1
  * Add setup for reading klarna.xml custom config file
  * Add observer to force redirect to /checkout/klarna
  * Move Klarna checkout to it's own URL allowing for A/B testing
  * Add back methods required for KO to work
  * Initial add of iframe to checkout
  * Initial Commit
