/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
define([
    'Klarna_Kco/js/action/select-shipping-method',
    'Klarna_Kco/js/action/refresh-addresses',
    'Klarna_Kco/js/action/update-kss-status',
    'Magento_Checkout/js/action/get-totals'
], function (
    kcoShippingMethod,
    kcoRefreshAddresses,
    updateKssStatus,
    getTotals
) {
    'use strict';

    return {
        /**
         * Triggering the events
         */
        attachEvents: function () {
            window._klarnaCheckout(function (api) {
                api.on({

                    /**
                     * Triggering the shipping_option_change event
                     * @param {Object} data
                     */
                    'shipping_option_change': function (data) {
                        // On initial load we don't have any shipping options. Therefore we need to skip the logic.
                        if (data.id === undefined) {
                            return;
                        }

                        // eslint-disable-next-line vars-on-top
                        var dataArray = data.id.split('_'),
                            method = {
                                // jscs:disable requireCamelCaseOrUpperCaseIdentifiers
                                carrier_code: dataArray[0],
                                method_code: dataArray.slice(1).join('_')
                            };

                        kcoShippingMethod(method);
                    },

                    /**
                     * Triggering the order_total_change event
                     */
                    'order_total_change': function () {
                        window.checkoutConfig.klarna.klarnaUpdateNeeded = false;
                        getTotals([]);
                    },

                    /**
                     * Triggering the billing_address_change event
                     */
                    'billing_address_change': function () {
                        kcoRefreshAddresses();
                        updateKssStatus();
                    },

                    /**
                     * Triggering the shipping_address_change event
                     */
                    'shipping_address_change': function () {
                        kcoRefreshAddresses();
                        updateKssStatus();
                    }
                });
            });
        }
    };
});
