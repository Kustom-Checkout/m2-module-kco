/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
define([
    'mage/storage',
    'Klarna_Kco/js/model/config',
    'Klarna_Kco/js/model/iframe'
], function (
    storage,
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
        storage.post(config.updateKssStatusUrl).done(function () {
            iframe.resume();
            initialLoad = false;
            ajaxUpdateAction = false;
        });
    };
});
