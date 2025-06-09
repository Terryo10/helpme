jQuery(document).ready(function ($) {
  const form = $(".helpme-donation-form");
  const stepIndicators = form.parent().find(".step");
  const formSteps = form.find(".form-step");
  const prevButton = form.find(".prev-button");
  const nextButton = form.find(".next-button");
  const messagesContainer = form.find(".form-messages");

  let currentStep = 1;
  const totalSteps = 6; // Updated to 6 steps

  // Form elements
  const amountButtons = form.find(".amount-button");
  const customAmountInput = form.find("#custom-amount-input");
  const selectedAmountInput = form.find("#selected-amount");
  const recurringCheckbox = form.find('input[name="is_recurring"]');
  const recurringInterval = form.find(".recurring-interval");
  const donorTypeRadios = form.find('input[name="donor_type"]');
  const anonymousInput = form.find('input[name="anonymous"]');
  const donorDetails = form.find(".donor-details");
  const paymentMethodRadios = form.find('input[name="payment_gateway"]');

  // Initialize form
  initializeForm();

  function initializeForm() {
    updateStepDisplay();
    bindEvents();
    updateNavigationButtons();
  }

  function bindEvents() {
    // Amount selection
    amountButtons.on("click", function () {
      amountButtons.removeClass("selected");
      $(this).addClass("selected");
      selectedAmountInput.val($(this).data("amount"));
      customAmountInput.val("");
      validateCurrentStep();
    });

    // Custom amount input
    customAmountInput.on("input", function () {
      amountButtons.removeClass("selected");
      selectedAmountInput.val($(this).val());
      validateCurrentStep();
    });

    // Recurring options
    if (recurringCheckbox.length && recurringInterval.length) {
      recurringCheckbox.on("change", function () {
        recurringInterval.toggle($(this).is(":checked"));
      });
    }

    // Donor type selection
    donorTypeRadios.on("change", function () {
      if ($(this).val() === "anonymous") {
        anonymousInput.val("1");
        donorDetails.css("opacity", "0.5");
        form.find("#donor-name, #donor-email").prop("required", false);
      } else {
        anonymousInput.val("0");
        donorDetails.css("opacity", "1");
        form.find("#donor-name, #donor-email").prop("required", true);
      }
      validateCurrentStep();
    });

    // Payment method selection
    paymentMethodRadios.on("change", function () {
      const selectedGateway = $(this).val();
      $(".payment-method-card").removeClass("selected");
      $(this)
        .closest(".payment-method-option")
        .find(".payment-method-card")
        .addClass("selected");
      validateCurrentStep();
    });

    // Navigation buttons
    prevButton.on("click", function () {
      if (currentStep > 1) {
        currentStep--;
        updateStepDisplay();
        updateNavigationButtons();
      }
    });

    nextButton.on("click", function () {
      if (validateCurrentStep()) {
        if (currentStep < totalSteps) {
          // Special handling for step 3 (payment method selection)
          if (currentStep === 3) {
            loadGatewayPaymentForm();
          }
          // Special handling for step 4 (payment details)
          else if (currentStep === 4) {
            preparePaymentProcessing();
          }
          // Special handling for step 5 (payment processing)
          else if (currentStep === 5) {
            processPayment();
            return; // Don't increment step yet
          }

          currentStep++;
          updateStepDisplay();
          updateNavigationButtons();
        }
      }
    });

    // Form input validation
    form.on("input change", validateCurrentStep);

    // Share and new donation buttons
    form.find(".share-donation").on("click", shareDonation);
    form.find(".new-donation").on("click", resetForm);
  }

  function updateStepDisplay() {
    // Update step indicators
    stepIndicators.each(function (index) {
      const stepNumber = index + 1;
      $(this).removeClass("active completed");

      if (stepNumber < currentStep) {
        $(this).addClass("completed");
      } else if (stepNumber === currentStep) {
        $(this).addClass("active");
      }
    });

    // Update form steps
    formSteps.each(function (index) {
      const stepNumber = index + 1;
      $(this).removeClass("active");

      if (stepNumber === currentStep) {
        $(this).addClass("active");
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
          nextButton.html("<span>" + helpmeDonations.i18n.continue + "</span>");
          break;
        case 2:
          nextButton.html(
            "<span>" + helpmeDonations.i18n.choose_payment + "</span>"
          );
          break;
        case 3:
          nextButton.html(
            "<span>" + helpmeDonations.i18n.enter_details + "</span>"
          );
          break;
        case 4:
          nextButton.html(
            "<span>" + helpmeDonations.i18n.process_payment + "</span>"
          );
          break;
        case 5:
          nextButton.html(
            "<span>" + helpmeDonations.i18n.process_payment + "</span>"
          );
          break;
        default:
          nextButton.html("<span>" + helpmeDonations.i18n.continue + "</span>");
      }
    }
  }

  function validateCurrentStep() {
    let isValid = true;
    nextButton.prop("disabled", false);

    switch (currentStep) {
      case 1: // Amount step
        const amount = parseFloat(selectedAmountInput.val());
        isValid = amount > 0;
        break;

      case 2: // Details step
        const donorType = form.find('input[name="donor_type"]:checked').val();
        if (donorType === "named") {
          const name = form.find("#donor-name").val().trim();
          const email = form.find("#donor-email").val().trim();
          isValid = name.length > 0 && email.length > 0 && isValidEmail(email);
        } else {
          isValid = true; // Anonymous donations are always valid
        }
        break;

      case 3: // Payment method step
        const selectedGateway = form.find(
          'input[name="payment_gateway"]:checked'
        );
        isValid = selectedGateway.length > 0;
        break;

      case 4: // Payment details step
        // Validation depends on the selected gateway
        isValid = validateGatewayForm();
        break;

      default:
        isValid = true;
    }

    nextButton.prop("disabled", !isValid);
    return isValid;
  }

  function loadGatewayPaymentForm() {
    const selectedGateway = form
      .find('input[name="payment_gateway"]:checked')
      .val();
    const amount = selectedAmountInput.val();
    const currency = form.find('input[name="currency"]').val();
    const donorName = form.find("#donor-name").val();
    const donorEmail = form.find("#donor-email").val();
    const campaignId = form.data("campaign-id");

    if (!selectedGateway) {
      showMessage(helpmeDonations.i18n.payment_form_error, "error");
      return;
    }

    const container = $("#gateway-payment-container");

    // Show loading state
    container.html(`
            <div class="loading-message">
                <div class="loading-spinner"></div>
                <p>${helpmeDonations.i18n.loading_payment_form}</p>
            </div>
        `);

    // AJAX request to get gateway-specific form
    $.ajax({
      url: helpmeDonations.ajaxurl,
      type: "POST",
      data: {
        action: "get_gateway_payment_form",
        nonce: helpmeDonations.nonce,
        gateway_id: selectedGateway,
        amount: amount,
        currency: currency,
        donor_name: donorName,
        donor_email: donorEmail,
        campaign_id: campaignId,
        donation_id: generateDonationId(),
      },
      success: function (response) {
        if (response.success) {
          container.html(response.data.form_html);

          // Initialize gateway-specific functionality
          initializeGatewayForm(selectedGateway);
        } else {
          container.html(`
                        <div class="error-message">
                            <p>${
                              response.data.message ||
                              helpmeDonations.i18n.payment_form_error
                            }</p>
                        </div>
                    `);
        }
      },
      error: function () {
        container.html(`
                    <div class="error-message">
                        <p>${helpmeDonations.i18n.payment_form_error}</p>
                    </div>
                `);
      },
    });
  }

  function initializeGatewayForm(gatewayId) {
    switch (gatewayId) {
      case "stripe":
        initializeStripeForm();
        break;
      case "paypal":
        initializePayPalForm();
        break;
      case "paynow":
        initializePaynowForm();
        break;
      case "inbucks":
        initializeInBucksForm();
        break;
      case "zimswitch":
        initializeZimSwitchForm();
        break;
    }

    // Re-validate the current step
    validateCurrentStep();
  }

  function initializeStripeForm() {
    if (
      typeof Stripe !== "undefined" &&
      helpmeDonations.stripe_publishable_key
    ) {
      const stripe = Stripe(helpmeDonations.stripe_publishable_key);
      const elements = stripe.elements();

      const cardElement = elements.create("card", {
        style: {
          base: {
            fontSize: "16px",
            color: "#424770",
            "::placeholder": {
              color: "#aab7c4",
            },
          },
        },
      });

      cardElement.mount("#stripe-card-element");

      cardElement.on("change", function (event) {
        const displayError = document.getElementById("stripe-card-errors");
        if (event.error) {
          displayError.textContent = event.error.message;
          displayError.style.display = "block";
        } else {
          displayError.style.display = "none";
        }
        validateCurrentStep();
      });

      // Store stripe instance for later use
      window.stripeInstance = { stripe, cardElement };
    }
  }

  function initializePayPalForm() {
    // PayPal initialization is handled in the returned HTML
    console.log("PayPal form initialized");
  }

  function initializePaynowForm() {
    // Add phone number formatting
    $("#paynow-phone").on("input", function () {
      let value = this.value.replace(/\D/g, "");
      if (value.length > 9) {
        value = value.substring(0, 9);
      }
      this.value = value;
      validateCurrentStep();
    });
  }

  function initializeInBucksForm() {
    // Add phone number formatting
    $("#inbucks-phone").on("input", function () {
      let value = this.value.replace(/\D/g, "");
      if (value.length > 9) {
        value = value.substring(0, 9);
      }
      this.value = value;
      validateCurrentStep();
    });
  }

  function initializeZimSwitchForm() {
    // Bank selection validation
    $("#zimswitch-bank").on("change", function () {
      validateCurrentStep();
    });
  }

  function validateGatewayForm() {
    const selectedGateway = form
      .find('input[name="payment_gateway"]:checked')
      .val();

    switch (selectedGateway) {
      case "stripe":
        return validateStripeForm();
      case "paynow":
        return validatePaynowForm();
      case "inbucks":
        return validateInBucksForm();
      case "zimswitch":
        return validateZimSwitchForm();
      case "paypal":
        return true; // PayPal validation happens during payment
      default:
        return true;
    }
  }

  function validateStripeForm() {
    // Basic validation - full validation happens when card element changes
    return $("#stripe-card-element").length > 0;
  }

  function validatePaynowForm() {
    const phone = $("#paynow-phone").val();
    const method = $('input[name="paynow_method"]:checked').val();
    return phone && phone.length === 9 && method;
  }

  function validateInBucksForm() {
    const phone = $("#inbucks-phone").val();
    return phone && phone.length === 9;
  }

  function validateZimSwitchForm() {
    const bank = $("#zimswitch-bank").val();
    return bank && bank.length > 0;
  }

  function preparePaymentProcessing() {
    // Update summary with form data
    const amount = selectedAmountInput.val();
    const currency = form.find('input[name="currency"]').val();
    const donorType = form.find('input[name="donor_type"]:checked').val();
    const selectedGateway = form.find('input[name="payment_gateway"]:checked');
    const isRecurring = form.find('input[name="is_recurring"]').is(":checked");
    const recurringIntervalSelect = form.find(
      'select[name="recurring_interval"]'
    );

    // Update summary display
    form.find(".summary-amount").text(formatCurrency(amount, currency));

    if (donorType === "anonymous") {
      form.find(".summary-donor").text(helpmeDonations.i18n.anonymous_donor);
    } else {
      const donorName = form.find("#donor-name").val();
      form.find(".summary-donor").text(donorName);
    }

    if (selectedGateway.length) {
      const gatewayLabel = selectedGateway
        .closest(".payment-method-option")
        .find(".payment-method-name")
        .text();
      form.find(".summary-gateway").text(gatewayLabel);
    }

    if (isRecurring && recurringIntervalSelect.length) {
      const frequencyText = recurringIntervalSelect
        .find("option:selected")
        .text();
      form.find(".summary-frequency").text(frequencyText);
      form.find(".recurring-summary").show();
    } else {
      form.find(".recurring-summary").hide();
    }
  }

  function processPayment() {
    const selectedGateway = form
      .find('input[name="payment_gateway"]:checked')
      .val();

    // Show loading state
    const paymentContainer = form.find(".payment-form-container");
    paymentContainer.html(`
            <div style="text-align: center; padding: 40px;">
                <div class="loading-spinner"></div>
                <p style="margin-top: 20px; color: #666;">${helpmeDonations.i18n.processing}</p>
            </div>
        `);

    // Process based on gateway type
    switch (selectedGateway) {
      case "stripe":
        processStripePayment();
        break;
      case "paypal":
        processPayPalPayment();
        break;
      case "paynow":
        processPaynowPayment();
        break;
      case "inbucks":
        processInBucksPayment();
        break;
      case "zimswitch":
        processZimSwitchPayment();
        break;
      default:
        processDefaultPayment();
    }
  }

  function processStripePayment() {
    if (!window.stripeInstance) {
      showMessage("Stripe not properly initialized", "error");
      return;
    }

    const { stripe, cardElement } = window.stripeInstance;
    const formData = getFormData();

    // Create payment intent first
    $.ajax({
      url: helpmeDonations.ajaxurl,
      type: "POST",
      data: {
        action: "helpme_process_donation",
        nonce: helpmeDonations.nonce,
        gateway: "stripe",
        ...formData,
      },
      success: function (response) {
        if (response.success && response.data.client_secret) {
          // Confirm payment with Stripe
          stripe
            .confirmCardPayment(response.data.client_secret, {
              payment_method: {
                card: cardElement,
                billing_details: {
                  name: formData.donor_name,
                  email: formData.donor_email,
                },
              },
            })
            .then(function (result) {
              if (result.error) {
                showMessage(result.error.message, "error");
              } else {
                paymentCompleted(result.paymentIntent);
              }
            });
        } else {
          showMessage(
            response.data.message || "Payment processing failed",
            "error"
          );
        }
      },
      error: function () {
        showMessage("An error occurred while processing your payment", "error");
      },
    });
  }

  function processPayPalPayment() {
    // PayPal processing is handled by PayPal buttons
    showMessage(
      "Please use the PayPal button above to complete your payment",
      "info"
    );
  }

  function processPaynowPayment() {
    const phone = $("#paynow-phone").val();
    const form = $(".helpme-donation-form");
    const selectedAmountInput = form.find("#selected-amount");

    const amount = selectedAmountInput;
    const method = $('input[name="paynow_method"]:checked').val();
    const formData = getFormData();
    formData.phone = phone;
    formData.method = method;

    // alert(formData.amount);

    processGenericPayment("paynow", formData);
  }

  function processInBucksPayment() {
    const phone = $("#inbucks-phone").val();
    const formData = getFormData();
    formData.phone = phone;

    processGenericPayment("inbucks", formData);
  }

  function processZimSwitchPayment() {
    const bankCode = $("#zimswitch-bank").val();
    const formData = getFormData();
    formData.bank_code = bankCode;

    processGenericPayment("zimswitch", formData);
  }

  function processDefaultPayment() {
    const formData = getFormData();
    processGenericPayment("default", formData);
  }

  function processGenericPayment(gateway, formData) {
    addFormLoader();

    $.ajax({
      url: helpmeDonations.ajaxurl,
      type: "POST",
      data: {
        action: "helpme_submit_paynow_donation",
        nonce: helpmeDonations.nonce,
        gateway: gateway,
        ...formData,
      },
      success: function (response) {
        removeFormLoader();
        if (response.success) {
          if (response.data.redirect_url) {
            // Redirect to payment page
            window.location.href = response.data.redirect_url;
          } else {
            paymentCompleted(response.data);
          }
        } else if (response.data?.poll_url) {
          showMessage(
            response.data.message || "Payment processing failed",

            "error",
            response?.data?.poll_url
          );
        } else {
          showMessage(
            response.data.message || "Payment processing failed",
            "error"
          );
        }
      },
      error: function () {
        removeFormLoader();
        showMessage("An error occurred while processing your payment", "error");
      },
    });
  }

  function paymentCompleted(paymentData) {
    // Update completion details
    form.find(".transaction-id").text(paymentData.transaction_id || "N/A");
    form
      .find(".final-amount")
      .text(
        formatCurrency(
          selectedAmountInput.val(),
          form.find('input[name="currency"]').val()
        )
      );

    // Move to completion step
    currentStep = 6;
    updateStepDisplay();
    updateNavigationButtons();

    showMessage(helpmeDonations.i18n.success, "success");
  }

  function getFormData() {
    return {
      amount: selectedAmountInput.val(),
      currency: form.find('input[name="currency"]').val(),
      donor_name: form.find("#donor-name").val(),
      donor_email: form.find("#donor-email").val(),
      donor_phone: form.find("#donor-phone").val(),
      donor_message: form.find("#donor-message").val(),
      campaign_id: form.data("campaign-id"),
      form_id: form.data("form-id"),
      is_recurring: form.find('input[name="is_recurring"]').is(":checked"),
      recurring_interval: form.find('select[name="recurring_interval"]').val(),
      anonymous: form.find('input[name="anonymous"]').val(),
      donation_id: generateDonationId(),
    };
  }

  function generateDonationId() {
    return (
      "donation_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9)
    );
  }

  function formatCurrency(amount, currency) {
    const symbol = helpmeDonations.currency_symbols[currency] || currency;
    return symbol + parseFloat(amount).toLocaleString();
  }

  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  function showMessage(message, type, poll_url) {
    let repayButton = ``;

    if (poll_url) {
      repayButton += `<button data-pollurl="${poll_url}" class="btn btn-success check-paynow-status gateway-pay-button" data-type="${type}">Recheck Your payment details</button> `;
    }
    messagesContainer.html(
      `<div class="form-message ${type}">${message} ${repayButton}</div>`
    );

    $(document).on("click", ".check-paynow-status", function () {
      addFormLoader();

      const pollUrl = $(this).data("pollurl");

      if (!pollUrl) return;

      $.ajax({
        url: helpmeDonations.ajaxurl,
        type: "POST",
        data: {
          action: "check_paynow_payment_status",
          poll_url: pollUrl,
          nonce: helpmeDonations.nonce,
        },
        success: function (response) {
          removeFormLoader();
          if (response.success) {
            paymentCompleted(response.data);
          } else {
            showMessage(
              response.data.message || "Payment not completed",
              "error",
              pollUrl
            );
          }
        },
        error: function () {
          removeFormLoader();
          showMessage(
            "An error occurred while checking payment status",
            "error"
          );
        },
      });
    });
  }

  function shareDonation() {
    const amount = selectedAmountInput.val();
    const currency = form.find('input[name="currency"]').val();
    const shareText = `I just donated ${formatCurrency(
      amount,
      currency
    )} to help make a difference! Join me in supporting this cause.`;

    if (navigator.share) {
      navigator.share({
        title: document.title,
        text: shareText,
        url: window.location.href,
      });
    } else {
      // Fallback to copying to clipboard
      navigator.clipboard
        .writeText(shareText + " " + window.location.href)
        .then(() => {
          showMessage(helpmeDonations.i18n.share_copied, "success");
        });
    }
  }

  function resetForm() {
    currentStep = 1;
    form[0].reset();
    selectedAmountInput.val("");
    amountButtons.removeClass("selected");
    anonymousInput.val("0");
    donorDetails.css("opacity", "1");
    form.find("#donor-name, #donor-email").prop("required", true);
    $("#gateway-payment-container").html(
      '<div class="payment-form-placeholder"><p>Please select a payment method to continue.</p></div>'
    );
    updateStepDisplay();
    updateNavigationButtons();
    messagesContainer.empty();
  }

  function addFormLoader() {
    const paymentContainer = form.find(".payment-form-container");
    paymentContainer.html(`
            <div style="text-align: center; padding: 40px;">
                <div class="loading-spinner"></div>
                <p style="margin-top: 20px; color: #666;">${helpmeDonations.i18n.processing}</p>
            </div>
        `);
  }
  function removeFormLoader() {
    const paymentContainer = form.find(".payment-form-container");
    paymentContainer.html("");
  }

  // Keyboard navigation
  $(document).on("keydown", function (e) {
    if (e.key === "Enter" && !e.shiftKey) {
      if (currentStep < totalSteps && !nextButton.prop("disabled")) {
        e.preventDefault();
        nextButton.click();
      }
    } else if (e.key === "Escape" && currentStep > 1) {
      e.preventDefault();
      prevButton.click();
    }
  });
});
