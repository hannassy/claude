define([
    'jquery',
    'js/vue.min',
    'js/lodash.min',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Tirehub_TirePromo/js/promo-item/promo-item',
    'Tirehub_DropShipping/js/cancellation-confirmation-modal/cancellation-modal'
], function ($, Vue, _, $t, customerData, promoItem, cancellationModal) {
    return function (config) {
        new Vue({
            el: '#promo-container',
            data: {
                promoItems: config.customer_promo,
                endDropShipUrl: config.endDropShipUrl,
                searchUrl: '',
                showDropShipModal: false,
                dropShipFlow: false
            },
            components: {
                'promo-item': promoItem,
                'cancellation-modal': cancellationModal
            },
            created: function () {
                var dropShipping = customerData.get('dropshipping');
                this.dropShipFlow = _.get(dropShipping(), 'dropshipping_mode', false);

                dropShipping.subscribe($.proxy(function (data) {
                    this.dropShipFlow = _.get(data, 'dropshipping_mode', false);
                }, this));
            },
            methods: {
                copyCode: function (e) {
                    var $target = $(e.target);
                    var $promoContainer = $(e.currentTarget);
                    var $promo = $target.closest('[data-role="promo-code"]');
                    var $promoItems = $promoContainer.find('[data-role="promo-code"]');

                    if (!$promoItems.length || !$promo.length) {
                        return;
                    }

                    navigator.clipboard.writeText($promo.attr('data-copy-code'))
                        .then(function () {
                            $promoItems.removeClass('copied-code');
                            $promoContainer.find('[data-copy-code="' + $promo.attr('data-copy-code') + '"]').addClass('copied-code');
                        })
                        .catch(function (err) {
                            console.log('Error in copying text: ', err);
                        });
                },
                handleDropShipModal: function (url) {
                    this.searchUrl = url;
                    if (this.dropShipFlow) {
                        this.showDropShipModal = true;
                    } else {
                        window.location.replace(this.searchUrl);
                    }
                },
                onDropShipModalCancel: function () {
                    this.searchUrl = '';
                    this.showDropShipModal = false;
                },
                t: function (text) {
                    return $t(text);
                }
            }
        });
    };
});
