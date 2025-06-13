define([
    'jquery',
    'js/vue.min',
    'mage/translate',
    'js/lodash.min',
    'text!Tirehub_TirePromo/js/promo-item/promo-item.html'
], function ($, Vue, $t, _, template) {
    return Vue.extend({
        template: template,
        props: {
            index: {
                type: Number,
                default: 0
            },
            promo: {
                type: Array,
                default: []
            },
            promoEndsMessage: {
                type: String,
                default: 'Promo ends on '
            }
        },
        methods: {
            showModal: function () {
                this.$emit('handle-drop-ship-modal', this.promo.search_url);
            },
            t: function (text, params) {
                var isArray = _.isArray(params);

                return _.reduce(params, function (result, param, key) {
                    var replaceKey = isArray ? key + 1 : key;
                    return result.replace('%' + replaceKey, param);
                }, $t(text));
            }
        }
    });
});
