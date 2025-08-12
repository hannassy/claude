define([
    'jquery',
    'uiRegistry'
], function ($, registry) {
    'use strict';

    return function (target) {
        return target.extend({
            initialize: function () {
                this._super();

                // Only add import button for relation grid - ADD THIS CHECK
                if (this.ns === 'transfernetwork_relation_listing' &&
                    window.location.href.includes('transfernetwork/relation')) {
                    this.addImportButton();
                }

                return this;
            },

            addImportButton: function () {
                var self = this;

                setTimeout(function () {
                    // Check if button already exists to prevent duplicates
                    if ($('#import-relations-btn').length > 0) {
                        return;
                    }

                    // Create the import button with unique ID
                    var importBtn = $('<button type="button" id="import-relations-btn" class="action-secondary" style="margin-left: 10px;">' +
                        '<span>Import from Excel</span>' +
                        '</button>');

                    // Add click handler
                    importBtn.on('click', function (e) {
                        e.preventDefault();
                        self.openImportModal();
                    });

                    // Find the export button and add import button after it
                    var $exportBtn = $('button:contains("Export to Excel")').last();
                    if ($exportBtn.length) {
                        $exportBtn.after(importBtn);
                    } else {
                        // Fallback: add to page actions
                        $('.page-actions-buttons').append(importBtn);
                    }
                }, 1000);
            },

            openImportModal: function () {
                var self = this;

                require(['Magento_Ui/js/modal/modal', 'jquery'], function(modal, $) {
                    // Create a more complete modal HTML
                    var modalHtml = '<div id="import-modal-content">' +
                        '<div class="admin__fieldset">' +
                        '<div class="admin__legend"><span>Import Relations from Excel</span></div>' +
                        '<div class="admin__field">' +
                        '<label class="admin__field-label"><span>Select Excel/CSV File</span></label>' +
                        '<div class="admin__field-control">' +
                        '<input type="file" accept=".xlsx,.xls,.csv" id="import-file-input" class="admin__control-file">' +
                        '</div>' +
                        '<div class="admin__field-note">Supported formats: .xlsx, .xls, .csv</div>' +
                        '</div>' +
                        '<div class="admin__fieldset" style="margin-top: 20px;">' +
                        '<div class="admin__legend"><span>File Format Requirements</span></div>' +
                        '<div class="admin__field-note">' +
                        '<p><strong>Required Columns:</strong></p>' +
                        '<ul>' +
                        '<li><strong>active</strong> - Y for active, N for inactive</li>' +
                        '<li><strong>TransferTo</strong> - Destination location ID</li>' +
                        '<li><strong>TransferFrom</strong> - Source location ID</li>' +
                        '<li><strong>CutoffDays</strong> - Number of days (optional)</li>' +
                        '<li><strong>CutoffTime</strong> - Time in HH:MM format (optional)</li>' +
                        '<li><strong>UnloadMinutes</strong> - Number of minutes (optional)</li>' +
                        '</ul>' +
                        '</div>' +
                        '</div>' +
                        '</div>' +
                        '</div>';

                    var options = {
                        type: 'popup',
                        responsive: true,
                        innerScroll: true,
                        title: 'Import Relations from Excel',
                        modalClass: 'import-relations-modal',
                        buttons: [{
                            text: 'Cancel',
                            class: 'action-secondary',
                            click: function () {
                                this.closeModal();
                            }
                        }, {
                            text: 'Import',
                            class: 'action-primary',
                            click: function () {
                                self.handleImport(this);
                            }
                        }]
                    };

                    modal(options, $(modalHtml)).openModal();
                });
            },

            getExportUrl: function () {
                return window.location.href.replace(/\/index\/.*$/, '/export');
            },

            handleImport: function (modalInstance) {
                var self = this;
                var fileInput = document.getElementById('import-file-input');
                var file = fileInput.files[0];

                if (!file) {
                    alert('Please select a file first.');
                    return;
                }

                // Show loading state
                var importBtn = modalInstance.modal.find('.action-primary');
                var originalText = importBtn.text();
                importBtn.text('Importing...').prop('disabled', true);

                // Create FormData for file upload
                var formData = new FormData();
                formData.append('import_file', file);
                formData.append('form_key', window.FORM_KEY);

                // Upload file first
                $.ajax({
                    url: self.getUploadUrl(),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(uploadResponse) {
                        console.log('Upload response:', uploadResponse);

                        if (uploadResponse.error) {
                            alert('Upload failed: ' + uploadResponse.error);
                            importBtn.text(originalText).prop('disabled', false);
                            return;
                        }

                        // Now process the uploaded file
                        var importData = {
                            import_file: [{
                                file: uploadResponse.file,
                                name: uploadResponse.name,
                                size: uploadResponse.size
                            }],
                            form_key: window.FORM_KEY
                        };

                        $.ajax({
                            url: self.getImportUrl(),
                            type: 'POST',
                            data: importData,
                            success: function(importResponse) {
                                console.log('Import response:', importResponse);

                                if (importResponse.success) {
                                    alert('Import completed successfully!');
                                    modalInstance.closeModal();
                                    // Reload the grid
                                    self.reloadGrid();
                                } else {
                                    alert('Import failed: ' + (importResponse.message || 'Unknown error'));
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Import error:', error);
                                alert('Import failed: ' + error);
                            },
                            complete: function() {
                                importBtn.text(originalText).prop('disabled', false);
                            }
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error('Upload error:', error);
                        alert('Upload failed: ' + error);
                        importBtn.text(originalText).prop('disabled', false);
                    }
                });
            },

            getUploadUrl: function () {
                return window.location.href.replace(/\/index\/.*$/, '/uploadFile');
            },

            getImportUrl: function () {
                return window.location.href.replace(/\/index\/.*$/, '/import');
            },

            reloadGrid: function () {
                // Reload the current page to refresh the grid
                window.location.reload();
            }
        });
    };
});
