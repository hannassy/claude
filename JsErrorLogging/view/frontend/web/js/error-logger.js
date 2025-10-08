define([
    'jquery',
    'mage/cookies'
], function ($) {
    'use strict';

    return {
        logUrl: window.jsErrorLoggerUrl || '',
        errorQueue: [],
        isProcessing: false,

        init: function() {
            this.attachErrorHandlers();
            this.setupUnhandledRejection();
        },

        attachErrorHandlers: function() {
            var self = this;

            window.addEventListener('error', function(event) {
                self.captureError({
                    message: event.message,
                    source: event.filename,
                    lineno: event.lineno,
                    colno: event.colno,
                    stack: event.error ? event.error.stack : null
                });
            }, true);
        },

        setupUnhandledRejection: function() {
            var self = this;

            window.addEventListener('unhandledrejection', function(event) {
                self.captureError({
                    message: 'Unhandled Promise Rejection: ' + (event.reason ? event.reason.message || event.reason : 'Unknown'),
                    source: 'Promise',
                    lineno: null,
                    colno: null,
                    stack: event.reason ? event.reason.stack : null
                });
            });
        },

        captureError: function(errorData) {
            var enrichedData = this.enrichErrorData(errorData);
            this.errorQueue.push(enrichedData);
            this.processQueue();
        },

        enrichErrorData: function(errorData) {
            var userAgent = navigator.userAgent;
            var browserInfo = this.parseBrowser(userAgent);

            return $.extend({}, errorData, {
                url: window.location.href,
                userAgent: userAgent,
                browser: browserInfo.name,
                browserVersion: browserInfo.version,
                os: this.parseOS(userAgent),
                deviceType: this.getDeviceType(),
                viewportWidth: window.innerWidth,
                viewportHeight: window.innerHeight,
                timestamp: new Date().toISOString()
            });
        },

        parseBrowser: function(ua) {
            var browsers = [
                {name: 'Edge', pattern: /Edg\/(\d+)/},
                {name: 'Chrome', pattern: /Chrome\/(\d+)/},
                {name: 'Safari', pattern: /Safari\/(\d+)/},
                {name: 'Firefox', pattern: /Firefox\/(\d+)/},
                {name: 'IE', pattern: /MSIE (\d+)|Trident.*rv:(\d+)/}
            ];

            for (var i = 0; i < browsers.length; i++) {
                var match = ua.match(browsers[i].pattern);
                if (match) {
                    return {
                        name: browsers[i].name,
                        version: match[1] || match[2] || 'Unknown'
                    };
                }
            }

            return {name: 'Unknown', version: 'Unknown'};
        },

        parseOS: function(ua) {
            if (ua.indexOf('Win') !== -1) return 'Windows';
            if (ua.indexOf('Mac') !== -1) return 'MacOS';
            if (ua.indexOf('Linux') !== -1) return 'Linux';
            if (ua.indexOf('Android') !== -1) return 'Android';
            if (ua.indexOf('iOS') !== -1 || ua.indexOf('iPhone') !== -1 || ua.indexOf('iPad') !== -1) return 'iOS';
            return 'Unknown';
        },

        getDeviceType: function() {
            if (/Mobi|Android/i.test(navigator.userAgent)) return 'Mobile';
            if (/Tablet|iPad/i.test(navigator.userAgent)) return 'Tablet';
            return 'Desktop';
        },

        processQueue: function() {
            if (this.isProcessing || this.errorQueue.length === 0) {
                return;
            }

            this.isProcessing = true;
            var errorData = this.errorQueue.shift();
            var self = this;

            $.ajax({
                url: this.logUrl,
                type: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify(errorData),
                complete: function() {
                    self.isProcessing = false;
                    if (self.errorQueue.length > 0) {
                        setTimeout(function() {
                            self.processQueue();
                        }, 100);
                    }
                }
            });
        }
    };
});
