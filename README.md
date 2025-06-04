# Help Me Donations Plugin

A comprehensive WordPress donation plugin supporting Zimbabwean and international payment methods with multi-currency capabilities.

## Features

### Payment Gateways
- **Stripe** - International credit/debit cards
- **PayPal** - Global payment processing
- **Paynow** - Zimbabwe's leading payment gateway
- **InBucks** - Mobile wallet payments
- **ZimSwitch** - Banking network integration

### Multi-Currency Support
- USD (US Dollar)
- ZIG (Zimbabwean Gold)
- EUR (Euro)
- GBP (British Pound)
- ZAR (South African Rand)

### Core Features
- **Campaign Management** - Create and manage fundraising campaigns
- **Donation Forms** - Customizable multi-step donation forms
- **Analytics & Reporting** - Comprehensive donation tracking and analytics
- **Donor Management** - Track and manage donor information
- **Recurring Donations** - Monthly, yearly recurring payment support
- **Exchange Rate Management** - Automatic and manual currency conversion
- **Admin Dashboard** - Complete administrative interface

### Technical Features
- WordPress 5.0+ compatibility
- PHP 7.4+ support
- Responsive design
- AJAX-powered forms
- Webhook support for payment confirmations
- Security-first approach with nonce verification
- Database-driven architecture
- Multilingual ready

## Installation

1. **Download the Plugin**
   ```bash
   git clone https://github.com/Terryo10/helpme.git
   ```

2. **Upload to WordPress**
   - Upload the `zim-donations` folder to `/wp-content/plugins/`
   - Or install via WordPress admin: Plugins → Add New → Upload Plugin

3. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Help Me Donations" and click "Activate"

4. **Configure Settings**
   - Navigate to Donations → Settings
   - Configure your payment gateways
   - Set default currency and amounts

## Quick Start

### Setting Up Payment Gateways

#### Stripe Configuration
1. Go to Donations → Settings → Payment Gateways
2. Enable Stripe and enter your API keys:
   - **Test Mode**: Use test keys for development
   - **Live Mode**: Use live keys for production

#### PayPal Configuration
1. Enable PayPal in settings
2. Enter your PayPal Client ID and Secret
3. Configure webhook URL: `https://yoursite.com/?zim-donations-webhook=1&gateway=paypal`

#### Paynow Configuration
1. Sign up for Paynow merchant account
2. Enter Integration ID and Integration Key
3. Set up webhook notifications

### Creating Your First Campaign

1. Go to Donations → Campaigns
2. Click "Add New"
3. Fill in campaign details:
   - Title and description
   - Goal amount and currency
   - Start/end dates
   - Category

### Adding Donation Forms

Use shortcodes to display donation forms:

```php
// Basic donation form
[zim_donation_form]

// Campaign-specific form
[zim_donation_form campaign_id="1" title="Help Zimbabwe Education"]

// Custom preset amounts
[zim_donation_form amounts="10,25,50,100" currency="USD"]

// Campaign progress display
[zim_campaign_progress campaign_id="1"]

// Recent donations list
[zim_recent_donations limit="5" show_amount="true"]
```

## Configuration

### Payment Gateway Settings

#### Stripe
```php
// Test Mode
zim_donations_stripe_test_publishable_key
zim_donations_stripe_test_secret_key

// Live Mode
zim_donations_stripe_live_publishable_key
zim_donations_stripe_live_secret_key
```

#### PayPal
```php
// Test Mode
zim_donations_paypal_test_client_id
zim_donations_paypal_test_client_secret

// Live Mode
zim_donations_paypal_live_client_id
zim_donations_paypal_live_client_secret
```

#### Paynow
```php
zim_donations_paynow_integration_id
zim_donations_paynow_integration_key
```

### Currency Settings

Set default currency and exchange rates:
```php
zim_donations_default_currency = 'USD'
zim_donations_exchange_api_key = 'your_api_key'
```

## Webhooks

Configure webhooks for payment confirmations:

| Gateway | Webhook URL |
|---------|-------------|
| Stripe | `https://yoursite.com/?zim-donations-webhook=1&gateway=stripe` |
| PayPal | `https://yoursite.com/?zim-donations-webhook=1&gateway=paypal` |
| Paynow | `https://yoursite.com/?zim-donations-webhook=1&gateway=paynow` |
| InBucks | `https://yoursite.com/?zim-donations-webhook=1&gateway=inbucks` |
| ZimSwitch | `https://yoursite.com/?zim-donations-webhook=1&gateway=zimswitch` |

## Database Schema

The plugin creates the following tables:

- `wp_zim_donations` - Main donations table
- `wp_zim_campaigns` - Campaign information
- `wp_zim_donors` - Donor profiles
- `wp_zim_transactions` - Payment transactions
- `wp_zim_forms` - Form configurations

## API Reference

### Actions

```php
// Process donation
do_action('zim_donations_process_donation', $donation_data);

// Campaign created
do_action('zim_donations_campaign_created', $campaign_id);

// Donation completed
do_action('zim_donations_donation_completed', $donation_id);
```

### Filters

```php
// Modify supported currencies
apply_filters('zim_donations_supported_currencies', $currencies);

// Customize form output
apply_filters('zim_donations_form_html', $html, $atts);

// Filter donation data before processing
apply_filters('zim_donations_before_process', $donation_data);
```

### Functions

```php
// Get donation by ID
$donation = zim_donations_get_donation($donation_id);

// Get campaign progress
$progress = zim_donations_get_campaign_progress($campaign_id);

// Format currency
$formatted = zim_donations_format_currency($amount, $currency);
```

## Styling

### CSS Classes

Main form classes:
- `.zim-donation-form` - Main form container
- `.zim-amount-selection` - Amount selection section
- `.zim-amount-button` - Preset amount buttons
- `.zim-payment-methods` - Payment method selection
- `.zim-form-navigation` - Form navigation buttons

Progress display:
- `.zim-campaign-progress` - Progress container
- `.zim-progress-bar` - Progress bar
- `.zim-progress-fill` - Progress fill

### Customization

Override default styles in your theme:

```css
.zim-donation-form {
    /* Custom form styling */
}

.zim-amount-button.selected {
    background: #your-color;
}
```

## Troubleshooting

### Common Issues

1. **Payment Gateway Errors**
   - Check API credentials
   - Verify webhook URLs
   - Enable test mode for debugging

2. **Currency Conversion Issues**
   - Update exchange rates manually
   - Check API key for exchange rate service
   - Verify supported currencies

3. **Form Display Problems**
   - Check shortcode syntax
   - Verify campaign IDs exist
   - Ensure JavaScript is loading

### Debug Mode

Enable debug logging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs in `/wp-content/debug.log`

## Security

### Best Practices

1. **API Keys**: Store securely, never commit to version control
2. **Webhooks**: Always verify signatures
3. **Form Data**: Sanitize and validate all inputs
4. **Database**: Use prepared statements
5. **Nonces**: Verify for all AJAX requests

### Security Features

- CSRF protection with nonces
- Input sanitization and validation
- Webhook signature verification
- SQL injection prevention
- XSS protection

## Performance

### Optimization Tips

1. **Caching**: Use object caching for exchange rates
2. **Database**: Optimize with proper indexing
3. **Assets**: Minify CSS/JS in production
4. **Images**: Optimize campaign images
5. **CDN**: Use CDN for static assets

## Support

### Getting Help

1. **Documentation**: Check this README and inline comments
2. **Issues**: Report bugs on GitHub
3. **Support**: Contact the development team

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 1.0.0
- Initial release
- Multi-gateway payment support
- Campaign management
- Analytics dashboard
- Multi-currency support
- Responsive design

## Credits

Developed by Tapiwa Tererai for the Zimbabwe community.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- cURL PHP extension
- JSON PHP extension
- OpenSSL PHP extension

---

For more information, visit [https://designave.co.za](https://designave.co.za) 