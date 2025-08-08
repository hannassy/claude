define([
    'jquery',
    'uiComponent',
    'mage/url'
], function ($, Component, urlBuilder) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Tirehub_Punchout/grid/cxml-response-content',
            contentSelector: '.cxml-response-container',
            loading: false,
            content: '',
            sessionId: null,
            ajaxUrl: ''
        },

        initialize: function () {
            this._super();
            this.initObservable();
            return this;
        },

        initObservable: function () {
            this._super()
                .observe([
                    'loading',
                    'content',
                    'sessionId'
                ]);

            return this;
        },

        updateData: function (params) {
            if (params && params.id) {
                this.sessionId(params.id);
                this.loadContent();
            }
        },

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
                self.content('<div class="message message-error">Error loading cXML response content</div>');
            }).always(function () {
                self.loading(false);
            });
        }
    });
});
