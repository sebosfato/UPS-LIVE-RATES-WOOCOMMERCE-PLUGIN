<?php

/**
 * Requires libcurl
 */
 
  define('version', 'v1');

if (!class_exists('WC')) {
    include_once ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';
}
        class UPS_Shipping_Method extends WC_Shipping_Method {
            public function __construct( $instance_id = 0 ){
                      // These title description are displayed on the configuration page
                $this->id                 = 'ups_shipping'; // Unique ID for your shipping method
                $this->method_title       =  __('UPS', 'ups-shipping'); // Displayed title in WooCommerce settings
                $this->method_description =  esc_html__('UPS Live Rates Shipping Method', 'ups-shipping'); // Description shown in WooCommerce settings
                $this->title              = __('', 'ups-shipping');// titulo que aparece na lista dropdown de metodos de entrega disponiveis
                $this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled
                $this->instance_id        = absint( $instance_id );
                $this->supports           = array(
                    'shipping-zones',
                    'instance-settings',
                    'instance-settings-modal',
                );
                      // Run the initial method
                $this->init();
            }
            public function init() {
                      // Load the settings API
                 $this->init_settings();
                // Initialize your settings
                $this->init_form_fields();
     
                 // reaplica se existe nome dinamico escolhido pelo usuario ou fica defaut
                $this->title = $this->get_option('title', __('UPS', 'ups-shipping')); 
               // $this->method_title = $this->get_option('method_title', __('Standard Shipping', 'ups-shipping')); 

             //   $this->method_description = $this->get_option('method_description', __('Select a Method', 'ups-shipping')); 

                add_filter('woocommerce_shipping_' . $this->id . '_settings', array($this, 'process_admin_options'));
      add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            }
            public function init_form_fields() {
                // Define your instance settings here
             $this->instance_form_fields = array(
                      
                    'title' => array(
                        'title'       => __('Title', 'ups-shipping'),
                        'type'        => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'ups-shipping'),
                        'default'     => __('Ups', 'ups-shipping'),
                        'desc_tip'    => true,
                    ),
                    'enabled' => array(
                        'title'   => __('Enable/Disable', 'ups-shipping'),
                        'type'    => 'checkbox',
                        'label'   => __('Enable this shipping method', 'ups-shipping'),
                        'default' => 'yes',
                    ),
                     'clientId' => array(
                        'title'   => __('Client Id', 'ups-shipping'),
                        'type'    => 'text',
                        'description' => __('Enter the client Id from Ups.'),
                        'desc_tip'    => true,
                    ),
                        'clientSecret' => array(
                        'title'   => __('Client Secret', 'ups-shipping'),
                        'type'    => 'text',
                        'description' => __('Enter the client secret from Ups.'),
                        'desc_tip'    => true,
                    ),
                    'merchantId' => array(
                        'title'   => __('Merchant Id', 'ups-shipping'),
                        'type'    => 'text',
                        'description' => __('Enter the mechant if from Ups.'),
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
                             $this->form_fields = $this->get_instance_form_fields();
            }

private function get_ups_access_token() {
    // Retrieve client ID and secret from settings
    $client_id     = $this->get_option('clientId');
    $client_secret = $this->get_option('clientSecret');
    $merchant_id   = $this->get_option('merchantId'); // Make sure to define this in your settings

    // Prepare payload for "client_credentials" grant type
    $payload = "grant_type=client_credentials";

    // Get access token
    $curl = curl_init();
    curl_setopt_array($curl, [
  CURLOPT_URL => "https://onlinetools.ups.com/security/v1/oauth/token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => "POST",
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => array(
            "Content-Type: application/x-www-form-urlencoded",
            "x-merchant-id: $merchant_id",
            "Authorization: Basic " . base64_encode("$client_id:$client_secret"),
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
        //echo notoken;
        return '';
    }

    // Extract the access token
    return $decodedResponse['access_token'];
    
}

    // Function to calculate shipping rates
    public function calculate_shipping($package = array()) {
        // Retrieve the UPS access token
        $access_token = $this->get_ups_access_token();
        // Check if access token is obtained successfully
        if ($access_token) {
            
            
            $curl = curl_init();

            // Define your API request payl oad and URL
$rate_payload = array(
  "ShipmentRequest" => array(
    "Request" => array(
      "RequestOption" => "nonvalidate",
      "TransactionReference" => array(
        "CustomerContext" => ""
      )
    ),
    "Shipment" => array(
      "Description" => "Description of Goods",
      "Shipper" => array(
        "Name" => "Henry Lee Thomson",
        "AttentionName" => "John Smith",
        "ShipperNumber" => "zzzzzzz",
        "TaxIdentificationNumber" => "456789",
        "Phone" => array(
          "Number" => "1234567890"
        ),
        "Address" => array(
          "AddressLine" => array(
            "34 Queen St"
          ),
          "City" => "Toronto",
          "StateProvinceCode" => "ON",
          "PostalCode" => "M5C2M6",
          "CountryCode" => "BR"
        )
      ),
      "ShipTo" => array(
        "Name" => "Happy Dog Pet Supply",
        "AttentionName" => "Marley Brinson",
        "TaxIdentificationNumber" => "458889",
        "Phone" => array(
          "Number" => "1234567890"
        ),
        "Address" => array(
          "AddressLine" => array(
            "B.B. King Blvd."
          ),
          "City" => "Charlotte",
          "StateProvinceCode" => "NC",
          "PostalCode" => "28256",
          "CountryCode" => "US"
        )
      ),
      "ShipFrom" => array(
        "Name" => "T and T Designs",
        "AttentionName" => "Mike",
        "Phone" => array(
          "Number" => "1234567890"
        ),
        "FaxNumber" => "1234567999",
        "TaxIdentificationNumber" => "456999",
        "Address" => array(
          "AddressLine" => array(
            "34 Queen St"
          ),
          "City" => "Toronto",
          "StateProvinceCode" => "ON",
          "PostalCode" => "M5C2M6",
          "CountryCode" => "BR"
        )
      ),
      "PaymentInformation" => array(
        "ShipmentCharge" => array(
          "Type" => "01",
          "BillShipper" => array(
            "AccountNumber" => "zzzzzzzz"
          )
        )
      ),
      "Service" => array(
        "Code" => "08",
        "Description" => "Expedited"
      ),
      "Package" => array(
        "Description" => "International Goods",
        "Packaging" => array(
          "Code" => "02"
        ),
        "PackageWeight" => array(
          "UnitOfMeasurement" => array(
            "Code" => "KGS"
          ),
          "Weight" => "10"
        )
      ),
      "ShipmentServiceOptions" => array(
        "InternationalForms" => array(
          "FormType" => "04",
          "FormGroupIdName" => "NAFTA Form",
          "Contacts" => array(
            "SoldTo" => array(
              "Option" => " ",
              "Name" => "ACME Designs",
              "AttentionName" => "Wile E Coyote",
              "TaxIdentificationNumber" => " ",
              "Phone" => array(
                "Number" => "5551479876"
              ),
              "Address" => array(
                "AddressLine" => array(
                  "123 Main St"
                ),
                "City" => "Phoenix",
                "StateProvinceCode" => "GA",
                "PostalCode" => "30076",
                "CountryCode" => "US"
              )
            ),
            "Producer" => array(
              "Option" => " ",
              "CompanyName" => "Tree Service",
              "Address" => array(
                "AddressLine" => array(
                  "678 Elm St"
                ),
                "City" => "Marietta",
                "StateProvinceCode" => "GA",
                "PostalCode" => "30066",
                "CountryCode" => "US"
              ),
              "Phone" => array(
                "Number" => "5555555555"
              ),
              "EmailAddress" => " ",
              "TaxIdentificationNumber" => " "
            )
          ),
          "Product" => array(
            "Description" => "Today is the best day of the week",
            "CommodityCode" => "12345678",
            "OriginCountryCode" => "US",
            "JointProductionIndicator" => " ",
            "NetCostCode" => "NC",
            "NetCostDateRange" => array(
              "BeginDate" => "20050801",
              "EndDate" => "20051015"
            ),
            "PreferenceCriteria" => "A",
            "ProducerInfo" => "No[1]"
          ),
          "BlanketPeriod" => array(
            "BeginDate" => "20050115",
            "EndDate" => "20050816"
          )
        )
      )
    ),
    "LabelSpecification" => array(
      "LabelImageFormat" => array(
        "Code" => "GIF"
      )
    )
  )
);

         //   $ship_to = $package['destination'];
    //    $ship_from = $package['origin'];
      //  $total_weight = $package['weight'];
            //update rate pay load
       //     $rate_payload = $this->prepare_shipment_payload($shipping_from_address, $ship_to, $total_weight);


$query = array(
  "additionaladdressvalidation" => "Fabio-Torturella"
);          
            //$version = "v1";
           $url = "https://onlinetools.ups.com/api/shipments/" . $version . "/ship?" . http_build_query($query);
        // $url = "https://wwwcie.ups.com/api/shipments/v1/ship?" . http_build_query($query);
            // Make API request using cURL
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_HTTPHEADER     => array(
                    "Authorization: Bearer $access_token",
                    "Content-Type: application/json",
                    "transId: string",
                    "transactionSrc: testing"
                ),
                CURLOPT_POSTFIELDS     => json_encode($rate_payload),
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => "POST",
            ]);

            $response = curl_exec($curl);
            $error    = curl_error($curl);
            curl_close($curl);

            // Check for errors
            if ($error) {
                echo "cURL Error #:" . $error;
            } else {
                // Decode the JSON response
                $responseData = json_decode($response, true);

                // Check if the 'ShipmentResponse' key exists
                if (isset($responseData['ShipmentResponse'])) {
                    // Extract and display the total charges information
                    $totalCharges = $responseData['ShipmentResponse']['ShipmentResults']['ShipmentCharges']['TotalCharges']['MonetaryValue'];
                    $currencyCode = $responseData['ShipmentResponse']['ShipmentResults']['ShipmentCharges']['TotalCharges']['CurrencyCode'];
                    //echo "Total Charges: " . $currencyCode . " " . number_format($totalCharges, 2); // Adjust the formatting as needed
          $this->add_rate( array(
         'id'     => $this->id,
         'label'  => $this->settings['title'],
         'cost'   => $totalCharges
      ));
                
                    // You can then use $totalCharges and $currencyCode in your cart display logic
                } else {
                    echo "ShipmentResponse key not found in the response";
                      $this->add_rate( array(
         'id'     => $this->id,
         'label'  => $this->settings['title'],
         'cost'   => $this->settings['cost']
      ));
                    
                }
            }
        } else {
            // Handle the case where access token retrieval fails
            echo "Failed to retrieve UPS access token.";
        }
    }
    
            
        }
