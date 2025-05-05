define([
    'jquery',
    'uiComponent',
    'mage/url'
], function ($, Component, urlBuilder) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Tirehub_Punchout/grid/items-content',
            contentSelector: '.items-content-container',
            loading: false,
            content: '',
            buyerCookie: null,
            ajaxUrl: ''
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.initObservable();
            return this;
        },

        /**
         * Initialize observable properties
         */
        initObservable: function () {
            this._super()
                .observe([
                    'loading',
                    'content',
                    'buyerCookie'
                ]);

            return this;
        },

        /**
         * Update data with buyer cookie and load content
         * @param {Object} params - Parameters containing buyer cookie
         */
        updateData: function (params) {
            if (params && params.buyer_cookie) {
                this.buyerCookie(params.buyer_cookie);
                this.loadContent();
            }
        },

        /**
         * Load items content via AJAX
         */
        loadContent: function () {
            var self = this;
            var buyerCookie = this.buyerCookie();

            if (!buyerCookie) {
                return;
            }

            this.loading(true);
            this.content('');

            $.ajax({
                url: this.ajaxUrl,
                data: {
                    buyer_cookie: buyerCookie
                },
                dataType: 'json',
                type: 'GET',
                showLoader: true
            }).done(function (response) {
                if (response.success) {
                    self.content(response.html);
                } else {
                    self.content('<div class="message message-error">' + (response.error || 'Error loading content') + '</div>');
                }
            }).fail(function () {
                self.content('<div class="message message-error">Error loading items content</div>');
            }).always(function () {
                self.loading(false);
            });
        }
    });
});
