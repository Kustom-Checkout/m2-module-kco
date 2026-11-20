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
    'Klarna_Kco/js/model/iframe'
], function (
    storage,
    messageList,
    config,
    iframe
) {
    'use strict';

    var initialLoad = true,
        ajaxUpdateAction = false;

    return function () {
        if (!initialLoad || !config.isKssEnabled) {
            return;
        }

        if (ajaxUpdateAction) {
            return;
        }

        ajaxUpdateAction = true;
        iframe.suspend();
        // KUSTOM-128: only called from iframe events, so the checkout is editable
        storage.post(config.updateKssStatusUrl + '?origin=iframe').done(function (response) {
            if (response && response.locked && response.message) {
                // KUSTOM-128: the change was refused, tell the customer
                messageList.addErrorMessage({message: response.message});
            }
            iframe.resume();
            initialLoad = false;
            ajaxUpdateAction = false;
        });
    };
});
