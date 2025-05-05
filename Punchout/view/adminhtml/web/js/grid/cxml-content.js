define([
    'jquery',
    'uiComponent',
    'mage/url'
], function ($, Component, urlBuilder) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Tirehub_Punchout/grid/cxml-content',
            contentSelector: '.cxml-content-container',
            loading: false,
            content: '',
            sessionId: null,
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
                    'sessionId'
                ]);

            return this;
        },

        /**
         * Update data with session ID and load content
         * @param {Object} params - Parameters containing session ID
         */
        updateData: function (params) {
            if (params && params.id) {
                this.sessionId(params.id);
                this.loadContent();
            }
        },

        /**
         * Load cXML content via AJAX
         */
        loadContent: function () {
            var self = this;
            var id = this.sessionId();

            if (!id) {
                return;
            }

            this.loading(true);
            this.content('');

            $.ajax({
                url: this.ajaxUrl,
                data: {
                    id: id
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
                self.content('<div class="message message-error">Error loading cXML content</div>');
            }).always(function () {
                self.loading(false);
            });
        }
    });
});
