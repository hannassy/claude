define([
    'js/vue.min',
    'js/lodash.min',
    'jquery',
    'mage/translate'
], function (Vue, _, $, $t) {
    return Vue.extend({
        template: '#promo-component-template',
        props: {
            value: {
                type: String,
                default: ''
            },
            percent: {
                type: Number,
                default: ''
            },
            code: {
                type: String,
                default: ''
            },
            endDate: {
                type: String,
                default: ''
            },
            minQty: {
                type: Number,
                default: ''
            },
            maxQty: {
                type: Number,
                default: ''
            }
        },
        data: function () {
            return {
                copiedToClipboard: false
            };
        },
        mounted: function () {
            $(document).on('promo-copied-to-clipboard', function (event, copiedCode) {
                if (this.code !== copiedCode) {
                    this.copiedToClipboard = false;
                }
            }.bind(this));
        },
        methods: {
            copyToClipboard: function (code) {
                navigator.clipboard.writeText(code);
                this.copiedToClipboard = true;
                $(document).trigger('promo-copied-to-clipboard', code);
            },
            t: function (text) {
                return $t(text);
            }
        }
    });
});
