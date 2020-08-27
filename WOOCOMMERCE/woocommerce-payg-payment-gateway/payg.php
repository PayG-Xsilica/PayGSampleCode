<?php 
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
ini_set('display_errors',0);
$pg_base_dir = dirname(__FILE__);
require_once($pg_base_dir . "/includes/payg_config.php"); 


/*
  Plugin Name: WooCommerce Payg
  Description: Extends WooCommerce with  Payment gateway. AJAX Version supported.
  Version: 1.0.1
  Requires at least: 4.0
  Tested up to: 4.9.4
  WC requires at least: 3.0.0
  WC tested up to: 3.2.6
  Author: Induco CIAL
  Author URI: https://www.inducocial.com/
 */

add_action('plugins_loaded', 'woocommerce_payg_init', 0);

if (!function_exists('woocommerce_payg_init')) {

   
    function woocommerce_payg_init() {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        /**
         * Localisation
         */
        load_plugin_textdomain('wc-payg', false, dirname(plugin_basename(__FILE__)) . '/languages');

        if (isset($_GET['msg']) && $_GET['msg'] != '') {
            add_action('the_content', 'showMessage');
        }

        if (!function_exists('showMessage')) {

            function showMessage($content) {
                return '<div class="box ' . htmlentities($_GET['type']) . '-box">' . htmlentities(urldecode($_GET['msg'])) . '</div>' . $content;
            }

        }

        /**
         * Gateway class
         */
        class WC_PayG extends WC_Payment_Gateway {
            
            protected $msg = array();

            public function __construct() {
                global $payg_title; global $payg_lowercase_title; global $payg_img;  
                // Go wild in here
                $this->supports           = array(
                    'products',
                    'refunds',
                    'pre-orders'
                );
                $this->id = $payg_lowercase_title;
                $this->method_title = __($payg_title, $payg_lowercase_title);
                $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/'.$payg_img;
                $this->has_fields = false;
                $this->init_form_fields();
                $this->init_settings();
                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];
                $this->merchant_id = $this->settings['merchant_id'];
                $this->secure_key = $this->settings['secure_key'];
                $this->authentication_key = $this->settings['authentication_key']; 
                $this->authentication_token= $this->settings['authentication_token']; 
                $this->url = $this->settings['url'];
                 
                if (isset($this->settings['redirect_page_id'])) {
                    $this->redirect_page_id = $this->settings['redirect_page_id'];
                } else {
                    $this->redirect_page_id = '';
                }

                $this->msg['message'] = "";
                $this->msg['class'] = "";
                add_action('init', array(&$this, 'check_payg_response'));
                //update for woocommerce >2.0
                add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_payg_response'));

                add_action('valid-payg-request', array(&$this, 'SUCCESS'));
                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
                }
                add_action('woocommerce_receipt_'.$payg_lowercase_title, array(&$this, 'receipt_page')); 
            }

            function init_form_fields() {
                global $payg_title; global $payg_lowercase_title; ;
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', $payg_lowercase_title),
                        'type' => 'checkbox',
                        'label' => __('Enable   '.$payg_title  .' Payment Module.', $payg_lowercase_title),
                        'default' => 'no'),
                    'title' => array(
                        'title' => __('Title:', $payg_lowercase_title),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', $payg_lowercase_title),
                        'default' => __($payg_title, $payg_lowercase_title)),
                    'description' => array(
                        'title' => __('Description:', $payg_lowercase_title),
                        'type' => 'textarea',
                        'description' => __('This controls the description which the user sees during checkout.', $payg_lowercase_title),
                        'default' => __('Pay securely by Credit or Debit card or net banking through '. $payg_title.' Secure Servers.', $payg_lowercase_title)),
                    'merchant_id' => array(
                        'title' => __('Merchant ID', $payg_lowercase_title),
                        'type' => 'text',
                        'description' => __('Merchant ID.', $payg_lowercase_title)
                    ),
                    'secure_key' => array(
                        'title' => __('Merchant Secure Key', $payg_lowercase_title),
                        'type' => 'text',
                        'description' => __('Merchant Secure Key.', $payg_lowercase_title)
                    ),
                    'authentication_key' => array(
                        'title' => __('Authentication Key', $payg_lowercase_title),
                        'type' => 'text',
                        'description' => __('Authentication Key.', $payg_lowercase_title)
                    ),
                    'authentication_token' => array(
                        'title' => __('Authentication Token', $payg_lowercase_title),
                        'type' => 'text',
                        'description' => __('Authentication Token.', $payg_lowercase_title)
                    ),
                    'url' => array(
                        'title' => __('URL', $payg_lowercase_title),
                        'type' => 'text',
                        'description' => __('URL.', $payg_lowercase_title)
                    ),
                );
            }

            /**
             * Admin Panel Options
             * - Options for bits like 'title' and availability on a country-by-country basis
             * */
            public function admin_options() {
                global $payg_title; global $payg_lowercase_title; ;
                echo '<h3>' . __($payg_title.' Payment Gateway', $payg_lowercase_title) . '</h3>';
                echo '<p>' . __($payg_title.' is most popular payment gateway for online shopping in India') . '</p>';
                echo '<table class="form-table">';
                $this->generate_settings_html();
                echo '</table>';
            }

            /**
             *  There are no payment fields for PayG, but we want to show the description if set.
             * */
            function payment_fields() {
                global $payg_title; global $payg_lowercase_title; ;
                if ($this->description) {
                    echo wpautop(wptexturize($this->description));
                }
            }

            /**
             * Receipt Page
             * */
            function receipt_page($order) {
                global $payg_title; global $payg_lowercase_title; ;
                echo '<p>' . __('Thank you for your order, please click the button below to pay with'.$payg_title.'.', $payg_lowercase_title) . '</p>';
                echo $this->generate_payg_form($order);
            }

            
            /**
             * Process the payment and return the result
             * */
            function process_payment($order_id) {
                $order = new WC_Order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg(
                            'order', $order->get_id(), add_query_arg(
                                    'key', $order->get_order_key(), ''
                                    //$order->get_checkout_payment_url() 
                                    //get_permalink(wc_get_page_id('pay'))
                            )
                    )
                );
            }
            /**
             * Check for valid PayG server callback
             * */
            function check_PayG_response() {
                global $payg_title; global $payg_lowercase_title; ;
                global $woocommerce;
                ini_set("display_errors", 0);
               
              
                $order_id =  $_GET['order_id']; 
                $order_data=  wc_get_order( $order_id);
                $order_meta_data=$order_data->get_meta('_payg_meta_data');
                $order_meta_data=json_decode($order_meta_data,true);
                $post_array=array();
                $post_array['OrderKeyId']= $order_meta_data['OrderKeyId'];
                $post_array['MerchantKeyId']= $order_meta_data['OrderKeyId'];
                $post_array['PaymentTransactionId']= $order_meta_data['PaymentTransactionId'];
                $post_array['PaymentType']= $order_meta_data['PaymentType'];
                $post['Merchantkeyid']= $this->merchant_id; 
                $post['MerchantAuthenticationKey']=$this->authentication_key;
                $post['MerchantAuthenticationToken']=$this->authentication_token;
                $header_key = $post['MerchantAuthenticationKey'].":".$post['MerchantAuthenticationToken'].":M:".$post['Merchantkeyid'];
                $response_data=$this->postApi($header_key,$post_array,"https://uatapi.payg.in/payment/api/order/Detail");
                add_post_meta( $order_id, '_payg_order_response', $response_data, true );
                $payg_order_response=json_decode($response_data,true);

                
                //get rid of time part
                $order = new WC_Order($order_id);
                if ($order_id != '') {
                    if (!empty($payg_order_response['PaymentResponseCode'])) {
                        try {

                            $txstatus = $payg_order_response['PaymentResponseText'];
                            $txrefno = $payg_order_response['PaymentTransactionId'];
                            
                            $transauthorised = false;

                            if ($order->get_status() !== 'completed') {
                                //if(strcmp($txstatus, 'SUCCESS') == 0)
                                if ($txstatus == 'Approved') {

                                    $transauthorised = true;
                                    $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                                    $this->msg['class'] = 'success';
                                    if ($order->get_status() == 'processing') {
                                        //do nothing
                                    } else {
                                        //complete the order
                                        $order->payment_complete($txrefno);
                                        //add_post_meta( $order->id, '_transaction_id', $txrefno, true );
                                        $order->add_order_note($payg_title.' Payment Gateway has processed the payment. Ref Number: ' . $txrefno);
                                        $order->add_order_note($this->msg['message']);
                                        $woocommerce->cart->empty_cart();
                                    }
                                } else {
                                    $this->msg['class'] = 'error';
                                    $this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";

                                    //Here you need to put in the routines for a failed
                                    //transaction such as sending an email to customer
                                    //setting database status etc etc
                                }

                                if ($transauthorised == false) {
                                    $order->update_status('failed');
                                    $order->add_order_note('Failed');
                                    $order->add_order_note($this->msg['message']);
                                }
                                //removed for WooCOmmerce 2.0
                                //add_action('the_content', array(&$this, 'showMessage'));
                            }
                        } catch (Exception $e) {
                            // $errorOccurred = true;
                            //$msg = "Error " . $e->getMessage();
                            $this->msg['class'] = 'error';
                            $this->msg['message'] = $e->getMessage();
                        }
                    } else {
                        //Failure
                        $this->msg['class'] = 'error';
                        $this->msg['message'] = $_POST['ResponseText'];
                    }
                } else {
                    //Failure
                    $this->msg['class'] = 'error';
                    $this->msg['message'] = "Attempt to forge transaction...";
                }

                //  $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                //For wooCoomerce 2.0
                // $redirect_url = add_query_arg( array('msg'=> urlencode($this -> msg['message']), 'type'=>$this -> msg['class']), $redirect_url );
                if (isset($txstatus) && ($txstatus == 'Successful' || $txstatus == 'successful')) {
                    $redirect_url = $order->get_checkout_order_received_url();
                } else {
                    $redirect_url = $order->get_cancel_order_url();
                }

                $redirect_url = add_query_arg(array('msg' => urlencode($this->msg['message']), 'type' => $this->msg['class']), $redirect_url);
                wp_redirect($redirect_url);
                exit;
            }

            /**
             * Generate PayG button link
             * */
            public function generate_payg_form($order_id) {
                global $payg_title; global $payg_lowercase_title; ;
                global $woocommerce;
                $order = new WC_Order($order_id);
                $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id);

                //For wooCoomerce 2.0
                $redirect_url = add_query_arg('wc-api', get_class($this), $redirect_url);
                     
                $address = $order->get_billing_address_1();
                if ($order->get_billing_address_2() != "") {
                    $address = $address . ' ' . $order->get_billing_address_2();
                }
                $currencycode = get_woocommerce_currency();
                $merchantTxnId = $order_id;
                $orderAmount = $order->get_total();
                $action = $this->url;
                
                $return_elements = array();

                //this need to be same order for hashing works
          

                $payg_args['me_id'] = $this->merchant_id; 
                $txn_details = array();
                $payg_args_array = array(); 
                $post['Merchantkeyid']= $this->merchant_id; 
                $post['MerchantAuthenticationKey']=$this->authentication_key;
                $post['MerchantAuthenticationToken']=$this->authentication_token;
                $post['UniqueRequestId']= uniqid();
                $post['OrderAmount']=number_format($orderAmount,2); 
                $post['OrderId']=$order_id;
                $post['OrderStatus']=$order->get_status();
                $post['OrderAmountData']=number_format($orderAmount,2); 
                $post['ProductData']=json_encode($woocommerce->cart->get_cart());
                $post['NextStepFlowData']="{}";
                //$post['TransactionData']

                $post['RedirectUrl']= $redirect_url."&order_id=".$order_id; 
                $post['OrderId']=$order_id;
                $post['CustomerEmail']=$order->get_billing_email();
                $post['TransactionType']="Charge";
             
                $txn_details = $this->secure_key.'|'.$post['Merchantkeyid'] . '|' . $post['UniqueRequestId'] . '|' . $post['MerchantAuthenticationKey'] . '|' . $post['MerchantAuthenticationToken'] . '|' . $post['OrderAmount'] ;
                $return_elements['txn_details'] =hash("sha256",$txn_details);
                
                
                $header_key = $post['MerchantAuthenticationKey'].":".$post['MerchantAuthenticationToken'].":M:".$post['Merchantkeyid'];
                $order->get_customer_ip_address(); 


                //customer data
                $customer=array();$shipment_details=array();
                $customer['CustomerId']=$order->get_customer_id();  
                $customer['FirstName']=$order->get_billing_first_name();
                $customer['LastName']=$order->get_billing_last_name();
                $customer['MobileNo']=$order->get_billing_phone();
                $customer['Email']=$order->get_billing_email();
                $customer['EmailReceipt']=false; 
                $customer['FirstName']=$order->get_billing_company();
                $customer['BillingAddress']=$order->get_formatted_billing_address(); 
                $customer['BillingCity']=$order->get_billing_city();
                $customer['BillingState']=$order->get_billing_state(); 
                $customer['BillingCountry']=$order->get_billing_country();
                $customer['BillingZipCode']=$order->get_billing_postcode();
                $shipment_details['ShippingFirstName']=$customer['ShippingFirstName']=$order->get_shipping_first_name();
                $shipment_details['ShippingLastName']=$customer['ShippingLastName']=$order->get_shipping_last_name();
                $shipment_details['ShippingAddress']=$customer['ShippingAddress']=$order->get_formatted_shipping_address();
                $shipment_details['ShippingCity']=$customer['ShippingCity']=$order->get_shipping_city();
                $shipment_details['ShippingState']=$customer['ShippingState']=$order->get_shipping_state();
                $shipment_details['ShippingCountry']=$customer['ShippingCountry']=$order->get_shipping_country();
                $shipment_details['ShippingZipCode']=$customer['ShippingZipCode']=$order->get_shipping_postcode();
                $shipment_details['ShippingMobileNo']=$customer['ShippingMobileNo']='';
                $post['CustomerData']=$customer;//json_decode(json_encode($customer));


                //UserDefined data
                $userdefined=array();
                $userdefined['UserDefined1']="";
                $userdefined['UserDefined2']="";
                $userdefined['UserDefined3']="";
                $userdefined['UserDefined4']="";
                $userdefined['UserDefined5']="";
                $userdefined['UserDefined6']="";
                $userdefined['UserDefined7']="";
                $userdefined['UserDefined8']="";
                $userdefined['UserDefined9']="";
                $userdefined['UserDefined10']="";
                $userdefined['UserDefined11']="";
                $userdefined['UserDefined12']="";
                $userdefined['UserDefined13']="";
                $userdefined['UserDefined14']="";
                $userdefined['UserDefined15']="";
                $userdefined['UserDefined16']="";
                $userdefined['UserDefined17']="";
                $userdefined['UserDefined18']="";
                $userdefined['UserDefined19']="";
                $userdefined['UserDefined20']="";
                $post['UserDefinedData']=$userdefined;//json_decode(json_encode($userdefined));

                $integrationData=array();
                $integrationData['UserName']=$order->get_billing_email();
                $integrationData['Source']="Woocommerce and Version :". $woocommerce->version;
                $integrationData['IntegrationType']=0;
                $integrationData['HashData']=$return_elements['txn_details'];
                $integrationData['PlatformId']=""; 
                $post['ShipmentData']=$shipment_details;//json_encode($shipment_details);
                $post['RequestDateTime']=date("mdY");
                $response_data=$this->postApi($header_key,$post,"https://uatapi.payg.in/payment/api/order/create");
                add_post_meta( $order_id, '_payg_meta_data', $response_data, true );
                $order_create_response=json_decode($response_data,true);
                    //    echo "<pre>";print_r($response_data);print_r($order_create_response);exit;
                    //   header("location:".$order_create_response["PaymentProcessUrl"]);
 
                    return '<script type="text/javascript">
                                        jQuery(function(){
                                        jQuery("body").block(
                                            {
                                                message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirecting\" style=\"float:left; margin-right: 10px;\" />' . 'Thank you for your order. We are now redirecting you to '.$payg_title.' Payment Gateway to make payment.' . '",
                                                    overlayCSS:
                                            {
                                                background: "#fff",
                                                    opacity: 0.6
                                        },
                                        css: {
                                            padding:        20,
                                                textAlign:      "center",
                                                color:          "#555",
                                                border:         "3px solid #aaa",
                                                backgroundColor:"#fff",
                                                cursor:         "wait",
                                                lineHeight:"32px"
                                        }
                                        });
                                        window.location=\''.$order_create_response["PaymentProcessUrl"].'\';

                                        });
                                        </script> ';
                    }

            function get_pages($title = false, $indent = true) {
                global $payg_title; global $payg_lowercase_title; ;
                $wp_pages = get_pages('sort_column=menu_order');
                $page_list = array();
                if ($title) {
                    $page_list[] = $title;
                }
                foreach ($wp_pages as $page) {
                    $prefix = '';
                    // show indented child pages?
                    if ($indent) {
                        $has_parent = $page->post_parent;
                        while ($has_parent) {
                            $prefix .= ' - ';
                            $next_page = get_page($has_parent);
                            $has_parent = $next_page->post_parent;
                        }
                    }
                    // add to page list array array
                    $page_list[$page->ID] = $prefix . $page->post_title;
                }
                return $page_list;
            }

            function postApi($header,$post,$url){
                // $post_data="";
                // foreach($post as $key=>$value) { $post_data .= $key.'='.$value.'&'; }
                // echo "<pre>";print_r($post_data);exit;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL,$url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($post));   
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
                $headers = [ 
                   'Authorization: Basic '.base64_encode($header)
                ]; 
                curl_setopt($ch, CURLOPT_HEADER, false); 
                
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
                $server_output = curl_exec ($ch); 
                curl_close ($ch);
              
                return $server_output;
            }
            function process_refund( $order_id, $amount, $reason ){
                 
                 $order = new WC_Order($orderId); 
                 $order_id =  $_GET['order_id']; 
                 $order_data=  wc_get_order( $order_id);
                 $order_meta_data=$order_data->get_meta('_payg_order_response');
                 $order_meta_data=json_decode($order_meta_data,true);
                 $post_array=array();
                 $post_array['OrderKeyId']= $order_meta_data['OrderKeyId'];
                 $post_array['MerchantKeyId']= $order_meta_data['OrderKeyId'];
                 $post_array['PaymentTransactionId']= $order_meta_data['PaymentTransactionId'];
                 $post_array['PaymentType']= $order_meta_data['PaymentType'];
                 $post['Merchantkeyid']= $this->merchant_id; 
                 $post['MerchantAuthenticationKey']=$this->authentication_key;
                 $post['MerchantAuthenticationToken']=$this->authentication_token;
                 $header_key = $post['MerchantAuthenticationKey'].":".$post['MerchantAuthenticationToken'].":M:".$post['Merchantkeyid'];
                 $response_data=$this->postApi($header_key,$post_array,"https://uatapi.payg.in/payment/api/order/Update");
                 add_post_meta( $order_id, '_payg_order_response', $response_data, true );
                 $payg_order_response=json_decode($response_data,true);
                 if(empty($transaction_id)){
                     return false;
                 }
                 $data["ag_ref"]=$transaction_id;
                 $data["refund_amount"]=$amount;
                 $data["refund_reason"]=$reason; 
                 $form_elements =  $this->initiateRefundProcess($data);
                 $json_refund_response = $this->getRefundResponsecurl($server_refund_url, $form_elements);
                 $refund_response=json_decode($json_refund_response,true);
                 if($refund_response['res_code'] == "0000"){  
                     $order->add_order_note( __( 'Refund Id: ' . $refund_response['refund_ref'], 'woocommerce' ) );

                     return true;
                 }else{
                    $log = array(
                        'Error' =>  $refund_response['error_details'],
                    );
        
                    error_log(json_encode($log));
                     return false;
                 }
                 //echo "<pre>";print_r($refund_response);return false;
                exit;
            }
        }

        /**
         * Add the Gateway to WooCommerce
         * */
        if (!function_exists('woocommerce_add_payg_gateway')) {

            function woocommerce_add_payg_gateway($methods) {
                $methods[] = 'WC_PayG';
                return $methods;
            }

        }

        function kia_convert_country_code($country) {
            $countries = array(
                'AF' => 'AFG', //Afghanistan
                'AX' => 'ALA', //&#197;land Islands
                'AL' => 'ALB', //Albania
                'DZ' => 'DZA', //Algeria
                'AS' => 'ASM', //American Samoa
                'AD' => 'AND', //Andorra
                'AO' => 'AGO', //Angola
                'AI' => 'AIA', //Anguilla
                'AQ' => 'ATA', //Antarctica
                'AG' => 'ATG', //Antigua and Barbuda
                'AR' => 'ARG', //Argentina
                'AM' => 'ARM', //Armenia
                'AW' => 'ABW', //Aruba
                'AU' => 'AUS', //Australia
                'AT' => 'AUT', //Austria
                'AZ' => 'AZE', //Azerbaijan
                'BS' => 'BHS', //Bahamas
                'BH' => 'BHR', //Bahrain
                'BD' => 'BGD', //Bangladesh
                'BB' => 'BRB', //Barbados
                'BY' => 'BLR', //Belarus
                'BE' => 'BEL', //Belgium
                'BZ' => 'BLZ', //Belize
                'BJ' => 'BEN', //Benin
                'BM' => 'BMU', //Bermuda
                'BT' => 'BTN', //Bhutan
                'BO' => 'BOL', //Bolivia
                'BQ' => 'BES', //Bonaire, Saint Estatius and Saba
                'BA' => 'BIH', //Bosnia and Herzegovina
                'BW' => 'BWA', //Botswana
                'BV' => 'BVT', //Bouvet Islands
                'BR' => 'BRA', //Brazil
                'IO' => 'IOT', //British Indian Ocean Territory
                'BN' => 'BRN', //Brunei
                'BG' => 'BGR', //Bulgaria
                'BF' => 'BFA', //Burkina Faso
                'BI' => 'BDI', //Burundi
                'KH' => 'KHM', //Cambodia
                'CM' => 'CMR', //Cameroon
                'CA' => 'CAN', //Canada
                'CV' => 'CPV', //Cape Verde
                'KY' => 'CYM', //Cayman Islands
                'CF' => 'CAF', //Central African Republic
                'TD' => 'TCD', //Chad
                'CL' => 'CHL', //Chile
                'CN' => 'CHN', //China
                'CX' => 'CXR', //Christmas Island
                'CC' => 'CCK', //Cocos (Keeling) Islands
                'CO' => 'COL', //Colombia
                'KM' => 'COM', //Comoros
                'CG' => 'COG', //Congo
                'CD' => 'COD', //Congo, Democratic Republic of the
                'CK' => 'COK', //Cook Islands
                'CR' => 'CRI', //Costa Rica
                'CI' => 'CIV', //C�te d\'Ivoire
                'HR' => 'HRV', //Croatia
                'CU' => 'CUB', //Cuba
                'CW' => 'CUW', //Cura�ao
                'CY' => 'CYP', //Cyprus
                'CZ' => 'CZE', //Czech Republic
                'DK' => 'DNK', //Denmark
                'DJ' => 'DJI', //Djibouti
                'DM' => 'DMA', //Dominica
                'DO' => 'DOM', //Dominican Republic
                'EC' => 'ECU', //Ecuador
                'EG' => 'EGY', //Egypt
                'SV' => 'SLV', //El Salvador
                'GQ' => 'GNQ', //Equatorial Guinea
                'ER' => 'ERI', //Eritrea
                'EE' => 'EST', //Estonia
                'ET' => 'ETH', //Ethiopia
                'FK' => 'FLK', //Falkland Islands
                'FO' => 'FRO', //Faroe Islands
                'FJ' => 'FIJ', //Fiji
                'FI' => 'FIN', //Finland
                'FR' => 'FRA', //France
                'GF' => 'GUF', //French Guiana
                'PF' => 'PYF', //French Polynesia
                'TF' => 'ATF', //French Southern Territories
                'GA' => 'GAB', //Gabon
                'GM' => 'GMB', //Gambia
                'GE' => 'GEO', //Georgia
                'DE' => 'DEU', //Germany
                'GH' => 'GHA', //Ghana
                'GI' => 'GIB', //Gibraltar
                'GR' => 'GRC', //Greece
                'GL' => 'GRL', //Greenland
                'GD' => 'GRD', //Grenada
                'GP' => 'GLP', //Guadeloupe
                'GU' => 'GUM', //Guam
                'GT' => 'GTM', //Guatemala
                'GG' => 'GGY', //Guernsey
                'GN' => 'GIN', //Guinea
                'GW' => 'GNB', //Guinea-Bissau
                'GY' => 'GUY', //Guyana
                'HT' => 'HTI', //Haiti
                'HM' => 'HMD', //Heard Island and McDonald Islands
                'VA' => 'VAT', //Holy See (Vatican City State)
                'HN' => 'HND', //Honduras
                'HK' => 'HKG', //Hong Kong
                'HU' => 'HUN', //Hungary
                'IS' => 'ISL', //Iceland
                'IN' => 'IND', //India
                'ID' => 'IDN', //Indonesia
                'IR' => 'IRN', //Iran
                'IQ' => 'IRQ', //Iraq
                'IE' => 'IRL', //Republic of Ireland
                'IM' => 'IMN', //Isle of Man
                'IL' => 'ISR', //Israel
                'IT' => 'ITA', //Italy
                'JM' => 'JAM', //Jamaica
                'JP' => 'JPN', //Japan
                'JE' => 'JEY', //Jersey
                'JO' => 'JOR', //Jordan
                'KZ' => 'KAZ', //Kazakhstan
                'KE' => 'KEN', //Kenya
                'KI' => 'KIR', //Kiribati
                'KP' => 'PRK', //Korea, Democratic People\'s Republic of
                'KR' => 'KOR', //Korea, Republic of (South)
                'KW' => 'KWT', //Kuwait
                'KG' => 'KGZ', //Kyrgyzstan
                'LA' => 'LAO', //Laos
                'LV' => 'LVA', //Latvia
                'LB' => 'LBN', //Lebanon
                'LS' => 'LSO', //Lesotho
                'LR' => 'LBR', //Liberia
                'LY' => 'LBY', //Libya
                'LI' => 'LIE', //Liechtenstein
                'LT' => 'LTU', //Lithuania
                'LU' => 'LUX', //Luxembourg
                'MO' => 'MAC', //Macao S.A.R., China
                'MK' => 'MKD', //Macedonia
                'MG' => 'MDG', //Madagascar
                'MW' => 'MWI', //Malawi
                'MY' => 'MYS', //Malaysia
                'MV' => 'MDV', //Maldives
                'ML' => 'MLI', //Mali
                'MT' => 'MLT', //Malta
                'MH' => 'MHL', //Marshall Islands
                'MQ' => 'MTQ', //Martinique
                'MR' => 'MRT', //Mauritania
                'MU' => 'MUS', //Mauritius
                'YT' => 'MYT', //Mayotte
                'MX' => 'MEX', //Mexico
                'FM' => 'FSM', //Micronesia
                'MD' => 'MDA', //Moldova
                'MC' => 'MCO', //Monaco
                'MN' => 'MNG', //Mongolia
                'ME' => 'MNE', //Montenegro
                'MS' => 'MSR', //Montserrat
                'MA' => 'MAR', //Morocco
                'MZ' => 'MOZ', //Mozambique
                'MM' => 'MMR', //Myanmar
                'NA' => 'NAM', //Namibia
                'NR' => 'NRU', //Nauru
                'NP' => 'NPL', //Nepal
                'NL' => 'NLD', //Netherlands
                'AN' => 'ANT', //Netherlands Antilles
                'NC' => 'NCL', //New Caledonia
                'NZ' => 'NZL', //New Zealand
                'NI' => 'NIC', //Nicaragua
                'NE' => 'NER', //Niger
                'NG' => 'NGA', //Nigeria
                'NU' => 'NIU', //Niue
                'NF' => 'NFK', //Norfolk Island
                'MP' => 'MNP', //Northern Mariana Islands
                'NO' => 'NOR', //Norway
                'OM' => 'OMN', //Oman
                'PK' => 'PAK', //Pakistan
                'PW' => 'PLW', //Palau
                'PS' => 'PSE', //Palestinian Territory
                'PA' => 'PAN', //Panama
                'PG' => 'PNG', //Papua New Guinea
                'PY' => 'PRY', //Paraguay
                'PE' => 'PER', //Peru
                'PH' => 'PHL', //Philippines
                'PN' => 'PCN', //Pitcairn
                'PL' => 'POL', //Poland
                'PT' => 'PRT', //Portugal
                'PR' => 'PRI', //Puerto Rico
                'QA' => 'QAT', //Qatar
                'RE' => 'REU', //Reunion
                'RO' => 'ROU', //Romania
                'RU' => 'RUS', //Russia
                'RW' => 'RWA', //Rwanda
                'BL' => 'BLM', //Saint Barth&eacute;lemy
                'SH' => 'SHN', //Saint Helena
                'KN' => 'KNA', //Saint Kitts and Nevis
                'LC' => 'LCA', //Saint Lucia
                'MF' => 'MAF', //Saint Martin (French part)
                'SX' => 'SXM', //Sint Maarten / Saint Matin (Dutch part)
                'PM' => 'SPM', //Saint Pierre and Miquelon
                'VC' => 'VCT', //Saint Vincent and the Grenadines
                'WS' => 'WSM', //Samoa
                'SM' => 'SMR', //San Marino
                'ST' => 'STP', //S&atilde;o Tom&eacute; and Pr&iacute;ncipe
                'SA' => 'SAU', //Saudi Arabia
                'SN' => 'SEN', //Senegal
                'RS' => 'SRB', //Serbia
                'SC' => 'SYC', //Seychelles
                'SL' => 'SLE', //Sierra Leone
                'SG' => 'SGP', //Singapore
                'SK' => 'SVK', //Slovakia
                'SI' => 'SVN', //Slovenia
                'SB' => 'SLB', //Solomon Islands
                'SO' => 'SOM', //Somalia
                'ZA' => 'ZAF', //South Africa
                'GS' => 'SGS', //South Georgia/Sandwich Islands
                'SS' => 'SSD', //South Sudan
                'ES' => 'ESP', //Spain
                'LK' => 'LKA', //Sri Lanka
                'SD' => 'SDN', //Sudan
                'SR' => 'SUR', //Suriname
                'SJ' => 'SJM', //Svalbard and Jan Mayen
                'SZ' => 'SWZ', //Swaziland
                'SE' => 'SWE', //Sweden
                'CH' => 'CHE', //Switzerland
                'SY' => 'SYR', //Syria
                'TW' => 'TWN', //Taiwan
                'TJ' => 'TJK', //Tajikistan
                'TZ' => 'TZA', //Tanzania
                'TH' => 'THA', //Thailand    
                'TL' => 'TLS', //Timor-Leste
                'TG' => 'TGO', //Togo
                'TK' => 'TKL', //Tokelau
                'TO' => 'TON', //Tonga
                'TT' => 'TTO', //Trinidad and Tobago
                'TN' => 'TUN', //Tunisia
                'TR' => 'TUR', //Turkey
                'TM' => 'TKM', //Turkmenistan
                'TC' => 'TCA', //Turks and Caicos Islands
                'TV' => 'TUV', //Tuvalu     
                'UG' => 'UGA', //Uganda
                'UA' => 'UKR', //Ukraine
                'AE' => 'ARE', //United Arab Emirates
                'GB' => 'GBR', //United Kingdom
                'US' => 'USA', //United States
                'UM' => 'UMI', //United States Minor Outlying Islands
                'UY' => 'URY', //Uruguay
                'UZ' => 'UZB', //Uzbekistan
                'VU' => 'VUT', //Vanuatu
                'VE' => 'VEN', //Venezuela
                'VN' => 'VNM', //Vietnam
                'VG' => 'VGB', //Virgin Islands, British
                'VI' => 'VIR', //Virgin Island, U.S.
                'WF' => 'WLF', //Wallis and Futuna
                'EH' => 'ESH', //Western Sahara
                'YE' => 'YEM', //Yemen
                'ZM' => 'ZMB', //Zambia
                'ZW' => 'ZWE', //Zimbabwe
            );
            $iso_code = isset($countries[$country]) ? $countries[$country] : $country;
            return $iso_code;
        }

        add_filter('woocommerce_payment_gateways', 'woocommerce_add_payg_gateway');

        /**
         * Show action links on the plugin screen.
         *
         * @param   mixed $links Plugin Action links.
         * @return  array
         */
        // if (!function_exists('plugin_action_links')) {

        //     function plugin_action_links($links) {
        //         global $payg_title; global $payg_lowercase_title; ;
        //         $action_links = array(
        //             'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=payg') . '" aria-label="' . esc_attr__('View $payg_title settings', $payg_lowercase_title) . '">' . esc_html__('Settings', $payg_lowercase_title) . '</a>',
        //         );
        //         return array_merge($action_links, $links);
        //     }

        // }
        add_filter('plugin_action_links_payg', 'plugin_action_links');
    }

}
?>