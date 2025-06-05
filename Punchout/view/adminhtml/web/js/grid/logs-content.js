define([
    'jquery',
    'uiComponent',
    'mage/url'
], function ($, Component, urlBuilder) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Tirehub_Punchout/grid/logs-content',
            contentSelector: '.logs-content-container',
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
            if (params && params.session_id) {
                this.sessionId(params.session_id);
                this.loadContent();
            }
        },

        loadContent: function () {
            var self = this;
            var sessionId = this.sessionId();

            if (!sessionId) {
                return;
            }

            this.loading(true);
            this.content('');

            $.ajax({
                url: this.ajaxUrl,
                data: {
                    session_id: sessionId
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
                self.content('<div class="message message-error">Error loading logs content</div>');
            }).always(function () {
                self.loading(false);
            });
        }
    });
});
