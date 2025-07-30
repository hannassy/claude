define([
    'Magento_Ui/js/form/form',
    'uiRegistry'
], function (Form, registry) {
    'use strict';

    return Form.extend({
        defaults: {
            template: 'ui/form/form'
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            return this;
        },

        /**
         * Save form data
         */
        save: function () {
            var self = this;

            if (!this.validate().valid) {
                return;
            }

            this.setLoading(true);

            this.client.save(this.data, {
                ajaxSave: true,
                ajaxSaveType: 'simple'
            }).done(function (response) {
                self.setLoading(false);

                if (response.success) {
                    self.showSuccessMessage(response.message || 'Relations imported successfully.');
                    self.closeModal();
                    self.reloadGrid();
                } else {
                    self.showErrorMessage(response.message || 'An error occurred during import.');
                }
            }).fail(function (xhr) {
                self.setLoading(false);
                var response;

                try {
                    response = JSON.parse(xhr.responseText);
                } catch (e) {
                    response = { message: 'An unexpected error occurred.' };
                }

                self.showErrorMessage(response.message || 'An error occurred during import.');
            });
        },

        /**
         * Show success message
         */
        showSuccessMessage: function (message) {
            require(['Magento_Ui/js/modal/alert'], function (alert) {
                alert({
                    title: 'Success',
                    content: message,
                    modalClass: 'confirm _success'
                });
            });
        },

        /**
         * Show error message
         */
        showErrorMessage: function (message) {
            require(['Magento_Ui/js/modal/alert'], function (alert) {
                alert({
                    title: 'Error',
                    content: message,
                    modalClass: 'confirm _error'
                });
            });
        },

        /**
         * Close modal
         */
        closeModal: function () {
            var modal = registry.get(this.parentName);
            if (modal && typeof modal.closeModal === 'function') {
                modal.closeModal();
            }
        },

        /**
         * Reload grid after import
         */
        reloadGrid: function () {
            var grid = registry.get('transfernetwork_relation_listing.transfernetwork_relation_listing_data_source');
            if (grid && typeof grid.reload === 'function') {
                grid.reload();
            }
        },

        /**
         * Set loading state
         */
        setLoading: function (loading) {
            var modal = registry.get(this.parentName);
            if (modal) {
                var importButton = modal.options.buttons.find(function(button) {
                    return button.text === 'Import';
                });

                if (importButton) {
                    if (loading) {
                        importButton.text = 'Importing...';
                        importButton.disabled = true;
                    } else {
                        importButton.text = 'Import';
                        importButton.disabled = false;
                    }
                }
            }
        }
    });
});
