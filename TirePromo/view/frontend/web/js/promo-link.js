define([
    'js/lodash.min',
    'js/vue.min'
], function (_, Vue) {
    'use strict';

    return function (config, element) {
        new Vue({
            el: element,
            data: function () {
                return {
                    displayLink: false,
                    promoItems: config.customer_promo
                };
            },
            created: function () {
                this.displayLink = !_.isEmpty(this.promoItems);
            }
        });
    };
});
