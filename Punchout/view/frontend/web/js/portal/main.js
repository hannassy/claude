define([
    'js/vue.min',
    'js/lodash.min',
    'jquery',
    'mage/translate',
    'js/vue-select.min'
], function (Vue, _, $, $t, VueSelect) {
    return function (config) {
        new Vue({
            el: '#punchout-portal-session-container',
            data: {
                customerName: config.customerName,
                locationSelectUrl: config.locationSelectUrl,
                locationOptions: config.locationOptions,
                cookie: config.cookie,
                selectedLocation: ''
            },
            components: {
                'vue-select': VueSelect.VueSelect
            },
            created: function () {
                this.locationOptions = _.map(this.locationOptions, function (item) {
                    return {
                        label: item.label,
                        value: item.value
                    };
                });
            },
            methods: {
                onLocationSelect: function () {
                    this.$nextTick(function () {
                        this.$refs.locationSelectForm.submit();
                    });
                },
                t: function (text) {
                    return $t(text);
                }
            },
            computed: {
                locationId: function () {
                    return _.get(this.selectedLocation, 'value', '');
                },
                cookie: function () {
                    return this.cookie;
                }
            }
        });
    };
});
