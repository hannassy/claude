define([
    'Tirehub_JsErrorLogging/js/error-logger'
], function (errorLogger) {
    'use strict';

    return function (Component) {
        errorLogger.init();

        return Component.extend({
            initialize: function () {
                var self = this;
                var originalInitialize = this._super.bind(this);

                try {
                    originalInitialize();
                } catch (error) {
                    errorLogger.captureError({
                        message: 'Payment component initialization error: ' + error.message,
                        source: 'payment-component',
                        lineno: null,
                        colno: null,
                        stack: error.stack
                    });
                    throw error;
                }

                return self;
            },

            placeOrder: function (data, event) {
                var self = this;
                var originalPlaceOrder = this._super.bind(this);

                try {
                    return originalPlaceOrder(data, event);
                } catch (error) {
                    errorLogger.captureError({
                        message: 'Place order error: ' + error.message,
                        source: 'payment-placeorder',
                        lineno: null,
                        colno: null,
                        stack: error.stack
                    });
                    throw error;
                }
            }
        });
    };
});
