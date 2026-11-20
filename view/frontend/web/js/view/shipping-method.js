/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
define([
    'jquery',
    'underscore',
    'Magento_Checkout/js/view/shipping',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/action/set-shipping-information',
    'Magento_Checkout/js/checkout-data',
    'Klarna_Kco/js/model/config',
    'Klarna_Kco/js/model/shipping-method-resolver',
    'Klarna_Kco/js/action/select-shipping-method',
    'mage/storage',
    'Klarna_Kco/js/action/update-klarna-order',
    'Magento_Ui/js/model/messageList'
], function (
    $,
    _,
    Component,
    ko,
    quote,
    shippingService,
    selectShippingMethodAction,
    setShippingInformationAction,
    checkoutData,
    config,
    kcoShippingMethodResolver,
    kcoShippingMethod,
    storage,
    updateKlarnaOrder,
    messageList
) {
    'use strict';

    var updateInProgress = ko.observable(false);

    shippingService.getShippingRates().subscribe(function (rates) {
        if (rates.length > 0) {
            // eslint-disable-next-line vars-on-top
            var selectedShippingRate = checkoutData.getSelectedShippingRate();

            kcoShippingMethodResolver.resolveShippingMethod(rates, selectedShippingRate);
        }
    });

    return Component.extend({
        defaults: {
            template: 'Klarna_Kco/shipping-method'
        },
        visible: ko.observable(!config.frontEndShipping),
        updateInProgress: updateInProgress,

        /**
         * Listening to an event
         */
        setupListener: function () {
            $('#onepage-checkout-shipping-method-additional-load').on('change', 'input', function () {
                setShippingInformationAction();
            });
        },

        /**
         * Set shipping information handler
         */
        setShippingInformation: function () {
            if (this.validateShippingInformation()) {
                setShippingInformationAction();
            }
        },

        /**
         * This method is called when a shipping method is selected outside of the iframe
         *
         * @param {Object} shippingMethod
         * @return {Boolean}
         */
        selectShippingMethod: function (shippingMethod) {
            /**
             * This method is called more then 1 time. To avoid duplicated calculations, requests and other things
             * we're using the flag to know if there is currently a active process.
             */
            if (updateInProgress()) {
                return true;
            }

            var previousMethod = quote.shippingMethod();

            kcoShippingMethod(shippingMethod);
            updateInProgress(true);

            storage.post(config.methodUrl, JSON.stringify(shippingMethod)).done(function (response) {
                updateInProgress(false);

                if (response && response.locked) {
                    // KUSTOM-128: refused (payment in progress or order created): show the message and put the
                    // previously selected method back, the quote on the server was not changed
                    if (response.message) {
                        messageList.addErrorMessage({message: response.message});
                    }
                    if (previousMethod && previousMethod['carrier_code']) {
                        selectShippingMethodAction(previousMethod);
                        checkoutData.setSelectedShippingRate(
                            previousMethod['carrier_code'] + '_' + previousMethod['method_code']
                        );
                    }

                    return;
                }

                updateKlarnaOrder();
            });

            return true;
        }

    });
});
