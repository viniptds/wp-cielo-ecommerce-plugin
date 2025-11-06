<?php
/**
 * Plugin Name: Cielo eCommerce 3.0 Gateway
 * Plugin URI: https://exemplo.com
 * Description: Gateway de pagamento com cartão de crédito integrado à API Cielo eCommerce 3.0
 * Version: 1.0.2
 * Author: Seu Nome
 * Author URI: https://exemplo.com
 * Text Domain: cielo-ecommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verifica se WooCommerce está ativo
add_action('plugins_loaded', 'cielo_ecommerce_init', 11);

function cielo_ecommerce_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Cielo_Gateway extends WC_Payment_Gateway {
        
        public function __construct() {
            $this->id = 'cielo_ecommerce';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = __('Cielo eCommerce 3.0', 'cielo-ecommerce');
            $this->method_description = __('Aceite pagamentos com cartão de crédito via Cielo eCommerce 3.0', 'cielo-ecommerce');
            
            $this->supports = array(
                'products'
            );
            
            $this->init_form_fields();
            $this->init_settings();
            
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->enable_debug = 'yes' === $this->get_option('enable_debug');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->merchant_id = $this->testmode ? $this->get_option('test_merchant_id') : $this->get_option('merchant_id');
            $this->merchant_key = $this->testmode ? $this->get_option('test_merchant_key') : $this->get_option('merchant_key');
            $this->establishment_code = $this->get_option('establishment_code');
            
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            $this->cielo_log('Cielo eCommerce 3.0 Gateway iniciado com sucesso.');
        }
        
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Habilitar/Desabilitar', 'cielo-ecommerce'),
                    'type' => 'checkbox',
                    'label' => __('Habilitar Cielo eCommerce', 'cielo-ecommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Título', 'cielo-ecommerce'),
                    'type' => 'text',
                    'description' => __('Título que o usuário vê durante o checkout.', 'cielo-ecommerce'),
                    'default' => __('Cartão de Crédito', 'cielo-ecommerce'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Descrição', 'cielo-ecommerce'),
                    'type' => 'textarea',
                    'description' => __('Descrição que o usuário vê durante o checkout.', 'cielo-ecommerce'),
                    'default' => __('Pague com seu cartão de crédito via Cielo.', 'cielo-ecommerce'),
                ),
                'testmode' => array(
                    'title' => __('Modo de Teste', 'cielo-ecommerce'),
                    'type' => 'checkbox',
                    'label' => __('Habilitar modo de teste', 'cielo-ecommerce'),
                    'default' => 'yes',
                    'description' => __('Use as credenciais de sandbox para testes.', 'cielo-ecommerce'),
                ),
                'merchant_id' => array(
                    'title' => __('Merchant ID (Produção)', 'cielo-ecommerce'),
                    'type' => 'text',
                    'description' => __('Seu Merchant ID da Cielo para ambiente de produção.', 'cielo-ecommerce'),
                    'default' => '',
                ),
                'merchant_key' => array(
                    'title' => __('Merchant Key (Produção)', 'cielo-ecommerce'),
                    'type' => 'password',
                    'description' => __('Sua Merchant Key da Cielo para ambiente de produção.', 'cielo-ecommerce'),
                    'default' => '',
                ),
                'test_merchant_id' => array(
                    'title' => __('Merchant ID (Teste)', 'cielo-ecommerce'),
                    'type' => 'text',
                    'description' => __('Seu Merchant ID da Cielo para ambiente de teste/sandbox.', 'cielo-ecommerce'),
                    'default' => '',
                ),
                'test_merchant_key' => array(
                    'title' => __('Merchant Key (Teste)', 'cielo-ecommerce'),
                    'type' => 'password',
                    'description' => __('Sua Merchant Key da Cielo para ambiente de teste/sandbox.', 'cielo-ecommerce'),
                    'default' => '',
                ),
                'establishment_code' => array(
                    'title' => __('Código do Estabelecimento', 'cielo-ecommerce'),
                    'type' => 'text',
                    'description' => __('Código do estabelecimento comercial (EC) fornecido pela Cielo. Este código identifica sua loja na rede Cielo.', 'cielo-ecommerce'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'debug' => array(
                    'title' => __('Ativar Debug', 'cielo-ecommerce'),
                    'type' => 'checkbox',
                    'description' => __('Ativa logs de debug', 'cielo-ecommerce'),
                    'default' => 'no',
                ),
            );
        }
        
        public function payment_scripts() {
            if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
                return;
            }
            
            if ('no' === $this->enabled) {
                return;
            }
            
            if (empty($this->merchant_id) || empty($this->merchant_key)) {
                return;
            }
            
            wp_enqueue_script('cielo-payment', plugins_url('assets/js/cielo-payment.js', __FILE__), array('jquery'), '1.0.0', true);
            
            wp_localize_script('cielo-payment', 'cielo_params', array(
                'testmode' => $this->testmode
            ));
        }
        
        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }
            
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
            
            do_action('woocommerce_credit_card_form_start', $this->id);
            
            echo '<div class="form-row form-row-wide">
                    <label>' . __('Número do Cartão', 'cielo-ecommerce') . ' <span class="required">*</span></label>
                    <input id="cielo_card_number" name="cielo_card_number" type="text" maxlength="19" autocomplete="off" placeholder="•••• •••• •••• ••••" />
                  </div>
                  <div class="form-row form-row-first">
                    <label>' . __('Nome no Cartão', 'cielo-ecommerce') . ' <span class="required">*</span></label>
                    <input id="cielo_card_holder" name="cielo_card_holder" type="text" autocomplete="off" placeholder="Nome como está no cartão" />
                  </div>
                  <div class="form-row form-row-last">
                    <label>' . __('Validade (MM/AA)', 'cielo-ecommerce') . ' <span class="required">*</span></label>
                    <input id="cielo_card_expiry" name="cielo_card_expiry" type="text" autocomplete="off" placeholder="MM/AA" maxlength="5" />
                  </div>
                  <div class="form-row form-row-first">
                    <label>' . __('CVV', 'cielo-ecommerce') . ' <span class="required">*</span></label>
                    <input id="cielo_card_cvc" name="cielo_card_cvc" type="text" autocomplete="off" placeholder="CVV" maxlength="4" />
                  </div>
                  <div class="form-row form-row-last">
                    <label>' . __('Parcelas', 'cielo-ecommerce') . ' <span class="required">*</span></label>
                    <select id="cielo_installments" name="cielo_installments">';
            
            $total = WC()->cart->total;
            for ($i = 1; $i <= 12; $i++) {
                $installment_value = $total / $i;
                echo '<option value="' . $i . '">' . $i . 'x de R$ ' . number_format($installment_value, 2, ',', '.') . '</option>';
            }
            
            echo '    </select>
                  </div>
                  <div class="clear"></div>';
            
            do_action('woocommerce_credit_card_form_end', $this->id);
            
            echo '<div class="clear"></div></fieldset>';
        }
        
        public function validate_fields() {
            if (empty($_POST['cielo_card_number'])) {
                wc_add_notice(__('Número do cartão é obrigatório', 'cielo-ecommerce'), 'error');
                return false;
            }
            
            if (empty($_POST['cielo_card_holder'])) {
                wc_add_notice(__('Nome no cartão é obrigatório', 'cielo-ecommerce'), 'error');
                return false;
            }
            
            if (empty($_POST['cielo_card_expiry'])) {
                wc_add_notice(__('Data de validade é obrigatória', 'cielo-ecommerce'), 'error');
                return false;
            }
            
            if (empty($_POST['cielo_card_cvc'])) {
                wc_add_notice(__('CVV é obrigatório', 'cielo-ecommerce'), 'error');
                return false;
            }
            
            return true;
        }
        
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            
            $card_number = sanitize_text_field($_POST['cielo_card_number']);
            $card_holder = sanitize_text_field($_POST['cielo_card_holder']);
            $card_expiry = sanitize_text_field($_POST['cielo_card_expiry']);
            $card_cvc = sanitize_text_field($_POST['cielo_card_cvc']);
            $installments = intval($_POST['cielo_installments']);
            
            $card_number = preg_replace('/\s+/', '', $card_number);
            $expiry_parts = explode('/', $card_expiry);
            $expiry_month = $expiry_parts[0];
            $expiry_year = '20' . $expiry_parts[1];
            
            $brand = $this->get_card_brand($card_number);
            
            $api_url = $this->testmode 
                ? 'https://apisandbox.cieloecommerce.cielo.com.br/1/sales/' 
                : 'https://api.cieloecommerce.cielo.com.br/1/sales/';
            
            $body = array(
                'MerchantOrderId' => $order_id,
                'Customer' => array(
                    'Name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'Email' => $order->get_billing_email(),
                    'Identity' => preg_replace('/[^0-9]/', '', $order->get_meta('_billing_cpf')),
                    'IdentityType' => 'CPF'
                ),
                'Payment' => array(
                    'Type' => 'CreditCard',
                    'Amount' => intval($order->get_total() * 100),
                    'Installments' => $installments,
                    'Capture' => true,
                    'SoftDescriptor' => substr(get_bloginfo('name'), 0, 13),
                    'CreditCard' => array(
                        'CardNumber' => $card_number,
                        'Holder' => $card_holder,
                        'ExpirationDate' => $expiry_month . '/' . $expiry_year,
                        'SecurityCode' => $card_cvc,
                        'Brand' => $brand
                    )
                )
            );
            
            // Adiciona código do estabelecimento se configurado
            if (!empty($this->establishment_code)) {
                $body['Payment']['Provider'] = 'Cielo';
                $body['Payment']['Credentials'] = array(
                    'Code' => $this->establishment_code
                );
            }
            
            $response = wp_remote_post($api_url, array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'MerchantId' => $this->merchant_id,
                    'MerchantKey' => $this->merchant_key
                ),
                'body' => json_encode($body),
                'timeout' => 70
            ));
            
            if (is_wp_error($response)) {
                // Falha de comunicação: log + nota no pedido + retornar erro ao checkout
                $this->cielo_log( 'Erro de comunicação com Cielo: ' . $response->get_error_message() );
                $order->add_order_note( 'Erro ao processar pagamento (Cielo): ' . $response->get_error_message() );
                wc_add_notice( 'Erro ao processar pagamento. Tente novamente ou entre em contato.', 'error' );
                return;
            }
            
            $code = wp_remote_retrieve_response_code( $response );

            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ( $this->enable_debug ) {
                $this->cielo_log( "Resposta Cielo HTTP {$code} : " . $response_body, 'info' );
            }

            if (isset($response_body['Payment']['Status'])) {
                $status = $response_body['Payment']['Status'];
                $payment_id = $response_body['Payment']['PaymentId'];
                
                $order->update_meta_data('_cielo_payment_id', $payment_id);
                $order->save();
                
                if ($status == 1 || $status == 2) {
                    $order->payment_complete($payment_id);
                    $order->add_order_note(
                        sprintf(__('Pagamento aprovado via Cielo. ID da Transação: %s', 'cielo-ecommerce'), $payment_id)
                    );
                    
                    WC()->cart->empty_cart();
                    
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                } else {
                    $error_message = $this->get_status_message($status);
                    $order->update_status('failed', $error_message);
                    $this->cielo_log($error_message,);
                    wc_add_notice($error_message, 'error');
                }
            } else {
                $error_message = isset($response_body[0]['Message']) 
                    ? $response_body[0]['Message'] 
                    : __('Erro ao processar pagamento', 'cielo-ecommerce');
                wc_add_notice($error_message, 'error');
            }
        }
        
        private function get_card_brand($card_number) {
            $card_number = preg_replace('/\s+/', '', $card_number);
            
            if (preg_match('/^4/', $card_number)) {
                return 'Visa';
            } elseif (preg_match('/^5[1-5]/', $card_number)) {
                return 'Master';
            } elseif (preg_match('/^3[47]/', $card_number)) {
                return 'Amex';
            } elseif (preg_match('/^6(?:011|5)/', $card_number)) {
                return 'Discover';
            } elseif (preg_match('/^36|38/', $card_number)) {
                return 'Diners';
            } elseif (preg_match('/^35/', $card_number)) {
                return 'JCB';
            } elseif (preg_match('/^606282|^3841/', $card_number)) {
                return 'Hipercard';
            } elseif (preg_match('/^636368|^438935|^504175|^451416|^636297/', $card_number)) {
                return 'Elo';
            }
            
            return 'Visa';
        }
        
        private function get_status_message($status) {
            $messages = array(
                0 => __('Pagamento não finalizado', 'cielo-ecommerce'),
                1 => __('Pagamento autorizado', 'cielo-ecommerce'),
                2 => __('Pagamento confirmado', 'cielo-ecommerce'),
                3 => __('Pagamento negado', 'cielo-ecommerce'),
                10 => __('Pagamento cancelado', 'cielo-ecommerce'),
                11 => __('Pagamento estornado', 'cielo-ecommerce'),
                12 => __('Aguardando retorno', 'cielo-ecommerce'),
                13 => __('Pagamento abortado', 'cielo-ecommerce')
            );
            
            return isset($messages[$status]) ? $messages[$status] : __('Status desconhecido', 'cielo-ecommerce');
        }

        private function cielo_log( $message, $level = 'error' ) {
            if ( ! function_exists( 'wc_get_logger' ) ) return;
            $logger = wc_get_logger();
            $context = array( 'source' => 'cielo-ecommerce' ); // gerará log com prefixo "cielo-ecommerce-YYYY-MM-DD.log"
            // shortcut methods
            if ( method_exists( $logger, $level ) ) {
                $logger->{$level}( $message, $context );
            } else {
                $logger->log( $level, $message, $context );
            }
        }
    }
}

function add_cielo_gateway_class($gateways) {
    $gateways[] = 'WC_Cielo_Gateway';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'add_cielo_gateway_class');