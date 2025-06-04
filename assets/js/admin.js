/**
 * Admin JavaScript for Help Me Donations Plugin
 */

(function ($) {
    'use strict';

    // Admin utility functions
    const ZimAdmin = {

        /**
         * Show loading state
         */
        showLoading: function (element) {
            const $element = $(element);
            $element.addClass('zim-loading').prop('disabled', true);

            if ($element.hasClass('zim-btn')) {
                $element.prepend('<span class="zim-spinner"></span>');
            }
        },

        /**
         * Hide loading state
         */
        hideLoading: function (element) {
            const $element = $(element);
            $element.removeClass('zim-loading').prop('disabled', false);
            $element.find('.zim-spinner').remove();
        },

        /**
         * Show notification
         */
        showNotification: function (message, type = 'info') {
            const $notice = $(`
                <div class="zim-notice zim-notice-${type}">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            $('.wrap h1').after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);

            // Manual dismiss
            $notice.on('click', '.notice-dismiss', function () {
                $notice.fadeOut(() => $notice.remove());
            });
        },

        /**
         * Confirm dialog
         */
        confirm: function (message, callback) {
            if (window.confirm(message)) {
                callback();
            }
        },

        /**
         * AJAX request wrapper
         */
        ajax: function (action, data, callback, errorCallback) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: action,
                    nonce: zimDonationsAdmin.nonce,
                    ...data
                },
                success: function (response) {
                    if (response.success) {
                        callback(response.data);
                    } else {
                        const message = response.data?.message || 'Operation failed';
                        if (errorCallback) {
                            errorCallback(message);
                        } else {
                            ZimAdmin.showNotification(message, 'error');
                        }
                    }
                },
                error: function () {
                    const message = 'Network error occurred';
                    if (errorCallback) {
                        errorCallback(message);
                    } else {
                        ZimAdmin.showNotification(message, 'error');
                    }
                }
            });
        }
    };

    // Campaign Management
    const CampaignManager = {

        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            $(document).on('click', '.edit-campaign', this.editCampaign);
            $(document).on('click', '.delete-campaign', this.deleteCampaign);
            $(document).on('click', '#add-new-campaign', this.addCampaign);
            $(document).on('submit', '#campaign-form', this.saveCampaign);
        },

        editCampaign: function (e) {
            e.preventDefault();
            const campaignId = $(this).data('id');
            CampaignManager.loadCampaignForm(campaignId);
        },

        deleteCampaign: function (e) {
            e.preventDefault();
            const campaignId = $(this).data('id');

            ZimAdmin.confirm(zimDonationsAdmin.strings.confirm_delete, function () {
                ZimAdmin.ajax('delete_campaign', {
                    campaign_id: campaignId
                }, function (data) {
                    ZimAdmin.showNotification('Campaign deleted successfully', 'success');
                    location.reload();
                });
            });
        },

        addCampaign: function (e) {
            e.preventDefault();
            CampaignManager.loadCampaignForm();
        },

        loadCampaignForm: function (campaignId = null) {
            // This would load a modal or redirect to campaign form
            // For now, showing placeholder
            ZimAdmin.showNotification('Campaign form will be implemented', 'info');
        },

        saveCampaign: function (e) {
            e.preventDefault();

            const $form = $(this);
            const formData = $form.serialize();

            ZimAdmin.showLoading($form.find('[type="submit"]'));

            ZimAdmin.ajax('save_campaign', formData, function (data) {
                ZimAdmin.hideLoading($form.find('[type="submit"]'));
                ZimAdmin.showNotification('Campaign saved successfully', 'success');

                // Optionally redirect or update UI
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                }
            }, function (error) {
                ZimAdmin.hideLoading($form.find('[type="submit"]'));
                ZimAdmin.showNotification(error, 'error');
            });
        }
    };

    // Settings Management
    const SettingsManager = {

        init: function () {
            this.bindEvents();
            this.initColorPickers();
            this.initGatewayToggles();
        },

        bindEvents: function () {
            $(document).on('click', '.zim-settings-tab', this.switchTab);
            $(document).on('change', '.gateway-toggle input', this.toggleGateway);
            $(document).on('click', '.test-gateway', this.testGateway);
            $(document).on('click', '#update-exchange-rates', this.updateExchangeRates);
        },

        switchTab: function (e) {
            e.preventDefault();

            const $tab = $(this);
            const targetTab = $tab.attr('href');

            // Update tab states
            $('.zim-settings-tab').removeClass('active');
            $tab.addClass('active');

            // Show/hide content
            $('.zim-settings-section').hide();
            $(targetTab).show();
        },

        initColorPickers: function () {
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker();
            }
        },

        initGatewayToggles: function () {
            $('.gateway-toggle input').each(function () {
                const $toggle = $(this);
                const $body = $toggle.closest('.gateway-settings').find('.gateway-body');

                if ($toggle.is(':checked')) {
                    $body.addClass('active');
                } else {
                    $body.removeClass('active');
                }
            });
        },

        toggleGateway: function () {
            const $toggle = $(this);
            const $body = $toggle.closest('.gateway-settings').find('.gateway-body');

            if ($toggle.is(':checked')) {
                $body.addClass('active');
            } else {
                $body.removeClass('active');
            }
        },

        testGateway: function (e) {
            e.preventDefault();

            const $button = $(this);
            const gateway = $button.data('gateway');

            ZimAdmin.showLoading($button);

            ZimAdmin.ajax('test_gateway', {
                gateway: gateway
            }, function (data) {
                ZimAdmin.hideLoading($button);
                ZimAdmin.showNotification(`${gateway} test completed successfully`, 'success');
            }, function (error) {
                ZimAdmin.hideLoading($button);
                ZimAdmin.showNotification(`${gateway} test failed: ${error}`, 'error');
            });
        },

        updateExchangeRates: function (e) {
            e.preventDefault();

            const $button = $(this);

            ZimAdmin.showLoading($button);

            ZimAdmin.ajax('update_exchange_rates', {}, function (data) {
                ZimAdmin.hideLoading($button);
                ZimAdmin.showNotification('Exchange rates updated successfully', 'success');

                // Update displayed rates if available
                if (data.rates) {
                    SettingsManager.updateRateDisplay(data.rates);
                }
            }, function (error) {
                ZimAdmin.hideLoading($button);
                ZimAdmin.showNotification(`Rate update failed: ${error}`, 'error');
            });
        },

        updateRateDisplay: function (rates) {
            Object.keys(rates).forEach(currency => {
                $(`.rate-${currency}`).text(rates[currency]);
            });
        }
    };

    // Analytics Dashboard
    const AnalyticsDashboard = {

        init: function () {
            this.bindEvents();
            this.loadDashboardData();
        },

        bindEvents: function () {
            $(document).on('change', '#report-period', this.changePeriod);
            $(document).on('click', '#refresh-reports', this.refreshReports);
            $(document).on('click', '.export-data', this.exportData);
        },

        changePeriod: function () {
            const period = $(this).val();
            AnalyticsDashboard.loadAnalyticsData(period);
        },

        refreshReports: function (e) {
            e.preventDefault();

            const period = $('#report-period').val() || '30d';
            const $button = $(this);

            ZimAdmin.showLoading($button);

            AnalyticsDashboard.loadAnalyticsData(period, function () {
                ZimAdmin.hideLoading($button);
            });
        },

        loadDashboardData: function () {
            const period = $('#report-period').val() || '30d';
            this.loadAnalyticsData(period);
        },

        loadAnalyticsData: function (period = '30d', callback) {
            ZimAdmin.ajax('get_analytics_data', {
                data_type: 'dashboard',
                period: period
            }, function (data) {
                AnalyticsDashboard.updateDashboard(data);
                if (callback) callback();
            });
        },

        updateDashboard: function (data) {
            // Update stat cards
            $('.total-donations .stat-number').text(data.total_donations || 0);
            $('.total-raised .stat-number').text(ZimAdmin.formatCurrency(data.total_raised || 0));
            $('.average-donation .stat-number').text(ZimAdmin.formatCurrency(data.average_donation || 0));
            $('.unique-donors .stat-number').text(data.unique_donors || 0);

            // Update charts if available
            if (window.Chart && data.chart_data) {
                this.updateCharts(data.chart_data);
            }
        },

        updateCharts: function (chartData) {
            // Chart implementation would go here
            console.log('Chart data received:', chartData);
        },

        exportData: function (e) {
            e.preventDefault();

            const $button = $(this);
            const dataType = $button.data('type') || 'donations';
            const format = $button.data('format') || 'csv';
            const period = $('#report-period').val() || '30d';

            ZimAdmin.showLoading($button);

            ZimAdmin.ajax('export_analytics', {
                data_type: dataType,
                format: format,
                period: period
            }, function (data) {
                ZimAdmin.hideLoading($button);

                if (data.download_url) {
                    // Create temporary download link
                    const link = document.createElement('a');
                    link.href = data.download_url;
                    link.download = data.filename || 'export.csv';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    ZimAdmin.showNotification('Export completed successfully', 'success');
                }
            }, function (error) {
                ZimAdmin.hideLoading($button);
                ZimAdmin.showNotification(`Export failed: ${error}`, 'error');
            });
        }
    };

    // Donation Management
    const DonationManager = {

        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            $(document).on('click', '#export-donations', this.exportDonations);
            $(document).on('change', '.bulk-action-select', this.handleBulkAction);
            $(document).on('click', '.view-donation', this.viewDonation);
        },

        exportDonations: function (e) {
            e.preventDefault();

            const $button = $(this);

            ZimAdmin.showLoading($button);

            ZimAdmin.ajax('export_donations', {}, function (data) {
                ZimAdmin.hideLoading($button);
                ZimAdmin.showNotification('Export completed', 'success');

                if (data.download_url) {
                    window.open(data.download_url, '_blank');
                }
            }, function (error) {
                ZimAdmin.hideLoading($button);
                ZimAdmin.showNotification(`Export failed: ${error}`, 'error');
            });
        },

        handleBulkAction: function () {
            const action = $(this).val();

            if (action && action !== '-1') {
                const selectedItems = $('.donation-checkbox:checked').length;

                if (selectedItems === 0) {
                    ZimAdmin.showNotification('Please select items to perform bulk action', 'warning');
                    return;
                }

                ZimAdmin.confirm(`Are you sure you want to ${action} ${selectedItems} item(s)?`, function () {
                    DonationManager.performBulkAction(action);
                });
            }
        },

        performBulkAction: function (action) {
            const selectedIds = [];
            $('.donation-checkbox:checked').each(function () {
                selectedIds.push($(this).val());
            });

            ZimAdmin.ajax('bulk_action_donations', {
                action: action,
                ids: selectedIds
            }, function (data) {
                ZimAdmin.showNotification(`Bulk ${action} completed successfully`, 'success');
                location.reload();
            });
        },

        viewDonation: function (e) {
            e.preventDefault();

            const donationId = $(this).data('id');
            // Implementation would show donation details in modal
            ZimAdmin.showNotification('Donation details modal will be implemented', 'info');
        }
    };

    // Form Builder (placeholder)
    const FormBuilder = {

        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            $(document).on('click', '#add-new-form', this.addForm);
            $(document).on('click', '.edit-form', this.editForm);
            $(document).on('click', '.delete-form', this.deleteForm);
        },

        addForm: function (e) {
            e.preventDefault();
            ZimAdmin.showNotification('Form builder will be implemented', 'info');
        },

        editForm: function (e) {
            e.preventDefault();
            ZimAdmin.showNotification('Form editor will be implemented', 'info');
        },

        deleteForm: function (e) {
            e.preventDefault();
            const formId = $(this).data('id');

            ZimAdmin.confirm(zimDonationsAdmin.strings.confirm_delete, function () {
                ZimAdmin.showNotification('Form deletion will be implemented', 'info');
            });
        }
    };

    // Utility functions
    ZimAdmin.formatCurrency = function (amount, currency = 'USD') {
        const symbols = {
            'USD': '$',
            'ZIG': 'ZiG',
            'EUR': '€',
            'GBP': '£',
            'ZAR': 'R'
        };

        const symbol = symbols[currency] || currency;
        return `${symbol}${parseFloat(amount || 0).toLocaleString()}`;
    };

    // Initialize when document is ready
    $(document).ready(function () {

        // Initialize components based on current page
        const currentPage = new URLSearchParams(window.location.search).get('page');

        switch (currentPage) {
            case 'zim-donations':
                AnalyticsDashboard.init();
                break;
            case 'zim-donations-donations':
                DonationManager.init();
                break;
            case 'zim-donations-campaigns':
                CampaignManager.init();
                break;
            case 'zim-donations-forms':
                FormBuilder.init();
                break;
            case 'zim-donations-reports':
                AnalyticsDashboard.init();
                break;
            case 'zim-donations-settings':
                SettingsManager.init();
                break;
        }

        // Global event handlers
        $(document).on('click', '.notice-dismiss', function () {
            $(this).closest('.notice').fadeOut();
        });

        // Tooltip initialization if available
        if ($.fn.tooltip) {
            $('[data-tooltip]').tooltip();
        }

        // Confirmation dialogs
        $(document).on('click', '.confirm-action', function (e) {
            const message = $(this).data('confirm') || zimDonationsAdmin.strings.confirm_delete;

            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-save functionality for settings
        let autoSaveTimeout;
        $(document).on('change', '.auto-save', function () {
            clearTimeout(autoSaveTimeout);

            autoSaveTimeout = setTimeout(function () {
                $('#settings-form').trigger('submit');
            }, 2000);
        });

        // Real-time validation
        $(document).on('blur', '.validate-field', function () {
            const $field = $(this);
            const value = $field.val().trim();
            const fieldType = $field.attr('type') || $field.prop('tagName').toLowerCase();

            // Clear previous validation
            $field.removeClass('error');
            $field.siblings('.field-error').remove();

            // Validate based on type
            let isValid = true;
            let errorMessage = '';

            if ($field.prop('required') && !value) {
                isValid = false;
                errorMessage = 'This field is required.';
            } else if (value) {
                switch (fieldType) {
                    case 'email':
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(value)) {
                            isValid = false;
                            errorMessage = 'Please enter a valid email address.';
                        }
                        break;
                    case 'url':
                        try {
                            new URL(value);
                        } catch {
                            isValid = false;
                            errorMessage = 'Please enter a valid URL.';
                        }
                        break;
                }
            }

            if (!isValid) {
                $field.addClass('error');
                $field.after(`<div class="field-error">${errorMessage}</div>`);
            }
        });
    });

    // Make ZimAdmin globally available
    window.ZimAdmin = ZimAdmin;

})(jQuery); 