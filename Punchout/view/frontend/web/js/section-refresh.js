define([
    'jquery',
    'Magento_Customer/js/customer-data'
], function ($, customerData) {
    'use strict';

    return function (config) {
        // Check if we're in a punchout session and haven't reloaded sections yet
        var punchoutSessionId = config.punchoutSessionId || '';
        var reloadFlag = 'punchout_sections_reloaded_' + punchoutSessionId;

        if (config.isPunchoutMode && !localStorage.getItem(reloadFlag)) {
            // Set flag to prevent repeated reloads
            localStorage.setItem(reloadFlag, 'true');

            // Force invalidate and reload all sections
            customerData.invalidate(['*']);
            customerData.reload(['*'], true);
        }
    };
});
