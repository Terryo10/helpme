jQuery(document).ready(function ($) {
    const form = $('.helpme-donation-form');
    const stepIndicators = form.parent().find('.step');
    const formSteps = form.find('.form-step');
    const prevButton = form.find('.prev-button');
    const nextButton = form.find('.next-button');
    const messagesContainer = form.find('.form-messages');

    let currentStep = 1;
    const totalSteps = 5;

    // Form elements
    const amountButtons = form.find('.amount-button');
    const customAmountInput = form.find('#custom-amount-input');
    const selectedAmountInput = form.find('#selected-amount');
    const recurringCheckbox = form.find('input[name="is_recurring"]');
    const recurringInterval = form.find('.recurring-interval');
    const donorTypeRadios = form.find('input[name="donor_type"]');
    const anonymousInput = form.find('input[name="anonymous"]');
    const donorDetails = form.find('.donor-details');

    // Initialize form
    initializeForm();

    function initializeForm() {
        updateStepDisplay();
        bindEvents();
        updateNavigationButtons();
    }

    function bindEvents() {
        // Amount selection
        amountButtons.on('click', function () {
            amountButtons.removeClass('selected');
            $(this).addClass('selected');
            selectedAmountInput.val($(this).data('amount'));
            customAmountInput.val('');
            validateCurrentStep();
        });

        // Custom amount input
        customAmountInput.on('input', function () {
            amountButtons.removeClass('selected');
            selectedAmountInput.val($(this).val());
            validateCurrentStep();
        });

        // Recurring options
        if (recurringCheckbox.length && recurringInterval.length) {
            recurringCheckbox.on('change', function () {
                recurringInterval.toggle($(this).is(':checked'));
            });
        }

        // Donor type selection
        donorTypeRadios.on('change', function () {
            if ($(this).val() === 'anonymous') {
                anonymousInput.val('1');
                donorDetails.css('opacity', '0.5');
                form.find('#donor-name, #donor-email').prop('required', false);
            } else {
                anonymousInput.val('0');
                donorDetails.css('opacity', '1');
                form.find('#donor-name, #donor-email').prop('required', true);
            }
            validateCurrentStep();
        });

        // Navigation buttons
        prevButton.on('click', function () {
            if (currentStep > 1) {
                currentStep--;
                updateStepDisplay();
                updateNavigationButtons();
            }
        });

        nextButton.on('click', function () {
            if (validateCurrentStep()) {
                if (currentStep < totalSteps) {
                    if (currentStep === 3) {
                        // Before going to step 4, prepare payment processing
                        preparePaymentStep();
                    }
                    currentStep++;
                    updateStepDisplay();
                    updateNavigationButtons();

                    if (currentStep === 4) {
                        // Process payment
                        processPayment();
                    }
                }
            }
        });

        // Form input validation
        form.on('input change', validateCurrentStep);

        // Share and new donation buttons
        form.find('.share-donation').on('click', shareDonation);
        form.find('.new-donation').on('click', resetForm);
    }

    function updateStepDisplay() {
        // Update step indicators
        stepIndicators.each(function (index) {
            const stepNumber = index + 1;
            $(this).removeClass('active completed');

            if (stepNumber < currentStep) {
                $(this).addClass('completed');
            } else if (stepNumber === currentStep) {
                $(this).addClass('active');
            }
        });

        // Update form steps
        formSteps.each(function (index) {
            const stepNumber = index + 1;
            $(this).removeClass('active');

            if (stepNumber === currentStep) {
                $(this).addClass('active');
            }
        });
    }

    function updateNavigationButtons() {
        // Show/hide previous button
        prevButton.toggle(currentStep > 1);

        // Update next button text and visibility
        if (currentStep === totalSteps) {
            nextButton.hide();
        } else {
            nextButton.show();

            switch (currentStep) {
                case 1:
                    nextButton.html('<span>' + helpmeDonations.i18n.continue + '</span>');
                    break;
                case 2:
                    nextButton.html('<span>' + helpmeDonations.i18n.choose_payment + '</span>');
                    break;
                case 3:
                    nextButton.html('<span>' + helpmeDonations.i18n.process_payment + '</span>');
                    break;
                default:
                    nextButton.html('<span>' + helpmeDonations.i18n.continue + '</span>');
            }
        }
    }

    function validateCurrentStep() {
        let isValid = true;
        nextButton.prop('disabled', false);

        switch (currentStep) {
            case 1: // Amount step
                const amount = parseFloat(selectedAmountInput.val());
                isValid = amount > 0;
                break;

            case 2: // Details step
                const donorType = form.find('input[name="donor_type"]:checked').val();
                if (donorType === 'named') {
                    const name = form.find('#donor-name').val().trim();
                    const email = form.find('#donor-email').val().trim();
                    isValid = name.length > 0 && email.length > 0 && isValidEmail(email);
                } else {
                    isValid = true; // Anonymous donations are always valid
                }
                break;

            case 3: // Payment method step
                const selectedGateway = form.find('input[name="payment_gateway"]:checked');
                isValid = selectedGateway.length > 0;
                break;

            default:
                isValid = true;
        }

        nextButton.prop('disabled', !isValid);
        return isValid;
    }

    function preparePaymentStep() {
        // Update summary with form data
        const amount = selectedAmountInput.val();
        const currency = form.find('input[name="currency"]').val();
        const donorType = form.find('input[name="donor_type"]:checked').val();
        const selectedGateway = form.find('input[name="payment_gateway"]:checked');
        const isRecurring = form.find('input[name="is_recurring"]').is(':checked');
        const recurringIntervalSelect = form.find('select[name="recurring_interval"]');

        // Update summary display
        form.find('.summary-amount').text(formatCurrency(amount, currency));

        if (donorType === 'anonymous') {
            form.find('.summary-donor').text(helpmeDonations.i18n.anonymous_donor);
        } else {
            const donorName = form.find('#donor-name').val();
            form.find('.summary-donor').text(donorName);
        }

        if (selectedGateway.length) {
            const gatewayLabel = selectedGateway.closest('.payment-method-option').find('.payment-method-name').text();
            form.find('.summary-gateway').text(gatewayLabel);
        }

        if (isRecurring && recurringIntervalSelect.length) {
            const frequencyText = recurringIntervalSelect.find('option:selected').text();
            form.find('.summary-frequency').text(frequencyText);
            form.find('.recurring-summary').show();
        } else {
            form.find('.recurring-summary').hide();
        }
    }

    function processPayment() {
        // Show loading state
        const paymentContainer = form.find('.payment-form-container');
        paymentContainer.html(`
            <div style="text-align: center; padding: 40px;">
                <div class="loading-spinner"></div>
                <p style="margin-top: 20px; color: #666;">${helpmeDonations.i18n.processing}</p>
            </div>
        `);

        // Prepare form data
        const formData = new FormData(form[0]);
        formData.append('action', 'helpme_process_donation');
        formData.append('nonce', helpmeDonations.nonce);

        // Send AJAX request
        $.ajax({
            url: helpmeDonations.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    // Update completion details
                    form.find('.transaction-id').text(response.data.transaction_id);
                    form.find('.final-amount').text(formatCurrency(response.data.amount, response.data.currency));

                    // Move to completion step
                    currentStep = 5;
                    updateStepDisplay();
                    updateNavigationButtons();

                    showMessage(helpmeDonations.i18n.success, 'success');
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function () {
                showMessage('An error occurred while processing your payment. Please try again.', 'error');
            }
        });
    }

    function formatCurrency(amount, currency) {
        const symbol = helpmeDonations.currency_symbols[currency] || currency;
        return symbol + parseFloat(amount).toLocaleString();
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function showMessage(message, type) {
        messagesContainer.html(`<div class="form-message ${type}">${message}</div>`);
        setTimeout(() => {
            messagesContainer.empty();
        }, 5000);
    }

    function shareDonation() {
        const amount = selectedAmountInput.val();
        const currency = form.find('input[name="currency"]').val();
        const shareText = `I just donated ${formatCurrency(amount, currency)} to help make a difference! Join me in supporting this cause.`;

        if (navigator.share) {
            navigator.share({
                title: document.title,
                text: shareText,
                url: window.location.href
            });
        } else {
            // Fallback to copying to clipboard
            navigator.clipboard.writeText(shareText + ' ' + window.location.href).then(() => {
                showMessage(helpmeDonations.i18n.share_copied, 'success');
            });
        }
    }

    function resetForm() {
        currentStep = 1;
        form[0].reset();
        selectedAmountInput.val('');
        amountButtons.removeClass('selected');
        anonymousInput.val('0');
        donorDetails.css('opacity', '1');
        form.find('#donor-name, #donor-email').prop('required', true);
        updateStepDisplay();
        updateNavigationButtons();
        messagesContainer.empty();
    }

    // Keyboard navigation
    $(document).on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            if (currentStep < totalSteps && !nextButton.prop('disabled')) {
                e.preventDefault();
                nextButton.click();
            }
        } else if (e.key === 'Escape' && currentStep > 1) {
            e.preventDefault();
            prevButton.click();
        }
    });
}); 