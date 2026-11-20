/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
define([
    'mage/storage',
    'Magento_Ui/js/model/messageList',
    'Klarna_Kco/js/model/config',
    'Magento_Checkout/js/model/shipping-save-processor',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/action/get-totals',
    'Magento_Checkout/js/checkout-data',
    'jquery',
    'Klarna_Kco/js/model/iframe'
], function (
    storage,
    messageList,
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

    /**
     * KUSTOM-128: the server refused the change (payment in progress or order created).
     * Show the message and put the previously selected method back in the UI.
     *
     * @param {Object} response
     * @param {Object|string} previousMethod
     * @returns {Boolean}
     */
    function handleLocked(response, previousMethod) {
        if (!response || !response.locked) {
            return false;
        }

        if (response.message) {
            messageList.addErrorMessage({message: response.message});
        }

        if (previousMethod && previousMethod['carrier_code']) {
            selectShippingMethodAction(previousMethod);
            checkoutData.setSelectedShippingRate(
                previousMethod['carrier_code'] + '_' + previousMethod['method_code']
            );
        }

        return true;
    }

    /**
     * KUSTOM-128: an iframe event marks the request, the server then skips the 300 s window
     *
     * @param {String} url
     * @param {Boolean} fromIframe
     * @returns {String}
     */
    function withOrigin(url, fromIframe) {
        return fromIframe ? url + (url.indexOf('?') === -1 ? '?' : '&') + 'origin=iframe' : url;
    }

    /**
     * @param {Object} shippingMethod
     * @param {String} [origin] 'iframe' when the change comes from a Kustom iframe event
     * @returns {Boolean}
     */
    return function (shippingMethod, origin) {
        var changedShippingMethod = false,
            fromIframe = origin === 'iframe',
            previousMethod = oldShippingMethod,
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
        storage.post(withOrigin(config.updateKssStatusUrl, fromIframe))
            .done(function (ajaxResult) {
                getTotals([]);

                if (handleLocked(ajaxResult, previousMethod)) {
                    oldShippingMethod = previousMethod;
                    iframe.resume();

                    return;
                }

                if (changedShippingMethod && ajaxResult['changed_grand_total']) {
                    storage.post(withOrigin(config.updateKssDiscountOrderUrl, fromIframe))
                        .done(function (ajaxResultInner) {
                            iframe.resume();

                            if (handleLocked(ajaxResultInner, previousMethod)) {
                                oldShippingMethod = previousMethod;
                                getTotals([]);

                                return;
                            }

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
