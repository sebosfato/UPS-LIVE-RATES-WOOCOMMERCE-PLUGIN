<?php
/*
Plugin Name: UPS Live Rates
Description: Fetch live shipping rates from UPS.
Version: 1.0
Author: Sebosfato
*/

// Your client credentials and merchant ID
$clientId = "";
$clientSecret = "";
$merchantId = "";

if (!function_exists('WC')) {
    include_once ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';
}

function ups_shipping_init() {
    if (!class_exists('UPS_Shipping_Method')) {
        class UPS_Shipping_Method extends WC_Shipping_Method {

            public function __construct() {
                $this->id                 = 'ups_shipping'; // Unique ID for your shipping method
                $this->instance_id        = absint( $instance_id );
                $this->method_title       = __('UPS Shipping'); // Displayed title in WooCommerce settings
                $this->method_description = __('UPS Live Rates Shipping Method'); // Description shown in WooCommerce settings
                $this->title              = __('Pickup Location', 'text-domain');
                $this->supports           = array(
                    'shipping-zones',
                    'instance-settings',
                    'instance-settings-modal',
                );
                $this->init();
            }

            public function init() {
                // Initialize your settings
                $this->init_form_fields();
                $this->init_instance_settings();

    $this->enabled = $this->get_option('enabled', 'yes') === 'yes'; // Enable this shipping method
$this->title = $this->get_option('title', __('UPS Shipping')); // Displayed title on the checkout page

                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            }

            public function init_instance_settings() {
                // Define your instance settings here
                $this->instance_form_fields = array(
                    'enabled'    => array(
                        'title'   => __('Enable/Disable'),
                        'type'    => 'checkbox',
                        'label'   => __('Enable this shipping method'),
                        'default' => 'yes',
                    ),
                    'title'      => array(
                        'title'       => __('Method Title'),
                        'type'        => 'text',
                        'description' => __('This controls the title which the user sees during checkout.'),
                        'default'     => __('Pickup Location'),
                        'desc_tip'    => true,
                    ),
                    'tax_status' => array(
                        'title'   => __('Tax status', 'woocommerce'),
                        'type'    => 'select',
                        'class'   => 'wc-enhanced-select',
                        'default' => 'taxable',
                        'options' => array(
                            'taxable' => __('Taxable', 'woocommerce'),
                            'none'    => _x('None', 'Tax status', 'woocommerce'),
                        ),
                    ),
                    'cost'       => array(
                        'title'       => __('Cost', 'woocommerce'),
                        'type'        => 'text',
                        'placeholder' => '0',
                        'description' => __('Optional cost for pickup.', 'woocommerce'),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),
                );
            }

            public function calculate_shipping($package = array()) {
                // Calculate shipping costs based on the contents of the cart and the destination
                // Set the cost using $this->add_rate()

                // Obtain UPS API access token
                $accessToken = $this->get_ups_access_token();

                // Make a request to UPS API to get live rates (replace with your logic)
                // For demonstration, using a fixed rate of $10.00
                $ratePayload = $this->generate_rate_request_payload($package);
                error_log('Rate Payload: ' . print_r($ratePayload, true));

                // Send request to UPS API
                $ups_response = $this->send_request_to_ups_api($ratePayload);
                error_log('UPS Response: ' . print_r($ups_response, true));

                // Parse UPS response and extract shipping rates
                $shipping_rates = $this->parse_ups_response($ups_response);
                error_log('Shipping Rates: ' . print_r($shipping_rates, true));

                $this->add_rate($shipping_rates);
            }
            
          public function process_admin_options() {
    // Save settings
    $settings = $this->get_post_data();
    $settings['enabled'] = isset($settings['enabled']) ? 'yes' : 'no'; // Ensure 'enabled' is set to 'yes' or 'no'
    
    error_log('UPS Shipping Settings: ' . print_r($settings, true));

    WC_Admin_Settings::save_fields($settings);

    // Update title after saving
    $this->title = $this->get_option('title', __('Pickup Location'));

    error_log('UPS Shipping Title: ' . $this->title);

    parent::process_admin_options();
}

            private function generate_rate_request_payload($package) {
                $shipping_address = $package['destination'];

                // Construct UPS rate request payload dynamically based on WooCommerce data
                $ratePayload = array(
                    "RateRequest" => array(
                        "Request" => array(
                            "TransactionReference" => array(
                                "CustomerContext" => "WooCommerce Order #" . $package['post_data']['ID']
                            )
                        ),
                        "Shipment" => array(
                            "Shipper" => array(
                                "Name" => "Your Company Name",  // Customize with your company details
                                // ... other shipper details
                            ),
                            "ShipTo" => array(
                                "Name" => $shipping_address['first_name'] . ' ' . $shipping_address['last_name'],
                                // ... other recipient details
                            ),
                            // ... other details
                        )
                    )
                );

                // Convert the payload to JSON
                return json_encode($ratePayload);
            }

            private function parse_ups_response($ups_response) {
                // Implement logic to parse the UPS API response and extract shipping rates
                // ...

                // For demonstration purposes, return a dummy shipping rate array
                return array(
                    'id'       => $this->id,
                    'label'    => $this->title,
                    'cost'     => 10.00, // Adjust this based on your UPS calculations
                    'calc_tax' => 'per_order',
                );
            }

            private function get_ups_access_token() {
                // Prepare payload for "client_credentials" grant type
                $payload = "grant_type=client_credentials";

                // Get access token
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL            => "https://onlinetools.ups.com/security/v1/oauth/token",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST  => "POST",
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => array(
                        "Content-Type: application/x-www-form-urlencoded",
                        "x-merchant-id: $merchantId",
                        "Authorization: Basic " . base64_encode("$clientId:$clientSecret"),
                    ),
                    CURLOPT_SSL_VERIFYPEER => true, // Enable SSL verification
                ]);

                $response = curl_exec($curl);
                $error    = curl_error($curl);
                curl_close($curl);

                // Decode the response to get the access token
                $decodedResponse = json_decode($response, true);

                if (!$decodedResponse || !isset($decodedResponse['access_token'])) {
                    // Handle error (you might want to log or display an error message)
                    return '';
                }

                // Extract the access token
                return $decodedResponse['access_token'];
            }
        }
    }
}

add_action('woocommerce_shipping_init', 'ups_shipping_init'); // use this hook to initialize your new custom method

function add_ups_shipping($methods) {
    $methods['ups_shipping'] = 'UPS_Shipping_Method';
    return $methods;
}
add_filter('woocommerce_shipping_methods', 'add_ups_shipping');
