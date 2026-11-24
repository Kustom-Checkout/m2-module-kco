/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
define([
    'mage/storage',
    'Klarna_Kco/js/model/config',
    'Magento_Checkout/js/model/shipping-save-processor',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/action/get-totals',
    'Magento_Checkout/js/checkout-data',
    'jquery',
    'Klarna_Kco/js/model/iframe'
], function (
    storage,
    config,
    shippingProcessor,
    selectShippingMethodAction,
    getTotals,
    checkoutData,
    $,
    iframe
) {
    'use strict';

    var oldShippingMethod = '';

    return function (shippingMethod) {
        var changedShippingMethod = false,
            data = {
                // jscs:disable requireCamelCaseOrUpperCaseIdentifiers
                shippingMethod: shippingMethod.carrier_code + '_' + shippingMethod.method_code
            };

        if (oldShippingMethod['carrier_code'] !== shippingMethod['carrier_code']) {
            changedShippingMethod = true;
        }
        oldShippingMethod = shippingMethod;

        // Updating the js quote.shippingMethod with the selected shipping method
        selectShippingMethodAction(shippingMethod);

        // Updating the persistence storage shipping method value
        checkoutData.setSelectedShippingRate(data.shippingMethod);

        if (!config.isKssEnabled) {
            window.checkoutConfig.klarna.klarnaUpdateNeeded = false;
            getTotals([]);

            return true;
        }

        iframe.suspend();
        storage.post(config.updateKssStatusUrl)
            .done(function (ajaxResult) {
                getTotals([]);

                if (changedShippingMethod && ajaxResult['changed_grand_total']) {
                    storage.post(config.updateKssDiscountOrderUrl)
                        .done(function (ajaxResultInner) {
                            iframe.resume();

                            if (ajaxResultInner['html_snippet']) {
                                // eslint-disable-next-line vars-on-top
                                var element = $('#klarna-checkout-container');

                                element.load(' #klarna-checkout-container > *');
                                element.replaceWith(ajaxResultInner['html_snippet']);
                            }
                        });
                } else {
                    iframe.resume();
                }
            });

        return true;
    };
});
