define([
    'jquery',
    'mage/url',
    'Magento_Customer/js/customer-data',
    'jquery/jquery-storageapi'
], function ($, urlBuilder, customerData) {
    'use strict';

    return function (config) {
        var redirectUrl = config.redirectUrl || 'customer/account';

        // Function to clear all Magento storage and cookies
        function clearMagentoStorage() {
            try {
                // Clear specific localStorage items
                var keysToRemove = [];
                for (var i = 0; i < localStorage.length; i++) {
                    var key = localStorage.key(i);
                    if (key && (
                        key.indexOf('mage-cache') !== -1 ||
                        key.indexOf('mage-messages') !== -1 ||
                        key.indexOf('private_content_version') !== -1 ||
                        key.indexOf('section_data_ids') !== -1 ||
                        key.indexOf('product_data_storage') !== -1
                    )) {
                        keysToRemove.push(key);
                    }
                }

                keysToRemove.forEach(function(key) {
                    try {
                        localStorage.removeItem(key);
                        console.log('Removed localStorage item:', key);
                    } catch (e) {
                        console.log('Error removing localStorage item:', key, e);
                    }
                });

                // Clear sessionStorage
                for (var j = sessionStorage.length - 1; j >= 0; j--) {
                    var sessionKey = sessionStorage.key(j);
                    if (sessionKey && (
                        sessionKey.indexOf('mage-cache') !== -1 ||
                        sessionKey.indexOf('mage-messages') !== -1
                    )) {
                        try {
                            sessionStorage.removeItem(sessionKey);
                            console.log('Removed sessionStorage item:', sessionKey);
                        } catch (e) {
                            console.log('Error removing sessionStorage item:', sessionKey, e);
                        }
                    }
                }

                console.log('Cleared Magento storage successfully');
            } catch (e) {
                console.error('Error clearing storage:', e);
            }
        }

        // Function to invalidate and clear customer data sections
        function invalidateCustomerSections() {
            try {
                // Clear messages first
                customerData.set('messages', {});

                // Invalidate all sections
                customerData.invalidate(['*']);

                // Clear the entire customer data storage
                customerData.clear();

                console.log('Invalidated all customer sections');
            } catch (e) {
                console.error('Error invalidating sections:', e);
            }
        }

        // Clear cookies that affect sections
        function clearSectionCookies() {
            try {
                // Clear private content version cookie
                $.cookieStorage.set('private_content_version', null);

                // Clear other relevant cookies
                $.mage.cookies.clear('section_data_ids');
                $.mage.cookies.clear('mage-cache-sessid');
                $.mage.cookies.clear('mage-cache-storage');

                console.log('Cleared section cookies');
            } catch (e) {
                console.error('Error clearing cookies:', e);
            }
        }

        // Execute cleanup in sequence
        console.log('Punchout: Starting cleanup before redirect');

        // 1. Clear cookies
        clearSectionCookies();

        // 2. Invalidate customer sections
        invalidateCustomerSections();

        // 3. Clear storage
        clearMagentoStorage();

        // 4. Set a flag to force refresh on next page
        try {
            localStorage.setItem('punchout_force_sections_refresh', 'true');
            localStorage.setItem('punchout_force_refresh_time', new Date().getTime());
        } catch (e) {
            console.error('Error setting refresh flag:', e);
        }

        // 5. Schedule the redirect with a slight delay to ensure cleanup completes
        console.log('Scheduling redirect to:', redirectUrl);
        setTimeout(function () {
            try {
                // Build target URL with cache buster
                var targetUrl = urlBuilder.build(redirectUrl);
                var cacheBuster = '_=' + new Date().getTime();
                targetUrl += (targetUrl.indexOf('?') !== -1 ? '&' : '?') + cacheBuster;
                window.location.href = targetUrl;
            } catch (e) {
                console.error('Error during redirect:', e);
                // Fall back to simpler redirect if there's an error
                window.location.href = redirectUrl;
            }
        }, 700); // Slightly longer delay to ensure all cleanup completes
    };
});
