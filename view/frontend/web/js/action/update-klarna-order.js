/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
/*jshint browser:true jquery:true*/
define([
    'mage/storage',
    'Magento_Ui/js/model/messageList',
    'Klarna_Kco/js/model/iframe',
    'Klarna_Kco/js/model/config'
], function (
    storage,
    messageList,
    iframe,
    config
) {
    'use strict';

    return function () {
        iframe.suspend();
        storage.post(config.updateKlarnaOrderUrl).done(function (response) {
            if (response.locked) {
                // KUSTOM-128: the change was refused, nothing was changed on the server
                if (response.message) {
                    messageList.addErrorMessage({message: response.message});
                }
            }

            if (response.error) {
                /**
                 * We scroll the customer to the top of the page even the customer is on a lower part of the page to
                 * ensure that he/she definitely see it.
                 */
                window.scrollTo(0, 'smooth');
            }
            iframe.resume();
        });
    };
});
