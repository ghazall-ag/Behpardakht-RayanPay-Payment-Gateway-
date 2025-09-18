<?php
/*
Plugin Name: Behpardakht Payment Gateway
Description: ایجاد درگاه پرداخت به پرداخت ملت برای ووکامرس
Version: 1.1
Author: به پرداخت ملت
*/

if (!defined('ABSPATH')) exit;

add_action('woocommerce_loaded', 'rayan_payment_init');

function rayan_payment_init() {
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Rayan_Payment_Gateway extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'rayan_payment';
            $this->method_title       = 'Behpardakht Payment Gateway';
            $this->method_description = 'درگاه پرداخت به پرداخت ملت';
            $this->title              = 'درگاه پرداخت به پرداخت ملت';
            $this->has_fields         = false;

            $this->icon = plugin_dir_url(__FILE__) . 'logo.png';

            $this->init_form_fields();
            $this->init_settings();

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_rayan_payment_callback', array($this, 'handle_callback'));

            if (!function_exists('write_log')) {
                function write_log($log) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        if (is_array($log) || is_object($log)) {
                            error_log(print_r($log, true));
                        } else {
                            error_log($log);
                        }
                    }
                }
            }
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => 'فعال/غیرفعال',
                    'type'    => 'checkbox',
                    'label'   => 'فعال‌سازی درگاه',
                    'default' => 'yes',
                ),
                'terminal_number' => array(
                    'title'       => 'شماره ترمینال',
                    'type'        => 'text',
                    'description' => 'شماره ترمینال به پرداخت ملت',
                    'default'     => '',
                ),
                'terminal_password' => array(
                    'title'       => 'رمز ترمینال',
                    'type'        => 'password',
                    'description' => 'رمز ترمینال به پرداخت ملت',
                    'default'     => '',
                ),
            );
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $order_total = $order->get_total();

            if (get_woocommerce_currency() !== 'IRR') {
                $conversion_rate = 10;
                $order_total *= $conversion_rate;
            }

            $items = $order->get_items();
            $weldpay_items = array();
            $categories = array();

            foreach ($items as $item) {
                $weldpay_items[] = array(
                    'Name'  => $item->get_name(),
                    'Notes' => $item->get_quantity(),
                    'Amount'=> $item->get_total(),
                );

                $product_id = $item->get_product_id();
                $product_categories = wp_get_post_terms($product_id, 'product_cat');
                foreach ($product_categories as $category) {
                    $categories[] = $category->name;
                }
            }

            $checkout_data = array(
                'orderId'                => $order_id,
                'terminalNumber'         => $this->get_option('terminal_number'),
                'terminalPassword'       => $this->get_option('terminal_password'),
                'amount'                 => $order_total,
                'invoiceDetailJsonString'=> json_encode($weldpay_items),
                'productNumber'          => json_encode($categories),
                'additionalData'         => "",
                'callBackUrl'            => WC()->api_request_url('rayan_payment_callback'),
            );

            $checkout_data_json = wp_json_encode($checkout_data);

            try {
                $response = wp_remote_post('https://api.eblustore.shop/api/GatewayProvider/Token', array(
                    'body'    => $checkout_data_json,
                    'headers' => array('Content-Type' => 'application/json'),
                    'timeout' => 20,
                    'sslverify' => true,
                ));

                if (is_wp_error($response)) throw new Exception($response->get_error_message());

                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                $response_data = json_decode($response_body, true);

                if ($response_code === 200 && isset($response_data['data']['token'])) {
                    $token = $response_data['data']['token'];

                    return array(
                        'result'   => 'success',
                        'redirect' => 'https://eblustore.shop/Payment/' . $token,
                    );
                } else {
                    write_log('Invalid response: ' . print_r($response_data, true));
                    throw new Exception('خطا در ایجاد توکن پرداخت');
                }

            } catch (Exception $e) {
                wc_add_notice('خطا در اتصال به درگاه پرداخت: ' . $e->getMessage(), 'error');
                return array(
                    'result'   => 'failure',
                    'redirect' => wc_get_checkout_url(),
                );
            }
        }

        public function handle_callback() {
            $order_id = $_GET['orderId'] ?? '';
            $reference_number = $_GET['referenceNumber'] ?? '';
            $amount = $_GET['amount'] ?? '';
            $is_success = $_GET['isSuccess'] ?? '';

            $order = wc_get_order($order_id);
            if (!$order) wp_die('سفارش یافت نشد');

            $terminal_number = $this->get_option('terminal_number');
            $status = 'ناموفق';

            if ($is_success === 'True') {
                $verify_data = array(
                    'orderId'         => $order_id,
                    'referenceNumber' => $reference_number,
                    'amount'          => $amount,
                    'terminalNumber'  => $terminal_number,
                );

                $response = wp_remote_post('https://api.eblustore.shop/api/GatewayProvider/Verify', array(
                    'body'    => wp_json_encode($verify_data),
                    'headers' => array('Content-Type' => 'application/json'),
                    'timeout' => 20,
                    'sslverify' => true,
                ));

                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $order->payment_complete($reference_number);
                    $order->add_order_note('پرداخت با موفقیت انجام شد');
                    WC()->cart->empty_cart();
                    $status = 'موفق';
                } else {
                    $order->update_status('failed', 'پرداخت موفقیت‌آمیز نبود.');
                }
            } else {
                $order->update_status('cancelled', 'پرداخت ناموفق بود');
            }

            wp_redirect(add_query_arg(array(
                'orderId'         => $order_id,
                'referenceNumber' => $reference_number,
                'amount'          => $amount,
                'status'          => $status
            ), home_url('/payment-result/')));
            exit;
        }

    }

    add_filter('woocommerce_payment_gateways', function($gateways){
        $gateways[] = 'WC_Rayan_Payment_Gateway';
        return $gateways;
    });
}
