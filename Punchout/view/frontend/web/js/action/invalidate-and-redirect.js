define([
    'jquery',
    'mage/url'
], function ($, urlBuilder) {
    'use strict';

    return function (config) {
        var redirectUrl = config.redirectUrl || 'customer/account';

        // Function to directly clear Magento storage
        function clearMagentoStorage()
        {
            try {
                // Clear localStorage
                for (var i = 0; i < localStorage.length; i++) {
                    var key = localStorage.key(i);
                    if (key && (
                        key.indexOf('mage-cache') !== -1 ||
                        key.indexOf('mage-messages') !== -1 ||
                        key.indexOf('private_content_version') !== -1
                    )) {
                        try {
                            localStorage.removeItem(key);
                            console.log('Removed localStorage item:', key);
                            // Adjust index since we removed an item
                            i--;
                        } catch (e) {
                            console.log('Error removing localStorage item:', key, e);
                        }
                    }
                }

                // Clear sessionStorage
                for (var j = 0; j < sessionStorage.length; j++) {
                    var sessionKey = sessionStorage.key(j);
                    if (sessionKey && (
                        sessionKey.indexOf('mage-cache') !== -1 ||
                        sessionKey.indexOf('mage-messages') !== -1
                    )) {
                        try {
                            sessionStorage.removeItem(sessionKey);
                            console.log('Removed sessionStorage item:', sessionKey);
                            // Adjust index since we removed an item
                            j--;
                        } catch (e) {
                            console.log('Error removing sessionStorage item:', sessionKey, e);
                        }
                    }
                }

                // Force removal of specific cache keys
                var specificKeys = [
                    'mage-cache-storage',
                    'mage-cache-storage-section-invalidation',
                    'mage-cache-sessid',
                    'section_data_ids'
                ];

                specificKeys.forEach(function (key) {
                    try {
                        localStorage.removeItem(key);
                        sessionStorage.removeItem(key);
                    } catch (e) {
                        console.log('Error removing specific key:', key, e);
                    }
                });

                console.log('Cleared Magento storage successfully');
            } catch (e) {
                console.error('Error clearing storage:', e);
            }
        }

        // Clear the storage first
        clearMagentoStorage();

        // Set a flag to indicate we need to refresh sections on the next page
        try {
            localStorage.setItem('mage_force_sections_refresh', 'true');
        } catch (e) {
            console.error('Error setting refresh flag:', e);
        }

        // Schedule the redirect
        console.log('Scheduling redirect to:', redirectUrl);
        setTimeout(function () {
            try {
                // Set no-cache parameters to ensure fresh page load
                var targetUrl = urlBuilder.build(redirectUrl);
                var cacheBuster = '_=' + new Date().getTime();
                targetUrl += (targetUrl.indexOf('?') !== -1 ? '&' : '?') + cacheBuster;

                console.log('Redirecting to:', targetUrl);
                window.location.href = targetUrl;
            } catch (e) {
                console.error('Error during redirect:', e);
                // Fall back to simpler redirect if there's an error
                window.location.href = redirectUrl;
            }
        }, 500);
    };
});