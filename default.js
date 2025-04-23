/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'ko',
        'jquery',
        'js/lodash.min',
        'uiComponent',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/payment-service',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/checkout-data-resolver',
        'Magento_Customer/js/customer-data',
        'uiRegistry',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Ui/js/model/messages',
        'uiLayout',
        'Magento_Checkout/js/action/redirect-on-success'
    ],
    function (
        ko,
        $,
        _,
        Component,
        placeOrderAction,
        selectPaymentMethodAction,
        quote,
        customer,
        paymentService,
        checkoutData,
        checkoutDataResolver,
        customerData,
        registry,
        additionalValidators,
        Messages,
        layout,
        redirectOnSuccessAction
    ) {
        'use strict';
        var isShowPlaceOrderButton =  window.checkoutConfig.placeorder.isShowPlaceOrderButton;
        var isPunchoutMode = window.checkoutConfig.isPunchoutMode;
        return Component.extend({
            redirectAfterPlaceOrder: true,
            isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),
            /**
             * After place order callback
             */
            afterPlaceOrder: function () {
                // Override this function and put after place order logic here
            },

            /**
             * Initialize view.
             *
             * @return {exports}
             */
            initialize: function () {
                var billingAddressCode,
                    billingAddressData,
                    defaultAddressData;

                this._super().initChildren();
                quote.billingAddress.subscribe(function (address) {
                    this.isPlaceOrderActionAllowed(isShowPlaceOrderButton && address !== null);
                }, this);
                checkoutDataResolver.resolveBillingAddress();

                billingAddressCode = 'billingAddress' + this.getCode();
                registry.async('checkoutProvider')(function (checkoutProvider) {
                    defaultAddressData = checkoutProvider.get(billingAddressCode);

                    if (defaultAddressData === undefined) {
                        // Skip if payment does not have a billing address form
                        return;
                    }
                    billingAddressData = checkoutData.getBillingAddressFromData();

                    if (billingAddressData) {
                        checkoutProvider.set(
                            billingAddressCode,
                            $.extend(true, {}, defaultAddressData, billingAddressData)
                        );
                    }
                    checkoutProvider.on(billingAddressCode, function (providerBillingAddressData) {
                        checkoutData.setBillingAddressFromData(providerBillingAddressData);
                    }, billingAddressCode);
                });

                this.fetchProducts();
                this.fetchGrandTotal();
                this.fetchShipToId();

                return this;
            },

            /**
             * Initialize child elements
             *
             * @returns {Component} Chainable.
             */
            initChildren: function () {
                this.messageContainer = new Messages();
                this.createMessagesComponent();

                return this;
            },

            fetchProducts: function () {
                var cartObj = customerData.get('cart');
                this.items = _.get(cartObj(), 'items', []);

                cartObj.subscribe($.proxy(function (data) {
                    this.items = _.get(data, 'items', []);
                }, this));
            },

            fetchGrandTotal: function () {
                var itemsSubtotal = customerData.get('items-subtotal');
                this.grandTotal = _.get(itemsSubtotal(), 'config.totalsData.grand_total', 0);

                itemsSubtotal.subscribe($.proxy(function (data) {
                    this.grandTotal = _.get(itemsSubtotal(), 'config.totalsData.grand_total', 0);
                }, this));
            },

            fetchShipToId: function () {
                var shipToIdObj = customerData.get('customer-my-store');
                this.shipToId = _.get(shipToIdObj(), 'shippingAddress.shipToId', '');

                shipToIdObj.subscribe($.proxy(function (data) {
                    this.shipToId = _.get(data, 'shippingAddress.shipToId', '');
                }, this));
            },

            /**
             * Create child message renderer component
             *
             * @returns {Component} Chainable.
             */
            createMessagesComponent: function () {

                var messagesComponent = {
                    parent: this.name,
                    name: this.name + '.messages',
                    displayArea: 'messages',
                    component: 'Magento_Ui/js/view/messages',
                    config: {
                        messageContainer: this.messageContainer
                    }
                };

                layout([messagesComponent]);

                return this;
            },

            /**
             * Place order.
             */
            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }
                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);

                    if (window.googleTagManagerLoaded && window.dataLayer) {
                        var items = _.map(self.items, function (item) {
                            item.item_id = item.itemId;
                            item.item_name = item.productName;
                            item.price = item.incShippingRowTotal || item.inc_shipping_row_total || 0;
                            item.quantity = item.qty;
                            return item;
                        });
                        window.dataLayer.push({
                            event: 'purchase',
                            currency: 'USD',
                            value: self.grandTotal,
                            items: items,
                            shipToId: self.shipToId
                        });
                    }

                    this.getPlaceOrderDeferredObject()
                        .fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(
                            function (response) {

                                if (response && isPunchoutMode) {
                                    var formData = response;
                                    debugger;
                                    // Create form element
                                    var form = $('<form>', {
                                        'action': formData[2], // browser_form_post_url
                                        'method': 'post',
                                        'id': 'punchout-return-form',
                                        'style': 'display: none;'
                                    });

                                    // Add hidden fields for cXML data
                                    $('<input>').attr({
                                        type: 'hidden',
                                        name: 'cxml-urlencoded',
                                        value: formData[0] // cxml-urlencoded
                                    }).appendTo(form);

                                    $('<input>').attr({
                                        type: 'hidden',
                                        name: 'cxml-base64',
                                        value: formData[1] // cxml-base64
                                    }).appendTo(form);

                                    // Append form to body
                                    $('body').append(form);

                                    // Display message to user
                                    $('body').html('<div class="punchout-redirect-message" style="text-align: center; margin: 50px;"><h1>' + $.mage.__('Processing Your Order') + '</h1><p>' + $.mage.__('Please wait while we return you to your procurement system...') + '</p></div>');

                                    // Submit the form
                                    $('#punchout-return-form').submit();
                                    return true;
                                } else {
                                    self.afterPlaceOrder();

                                    if (self.redirectAfterPlaceOrder) {
                                        redirectOnSuccessAction.execute();
                                    }
                                }
                            }
                        );

                    return true;
                }

                return false;
            },

            /**
             * Transfer Cart for Punchout mode.
             */
            transferCart: function () {
                // TODO
                console.warn('Transfer cart. TODO me')
            },

            getPlaceOrderDeferredObject: function () {
                return $.when(
                    placeOrderAction(this.getData(), this.messageContainer)
                );
            },

            /**
             * @return {Boolean}
             */
            selectPaymentMethod: function () {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);

                return true;
            },

            isChecked: ko.computed(function () {
                return quote.paymentMethod() ? quote.paymentMethod().method : null;
            }),

            isRadioButtonVisible: ko.computed(function () {
                return paymentService.getAvailablePaymentMethods().length !== 1;
            }),

            /**
             * Get payment method data
             */
            getData: function () {
                return {
                    'method': this.item.method,
                    'po_number': null,
                    'additional_data': null
                };
            },

            /**
             * Get payment method type.
             */
            getTitle: function () {
                return this.item.title;
            },

            /**
             * Get payment method code.
             */
            getCode: function () {
                return this.item.method;
            },

            /**
             * @return {Boolean}
             */
            validate: function () {
                return true;
            },

            /**
             * @return {String}
             */
            getBillingAddressFormName: function () {
                return 'billing-address-form-' + this.item.method;
            },

            /**
             * Dispose billing address subscriptions
             */
            disposeSubscriptions: function () {
                // dispose all active subscriptions
                var billingAddressCode = 'billingAddress' + this.getCode();

                registry.async('checkoutProvider')(function (checkoutProvider) {
                    checkoutProvider.off(billingAddressCode);
                });
            }
        });
    }
);
