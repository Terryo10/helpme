/**
 * Frontend JavaScript for Help Me Donations Plugin
 */

(function ($) {
    'use strict';

    // Main donation form class
    class HelpMeDonationForm {
        constructor(formElement) {
            this.form = $(formElement);
            this.currentStep = 1;
            this.totalSteps = this.form.find('.helpme-form-section').length;
            this.isProcessing = false;

            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeForm();
            this.updateStepIndicators();
        }

        bindEvents() {
            // Amount selection
            this.form.on('click', '.helpme-amount-button', this.handleAmountSelection.bind(this));
            this.form.on('input', '.helpme-custom-amount-input', this.handleCustomAmount.bind(this));

            // Recurring options
            this.form.on('change', '.helpme-recurring-checkbox', this.handleRecurringToggle.bind(this));
            this.form.on('click', '.helpme-interval-option', this.handleIntervalSelection.bind(this));

            // Navigation
            this.form.on('click', '.helpme-next-step', this.nextStep.bind(this));
            this.form.on('click', '.helpme-prev-step', this.prevStep.bind(this));

            // Payment methods
            this.form.on('change', '.helpme-payment-radio', this.handlePaymentMethodChange.bind(this));

            // Form submission
            this.form.on('submit', this.handleSubmit.bind(this));

            // Form validation
            this.form.on('blur', 'input, select, textarea', this.validateField.bind(this));
        }

        initializeForm() {
            // Show first step
            this.showStep(1);

            // Initialize amount buttons
            const presetAmounts = this.form.data('preset-amounts');
            if (presetAmounts) {
                this.setupPresetAmounts(presetAmounts);
            }

            // Set default currency
            const defaultCurrency = this.form.data('currency') || 'USD';
            this.updateCurrencyDisplay(defaultCurrency);
        }

        setupPresetAmounts(amounts) {
            const amountContainer = this.form.find('.helpme-amount-buttons');
            const currency = this.form.data('currency') || 'USD';

            if (amountContainer.length && amounts.length) {
                amountContainer.empty();

                amounts.forEach(amount => {
                    const button = $(`
                        <button type="button" class="helpme-amount-button" data-amount="${amount}">
                            ${this.formatCurrency(amount, currency)}
                        </button>
                    `);
                    amountContainer.append(button);
                });
            }
        }

        handleAmountSelection(e) {
            e.preventDefault();

            const button = $(e.currentTarget);
            const amount = button.data('amount');

            // Update UI
            this.form.find('.helpme-amount-button').removeClass('selected');
            button.addClass('selected');

            // Clear custom amount
            this.form.find('.helpme-custom-amount-input').val('');

            // Update hidden field
            this.form.find('.helpme-donation-amount').val(amount);

            this.validateStep();
        }

        handleCustomAmount(e) {
            const input = $(e.currentTarget);
            const amount = parseFloat(input.val()) || 0;

            // Clear preset selection
            this.form.find('.helpme-amount-button').removeClass('selected');

            // Update hidden field
            this.form.find('.helpme-donation-amount').val(amount);

            this.validateField(e);
            this.validateStep();
        }

        handleRecurringToggle(e) {
            const checkbox = $(e.currentTarget);
            const intervalContainer = this.form.find('.helpme-recurring-interval');

            if (checkbox.is(':checked')) {
                intervalContainer.addClass('show');
                // Select default interval
                intervalContainer.find('.helpme-interval-option').first().click();
            } else {
                intervalContainer.removeClass('show');
                this.form.find('.helpme-recurring-interval-input').val('');
            }
        }

        handleIntervalSelection(e) {
            e.preventDefault();

            const option = $(e.currentTarget);
            const interval = option.data('interval');

            // Update UI
            this.form.find('.helpme-interval-option').removeClass('selected');
            option.addClass('selected');

            // Update hidden field
            this.form.find('.helpme-recurring-interval-input').val(interval);
        }

        handlePaymentMethodChange(e) {
            const radio = $(e.currentTarget);
            const method = radio.val();

            // Update UI
            this.form.find('.helpme-payment-method').removeClass('selected');
            radio.closest('.helpme-payment-method').addClass('selected');

            // Show/hide payment forms
            this.form.find('.helpme-payment-form').hide();
            this.form.find(`.helpme-payment-form[data-gateway="${method}"]`).show();
        }

        nextStep() {
            if (this.isProcessing) return;

            if (this.validateStep()) {
                if (this.currentStep < this.totalSteps) {
                    this.currentStep++;
                    this.showStep(this.currentStep);
                    this.updateStepIndicators();
                }
            }
        }

        prevStep() {
            if (this.isProcessing) return;

            if (this.currentStep > 1) {
                this.currentStep--;
                this.showStep(this.currentStep);
                this.updateStepIndicators();
            }
        }

        showStep(stepNumber) {
            this.form.find('.helpme-form-section').removeClass('active');
            this.form.find(`.helpme-form-section[data-step="${stepNumber}"]`).addClass('active');

            // Update navigation buttons
            const prevBtn = this.form.find('.helpme-prev-step');
            const nextBtn = this.form.find('.helpme-next-step');
            const submitBtn = this.form.find('.helpme-submit-donation');

            if (stepNumber === 1) {
                prevBtn.hide();
            } else {
                prevBtn.show();
            }

            if (stepNumber === this.totalSteps) {
                nextBtn.hide();
                submitBtn.show();
            } else {
                nextBtn.show();
                submitBtn.hide();
            }
        }

        updateStepIndicators() {
            this.form.find('.helpme-step').each((index, step) => {
                const stepNum = index + 1;
                const $step = $(step);

                $step.removeClass('active completed');

                if (stepNum < this.currentStep) {
                    $step.addClass('completed');
                } else if (stepNum === this.currentStep) {
                    $step.addClass('active');
                }
            });
        }

        validateStep() {
            const currentSection = this.form.find(`.helpme-form-section[data-step="${this.currentStep}"]`);
            let isValid = true;

            // Validate required fields in current step
            currentSection.find('input[required], select[required], textarea[required]').each((index, field) => {
                if (!this.validateField({ currentTarget: field })) {
                    isValid = false;
                }
            });

            // Step-specific validations
            if (this.currentStep === 1) {
                // Validate amount
                const amount = parseFloat(this.form.find('.helpme-donation-amount').val()) || 0;
                if (amount <= 0) {
                    this.showFieldError(this.form.find('.helpme-amount-selection'), 'Please select a donation amount.');
                    isValid = false;
                }
            }

            return isValid;
        }

        validateField(e) {
            const field = $(e.currentTarget);
            const value = field.val().trim();
            const fieldType = field.attr('type') || field.prop('tagName').toLowerCase();
            let isValid = true;
            let errorMessage = '';

            // Clear previous errors
            this.clearFieldError(field);

            // Required field validation
            if (field.prop('required') && !value) {
                errorMessage = 'This field is required.';
                isValid = false;
            }

            // Type-specific validations
            if (value && isValid) {
                switch (fieldType) {
                    case 'email':
                        if (!this.isValidEmail(value)) {
                            errorMessage = 'Please enter a valid email address.';
                            isValid = false;
                        }
                        break;

                    case 'tel':
                        if (!this.isValidPhone(value)) {
                           
                            // errorMessage = 'Please enter a valid phone number.';
                            isValid = true;
                        }
                        break;

                    case 'number':
                        const min = parseFloat(field.attr('min'));
                        const max = parseFloat(field.attr('max'));
                        const numValue = parseFloat(value);

                        if (isNaN(numValue)) {
                            errorMessage = 'Please enter a valid number.';
                            isValid = false;
                        } else if (min && numValue < min) {
                            errorMessage = `Value must be at least ${min}.`;
                            isValid = false;
                        } else if (max && numValue > max) {
                            errorMessage = `Value must not exceed ${max}.`;
                            isValid = false;
                        }
                        break;
                }
            }

            if (!isValid) {
                this.showFieldError(field, errorMessage);
            }

            return isValid;
        }

        showFieldError(field, message) {
            const errorElement = $(`<div class="helpme-field-error">${message}</div>`);

            // Remove existing error
            field.siblings('.helpme-field-error').remove();
            field.removeClass('error');

            // Add new error
            field.addClass('error');
            field.after(errorElement);
        }

        clearFieldError(field) {
            field.removeClass('error');
            field.siblings('.helpme-field-error').remove();
        }

        handleSubmit(e) {
            e.preventDefault();

            if (this.isProcessing) return;

            if (!this.validateStep()) {
                return;
            }

            this.processDonation();
        }

        processDonation() {
            this.isProcessing = true;
            this.showProcessingState();

            const formData = this.getFormData();

            $.ajax({
                url: helpmeDonations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'process_donation',
                    nonce: helpmeDonations.nonce,
                    ...formData
                },
                success: (response) => {
                    if (response.success) {
                        this.handlePaymentRedirect(response.data);
                    } else {
                        this.showError(response.data.message || 'Payment processing failed.');
                    }
                },
                error: () => {
                    this.showError('Payment processing failed. Please try again.');
                },
                complete: () => {
                    this.isProcessing = false;
                    this.hideProcessingState();
                }
            });
        }

        getFormData() {
            const data = {};

            this.form.find('input, select, textarea').each((index, field) => {
                const $field = $(field);
                const name = $field.attr('name');

                if (name) {
                    if ($field.attr('type') === 'checkbox') {
                        data[name] = $field.is(':checked');
                    } else if ($field.attr('type') === 'radio') {
                        if ($field.is(':checked')) {
                            data[name] = $field.val();
                        }
                    } else {
                        data[name] = $field.val();
                    }
                }
            });

            return data;
        }

        handlePaymentRedirect(paymentData) {
            if (paymentData.redirect_url) {
                // Redirect to payment gateway
                window.location.href = paymentData.redirect_url;
            } else if (paymentData.requires_action) {
                // Handle in-page payment processing (e.g., Stripe Elements)
                this.handleInPagePayment(paymentData);
            } else {
                // Payment completed
                this.showSuccess('Thank you for your donation!');
                setTimeout(() => {
                    if (paymentData.success_url) {
                        window.location.href = paymentData.success_url;
                    }
                }, 2000);
            }
        }

        handleInPagePayment(paymentData) {
            // This would be implemented based on specific gateway requirements
            console.log('In-page payment processing:', paymentData);
        }

        showProcessingState() {
            const submitBtn = this.form.find('.helpme-submit-donation');
            submitBtn.addClass('helpme-btn-loading').prop('disabled', true);

            this.form.addClass('helpme-loading');
        }

        hideProcessingState() {
            const submitBtn = this.form.find('.helpme-submit-donation');
            submitBtn.removeClass('helpme-btn-loading').prop('disabled', false);

            this.form.removeClass('helpme-loading');
        }

        showError(message) {
            this.showMessage(message, 'error');
        }

        showSuccess(message) {
            this.showMessage(message, 'success');
        }

        showMessage(message, type = 'info') {
            const messageElement = $(`
                <div class="helpme-message helpme-message-${type}">
                    ${message}
                </div>
            `);

            // Remove existing messages
            this.form.find('.helpme-message').remove();

            // Add new message
            this.form.prepend(messageElement);

            // Auto-remove after delay (except for errors)
            // if (type !== 'error') {
            //     setTimeout(() => {
            //         messageElement.fadeOut(() => messageElement.remove());
            //     }, 5000);
            // }
        }

        formatCurrency(amount, currency) {
            const symbols = {
                'USD': '$',
                'ZIG': 'ZiG',
                'EUR': '€',
                'GBP': '£',
                'ZAR': 'R'
            };

            const symbol = symbols[currency] || currency;
            return `${symbol}${amount.toLocaleString()}`;
        }

        updateCurrencyDisplay(currency) {
            this.form.find('.helpme-currency-symbol').text(this.getCurrencySymbol(currency));
        }

        getCurrencySymbol(currency) {
            const symbols = {
                'USD': '$',
                'ZIG': 'ZiG',
                'EUR': '€',
                'GBP': '£',
                'ZAR': 'R'
            };

            return symbols[currency] || currency;
        }

        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        isValidPhone(phone) {
            const phoneRegex = /^\d{9}$/;
            return phoneRegex.test(phone);
        }
    }

    // Campaign progress animation
    class CampaignProgress {
        constructor(element) {
            this.element = $(element);
            this.init();
        }

        init() {
            this.animateProgress();
        }

        animateProgress() {
            const progressBar = this.element.find('.helpme-progress-fill');
            const targetWidth = progressBar.data('percentage') || 0;

            // Animate progress bar
            setTimeout(() => {
                progressBar.css('width', `${Math.min(targetWidth, 100)}%`);
            }, 500);

            // Animate counters
            this.animateCounters();
        }

        animateCounters() {
            this.element.find('.helpme-progress-stat .number').each((index, counter) => {
                const $counter = $(counter);
                const target = parseFloat($counter.data('target')) || 0;
                const duration = 2000;
                const increment = target / (duration / 16);
                let current = 0;

                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }

                    $counter.text(this.formatNumber(current));
                }, 16);
            });
        }

        formatNumber(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return Math.round(num).toLocaleString();
        }
    }

    // Initialize components when DOM is ready
    $(document).ready(function () {
        // Initialize donation forms
        $('.helpme-donation-form').each(function () {
            new HelpMeDonationForm(this);
        });

        // Initialize campaign progress bars
        $('.helpme-campaign-progress').each(function () {
            new CampaignProgress(this);
        });

        // Handle donation amount quick actions
        $(document).on('click', '.helpme-quick-donate', function (e) {
            e.preventDefault();

            const amount = $(this).data('amount');
            const campaignId = $(this).data('campaign-id');

            // Find or create donation form for this campaign
            let form = $(`.helpme-donation-form[data-campaign-id="${campaignId}"]`);

            if (form.length) {
                // Pre-fill amount and show form
                form.find('.helpme-donation-amount').val(amount);
                form.find(`.helpme-amount-button[data-amount="${amount}"]`).click();

                // Scroll to form
                $('html, body').animate({
                    scrollTop: form.offset().top - 50
                }, 500);
            }
        });

        // Handle responsive navigation
        const handleResponsiveNavigation = () => {
            const formNavigation = $('.helpme-form-navigation');

            if ($(window).width() <= 768) {
                formNavigation.addClass('mobile');
            } else {
                formNavigation.removeClass('mobile');
            }
        };

        handleResponsiveNavigation();
        $(window).resize(handleResponsiveNavigation);

        // Handle accessibility improvements
        $('.helpme-amount-button, .helpme-interval-option').attr('role', 'button');
        $('.helpme-payment-header').attr('role', 'button');

        // Keyboard navigation
        $(document).on('keydown', '.helpme-amount-button, .helpme-interval-option, .helpme-payment-header', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).click();
            }
        });
    });

    // Global utility functions
    window.HelpMeDonations = {
        formatCurrency: function (amount, currency) {
            const symbols = {
                'USD': '$',
                'ZIG': 'ZiG',
                'EUR': '€',
                'GBP': '£',
                'ZAR': 'R'
            };

            const symbol = symbols[currency] || currency;
            return `${symbol}${parseFloat(amount).toLocaleString()}`;
        },

        showNotification: function (message, type = 'info') {
            const notification = $(`
                <div class="helpme-notification helpme-notification-${type}">
                    <div class="helpme-notification-content">
                        <span class="helpme-notification-message">${message}</span>
                        <button class="helpme-notification-close">&times;</button>
                    </div>
                </div>
            `);

            $('body').append(notification);

            // Auto-remove after delay
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, 5000);

            // Manual close
            notification.on('click', '.helpme-notification-close', function () {
                notification.fadeOut(() => notification.remove());
            });
        }
    };

})(jQuery); 