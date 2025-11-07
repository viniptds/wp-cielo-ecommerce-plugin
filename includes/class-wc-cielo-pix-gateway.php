<?php

/**
 * Cielo Pix Payment Gateway
 *
 * Handles all Pix payment logic for Cielo eCommerce 3.0 (Cielo2 provider)
 *
 * @package Cielo_eCommerce
 * @since 1.0.9
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Cielo_Pix_Gateway extends WC_Payment_Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id = 'cielo_pix';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = __('Cielo eCommerce 3.0 - Pix', 'cielo-ecommerce');
        $this->method_description = __('Aceite pagamentos via Pix com Cielo eCommerce 3.0 (Cielo2)', 'cielo-ecommerce');

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
        $this->qrcode_expiration_format = $this->get_option('qrcode_expiration_format', 'minutes');
        $this->qrcode_expiration = $this->get_option('qrcode_expiration', '86400');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_api_cielo_pix_check_payment', array($this, 'check_payment_status'));

        if ($this->enable_debug) {
            $this->cielo_log('Cielo eCommerce 3.0 Pix Gateway iniciado com sucesso.');
        }
    }

    public function validate_description_field($key, $value)
    {
        if (isset($value) && 20 < strlen($value)) {
            WC_Admin_Settings::add_error(esc_html__('Looks like you made a mistake with the description. Make sure it isn&apos;t longer than 20 characters', 'woocommerce-integration-demo'));
        }

        return $value;
    }

    /**
     * Initialize gateway settings form fields
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Habilitar/Desabilitar', 'cielo-ecommerce'),
                'type' => 'checkbox',
                'label' => __('Habilitar Cielo eCommerce - Pix', 'cielo-ecommerce'),
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
                'default' => __('Pagamento instantâneo via Pix.', 'cielo-ecommerce'),
            ),
            'testmode' => array(
                'title' => __('Modo de Teste', 'cielo-ecommerce'),
                'type' => 'checkbox',
                'label' => __('Habilitar modo de teste', 'cielo-ecommerce'),
                'default' => 'no',
                'description' => __('Atenção: Sandbox ainda não está disponível para Pix Cielo2. Use apenas em produção.', 'cielo-ecommerce'),
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
            'qrcode_expiration' => array(
                'title' => __('Validade do QR Code', 'cielo-ecommerce'),
                'type' => 'number',
                'description' => __('Tempo de validade do QR Code em segundos. Padrão: 86400 (24 horas).', 'cielo-ecommerce'),
                'default' => '24',
                'desc_tip' => true,
            ),
            'qrcode_expiration_format' => array(
                'title' => __('Unidade de medida da Validade do QR Code', 'cielo-ecommerce'),
                'type' => 'select',
                'options' => array(
                    'minutes' => __('Minutos', 'cielo-ecommerce'),
                    'hours' => __('Horas', 'cielo-ecommerce'),
                ),
                'description' => __('Tempo de validade do QR Code em segundos. Padrão: horas.', 'cielo-ecommerce'),
                'default' => 'hours',
                'desc_tip' => true,
                're'
            ),
            'enable_debug' => array(
                'title' => __('Ativar Debug', 'cielo-ecommerce'),
                'type' => 'checkbox',
                'description' => __('Ativa logs de debug', 'cielo-ecommerce'),
                'default' => 'no',
            ),
        );
    }

    /**
     * Enqueue payment scripts
     */
    public function payment_scripts()
    {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order']) && !is_order_received_page()) {
            return;
        }

        if ('no' === $this->enabled) {
            return;
        }

        if (empty($this->merchant_id) || empty($this->merchant_key)) {
            return;
        }

        wp_enqueue_script('cielo-pix-payment', plugins_url('../assets/js/cielo-pix-payment.js', __FILE__), array('jquery'), '1.0.0', true);

        wp_localize_script('cielo-pix-payment', 'cielo_pix_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'check_payment_url' => WC()->api_request_url('cielo_pix_check_payment')
        ));
    }

    /**
     * Display payment fields on checkout page
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        echo '<style>
            .cielo-pix-info {
                background: #f0f8ff;
                border: 1px solid #0071ce;
                border-radius: 4px;
                padding: 15px;
                margin: 10px 0;
            }
            .cielo-pix-icon {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                font-weight: bold;
                color: #32bcad;
            }
            .cielo-pix-icon:before {
                content: "◈";
                font-size: 24px;
            }
        </style>';

        echo '<fieldset id="wc-' . esc_attr($this->id) . '-pix-form" class="wc-payment-form">';
        echo '<div class="cielo-pix-info">';
        echo '<div class="cielo-pix-icon">' . __('Pix - Pagamento Instantâneo', 'cielo-ecommerce') . '</div>';
        echo '<p>' . __('Ao finalizar o pedido, você receberá um QR Code para realizar o pagamento via Pix.', 'cielo-ecommerce') . '</p>';
        echo '<p>' . __('O pagamento é aprovado instantaneamente após a confirmação.', 'cielo-ecommerce') . '</p>';
        echo '</div>';
        echo '</fieldset>';
    }

    /**
     * Validate payment fields
     *
     * @return bool
     */
    public function validate_fields()
    {
        // Pix doesn't require additional fields during checkout
        return true;
    }

    /**
     * Process payment
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $api_url = $this->get_api_url();
        $body = $this->build_payment_request($order);
        $response = $this->send_payment_request($api_url, $body);

        return $this->process_payment_response($response, $order);
    }

    /**
     * Get API URL based on environment
     *
     * @return string
     */
    private function get_api_url()
    {
        return $this->testmode
            ? 'https://apisandbox.cieloecommerce.cielo.com.br/1/sales/'
            : 'https://api.cieloecommerce.cielo.com.br/1/sales/';
    }

    /**
     * Build payment request body for Pix
     *
     * @param WC_Order $order
     * @return array
     */
    private function build_payment_request($order)
    {
        // Get customer CPF/CNPJ from billing fields
        $cpf_cnpj = $this->get_customer_document($order);
        $identity_type = strlen($cpf_cnpj) > 11 ? 'CNPJ' : 'CPF';

        $expiration_time = $this->qrcode_expiration_format === 'hours'
            ? intval($this->qrcode_expiration) * 3600
            : intval($this->qrcode_expiration) * 60;

        // Set default expiration time to 1 hour
        if ($expiration_time <= 0) {
            $expiration_time = 3600;
        }
        $body = array(
            'MerchantOrderId' => $order->get_id(),
            'Customer' => array(
                'Name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'Identity' => $cpf_cnpj,
                'IdentityType' => $identity_type,
            ),
            'Payment' => array(
                'Type' => 'Pix',
                'Provider' => 'Cielo2',
                'Amount' => intval($order->get_total() * 100),
                'QrCode' => array(
                    'Expiration' => $expiration_time
                )
            )
        );

        return $body;
    }

    /**
     * Get customer CPF/CNPJ document
     *
     * @param WC_Order $order
     * @return string
     */
    private function get_customer_document($order)
    {
        // Try common field names for CPF/CNPJ
        $cpf_cnpj = $order->get_meta('_billing_cpf');
        if (empty($cpf_cnpj)) {
            $cpf_cnpj = $order->get_meta('_billing_cnpj');
        }
        if (empty($cpf_cnpj)) {
            $cpf_cnpj = $order->get_meta('billing_cpf');
        }
        if (empty($cpf_cnpj)) {
            $cpf_cnpj = $order->get_meta('billing_cnpj');
        }

        // Remove non-numeric characters
        $cpf_cnpj = preg_replace('/[^0-9]/', '', $cpf_cnpj);

        // If still empty, use a default value (should be validated on checkout)
        if (empty($cpf_cnpj)) {
            $cpf_cnpj = '00000000000';
        }

        return $cpf_cnpj;
    }

    /**
     * Send payment request to Cielo API
     *
     * @param string $api_url
     * @param array $body
     * @return array|WP_Error
     */
    private function send_payment_request($api_url, $body)
    {
        $this->cielo_log("URL: " . $api_url, 'info');

        if ($this->enable_debug) {
            $this->cielo_log("Requisição Cielo Pix: " . json_encode($body), 'info');
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

        return $response;
    }

    /**
     * Query Cielo transaction status
     *
     * @param string $payment_id
     * @return array|WP_Error
     */
    private function query_transaction_status($payment_id)
    {
        $query_url = $this->get_query_api_url() . $payment_id;

        $this->cielo_log("Consultando status da transação: " . $query_url, 'info');

        $response = wp_remote_get($query_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'MerchantId' => $this->merchant_id,
                'MerchantKey' => $this->merchant_key
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            $this->cielo_log('Erro ao consultar transação: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($this->enable_debug) {
            $this->cielo_log("Resposta consulta Pix HTTP {$code}: " . json_encode($response_body), 'info');
        }

        return array(
            'code' => $code,
            'body' => $response_body
        );
    }

    /**
     * Get Query API URL based on environment
     *
     * @return string
     */
    private function get_query_api_url()
    {
        return $this->testmode
            ? 'https://apiquerysandbox.cieloecommerce.cielo.com.br/1/sales/'
            : 'https://apiquery.cieloecommerce.cielo.com.br/1/sales/';
    }

    /**
     * Process payment response from Cielo
     *
     * @param array|WP_Error $response
     * @param WC_Order $order
     * @return array|void
     */
    private function process_payment_response($response, $order)
    {
        if (is_wp_error($response)) {
            $this->cielo_log('Erro de comunicação com Cielo Pix: ' . $response->get_error_message());
            $order->add_order_note('Erro ao processar pagamento Pix (Cielo): ' . $response->get_error_message());
            wc_add_notice('Erro ao processar pagamento. Tente novamente ou entre em contato.', 'error');
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        $this->cielo_log("Resposta Cielo Pix HTTP {$code} : " . json_encode($response_body), 'info');

        if (isset($response_body['Payment']['Status'])) {
            $status = $response_body['Payment']['Status'];
            $payment_id = $response_body['Payment']['PaymentId'];

            // Store Pix data in order meta
            $order->update_meta_data('_cielo_pix_payment_id', $payment_id);

            if (isset($response_body['Payment']['QrCodeBase64Image'])) {
                $order->update_meta_data('_cielo_pix_qrcode_image', $response_body['Payment']['QrCodeBase64Image']);
            }

            if (isset($response_body['Payment']['QrCodeString'])) {
                $order->update_meta_data('_cielo_pix_qrcode_string', $response_body['Payment']['QrCodeString']);
            }

            if (isset($response_body['Payment']['SentOrderId'])) {
                $order->update_meta_data('_cielo_pix_txid', $response_body['Payment']['SentOrderId']);
            }

            $order->save();

            // Status 12 = Pending (waiting for payment)
            if ($status == 12) {
                $order->update_status('on-hold', __('Aguardando pagamento via Pix.', 'cielo-ecommerce'));
                $order->add_order_note(
                    sprintf(__('QR Code Pix gerado com sucesso. ID da Transação: %s', 'cielo-ecommerce'), $payment_id)
                );

                WC()->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } elseif ($status == 2) {
                // Status 2 = Paid
                $order->payment_complete($payment_id);
                $order->add_order_note(
                    sprintf(__('Pagamento Pix confirmado. ID da Transação: %s', 'cielo-ecommerce'), $payment_id)
                );

                WC()->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else {
                $error_message = $this->get_status_message($status);
                $order->update_status('failed', $error_message);
                $this->cielo_log($error_message);
                wc_add_notice($error_message, 'error');
            }
        } else {
            $error_message = isset($response_body[0]['Message'])
                ? $response_body[0]['Message']
                : __('Erro ao gerar QR Code Pix', 'cielo-ecommerce');
            wc_add_notice($error_message, 'error');
            $this->cielo_log("Erro ao gerar Pix: " . $error_message);
        }
    }

    /**
     * Display Pix QR Code on thank you page
     *
     * @param int $order_id
     */
    public function thankyou_page($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $qrcode_image = $order->get_meta('_cielo_pix_qrcode_image');
        $qrcode_string = $order->get_meta('_cielo_pix_qrcode_string');
        $payment_id = $order->get_meta('_cielo_pix_payment_id');

        if (empty($qrcode_image) || empty($qrcode_string)) {
            return;
        }

        // Only show if order is pending payment
        if (!in_array($order->get_status(), array('on-hold', 'pending'))) {
            return;
        }

        echo '<style>
            .bold {
                font-weight: bold;
            }
            .cielo-pix-container {
                background: #fff;
                border: 2px solid #32bcad;
                border-radius: 8px;
                padding: 30px;
                margin: 20px 0;
                text-align: center;
            }
            .cielo-pix-title {
                font-size: 24px;
                font-weight: bold;
                color: #32bcad;
                margin-bottom: 10px;
            }
            .cielo-pix-instructions {
                font-size: 16px;
                color: #666;
                margin-bottom: 20px;
            }
            .cielo-pix-qrcode {
                margin: 20px auto;
                max-width: 300px;
            }
            .cielo-pix-qrcode img {
                width: 100%;
                height: auto;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .cielo-pix-code {
                background: #f5f5f5;
                border: 1px dashed #ccc;
                border-radius: 4px;
                padding: 15px;
                margin: 20px 0;
                word-break: break-all;
                font-family: monospace;
                font-size: 12px;
            }
            .cielo-pix-copy-btn {
                background: #32bcad;
                color: white;
                border: none;
                border-radius: 4px;
                padding: 12px 30px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                transition: background 0.3s;
            }
            .cielo-pix-copy-btn:hover {
                background: #2a9d8f;
            }
            .cielo-pix-copy-btn.copied {
                background: #28a745;
            }
            .cielo-pix-timer {
                margin-top: 20px;
                font-size: 14px;
                color: #666;
            }
            .cielo-pix-status {
                margin-top: 20px;
                padding: 10px;
                border-radius: 4px;
                font-weight: bold;
            }
            .cielo-pix-status.checking {
                background: #fff3cd;
                color: #856404;
            }
            .cielo-pix-status.paid {
                background: #d4edda;
                color: #155724;
            }
        </style>';

        echo '<div class="cielo-pix-container" data-order-id="' . esc_attr($order_id) . '" data-payment-id="' . esc_attr($payment_id) . '">';
        echo '<div class="cielo-pix-title">🔷 ' . __('Pagamento via Pix', 'cielo-ecommerce') . '</div>';
        echo '<div class="cielo-pix-instructions">' . __('Escaneie o QR Code abaixo com o app do seu banco ou copie o código Pix para realizar o pagamento.', 'cielo-ecommerce') . '</div>';

        echo '<div class="cielo-pix-qrcode">';
        echo '<img src="data:image/png;base64,' . esc_attr($qrcode_image) . '" alt="QR Code Pix" />';
        echo '</div>';

        echo '<div class="cielo-pix-code">' . esc_html($qrcode_string) . '</div>';

        echo '<button type="button" class="cielo-pix-copy-btn" data-code="' . esc_attr($qrcode_string) . '">';
        echo __('📋 Copiar Código Pix', 'cielo-ecommerce');
        echo '</button>';

        echo '<div class="cielo-pix-timer">';

        echo __('⏱️ Este QR Code expira em ', 'cielo-ecommerce');
        echo '<span id="dateClock" class="bold" date-origin="' . $order->get_date_created() . '"
            date-format="' . $this->qrcode_expiration_format . '" date-period="' . $this->qrcode_expiration . '"></span>';

        echo '</div>';

        echo '<div class="cielo-pix-status checking">';
        echo __('🔄 Verificando pagamento automaticamente...', 'cielo-ecommerce');
        echo '</div>';

        echo '</div>';
    }

    /**
     * Check payment status via AJAX
     */
    public function check_payment_status()
    {
        if (!isset($_GET['order_id'])) {
            wp_send_json_error(array('message' => 'Order ID não fornecido'));
        }

        $order_id = intval($_GET['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(array('message' => 'Pedido não encontrado'));
        }

        // Check if order is already paid
        $current_status = $order->get_status();
        if (in_array($current_status, array('processing', 'completed'))) {
            wp_send_json_success(array(
                'paid' => true,
                'status' => $current_status,
                'message' => __('Pagamento confirmado!', 'cielo-ecommerce')
            ));
            return;
        }

        // Get payment ID from order meta
        $payment_id = $order->get_meta('_cielo_pix_payment_id');

        if (empty($payment_id)) {
            wp_send_json_error(array('message' => 'Payment ID não encontrado'));
            return;
        }

        // Query Cielo API for transaction status
        $query_result = $this->query_transaction_status($payment_id);

        if (is_wp_error($query_result)) {
            // If query fails, return current order status
            wp_send_json_success(array(
                'paid' => false,
                'status' => $current_status,
                'message' => __('Aguardando pagamento...', 'cielo-ecommerce'),
                'error' => $query_result->get_error_message()
            ));
            return;
        }

        // Process the query response
        $response_body = $query_result['body'];
        $response_code = $query_result['code'];

        if ($response_code != 200 || !isset($response_body['Payment']['Status'])) {
            // Invalid response, return current status
            wp_send_json_success(array(
                'paid' => false,
                'status' => $current_status,
                'message' => __('Aguardando pagamento...', 'cielo-ecommerce')
            ));
            return;
        }

        // Get transaction status from Cielo
        $cielo_status = $response_body['Payment']['Status'];

        // Update order based on Cielo status (same logic as process_payment_response)
        $order_updated = $this->update_order_status_from_cielo($order, $cielo_status, $payment_id, $response_body);

        if ($order_updated) {
            // Reload order to get updated status
            $order = wc_get_order($order_id);
            $new_status = $order->get_status();

            if (in_array($new_status, array('processing', 'completed'))) {
                wp_send_json_success(array(
                    'paid' => true,
                    'status' => $new_status,
                    'message' => __('Pagamento confirmado!', 'cielo-ecommerce')
                ));
            } else {
                wp_send_json_success(array(
                    'paid' => false,
                    'status' => $new_status,
                    'message' => __('Aguardando pagamento...', 'cielo-ecommerce')
                ));
            }
        } else {
            // No update needed, return current status
            wp_send_json_success(array(
                'paid' => false,
                'status' => $current_status,
                'message' => __('Aguardando pagamento...', 'cielo-ecommerce')
            ));
        }
    }

    /**
     * Update order status based on Cielo transaction status
     *
     * @param WC_Order $order
     * @param int $cielo_status
     * @param string $payment_id
     * @param array $response_body
     * @return bool Whether the order was updated
     */
    private function update_order_status_from_cielo($order, $cielo_status, $payment_id, $response_body = array())
    {
        $current_status = $order->get_status();
        $updated = false;

        // Status 2 = Paid/Confirmed
        if ($cielo_status == 2) {
            // Only update if not already processing or completed
            if (!in_array($current_status, array('processing', 'completed'))) {
                $order->payment_complete($payment_id);

                // Store EndToEndId if available
                if (isset($response_body['Payment']['EndToEndId'])) {
                    $order->update_meta_data('_cielo_pix_end_to_end_id', $response_body['Payment']['EndToEndId']);
                }

                // Store AcquirerOrderId (txid) if available
                if (isset($response_body['AcquirerOrderId'])) {
                    $order->update_meta_data('_cielo_pix_acquirer_order_id', $response_body['AcquirerOrderId']);
                }

                $order->add_order_note(
                    sprintf(__('Pagamento Pix confirmado via consulta. ID da Transação: %s', 'cielo-ecommerce'), $payment_id)
                );

                $order->save();
                $updated = true;

                $this->cielo_log("Pedido #{$order->get_id()} atualizado para pago. Status Cielo: {$cielo_status}", 'info');
            }
        }
        // Status 12 = Pending
        elseif ($cielo_status == 12) {
            // Keep as on-hold if not already
            if (!in_array($current_status, array('on-hold', 'processing', 'completed'))) {
                $order->update_status('on-hold', __('Aguardando pagamento via Pix.', 'cielo-ecommerce'));
                $order->save();
                $updated = true;
            }
        }
        // Status 3 = Denied
        elseif ($cielo_status == 3) {
            if (!in_array($current_status, array('failed', 'cancelled', 'processing', 'completed'))) {
                $error_message = $this->get_status_message($cielo_status);
                $order->update_status('failed', $error_message);
                $order->save();
                $updated = true;

                $this->cielo_log("Pedido #{$order->get_id()} marcado como falho. Status Cielo: {$cielo_status}");
            }
        }
        // Status 10 = Cancelled
        elseif ($cielo_status == 10) {
            if (!in_array($current_status, array('cancelled', 'processing', 'completed'))) {
                $error_message = $this->get_status_message($cielo_status);
                $order->update_status('cancelled', $error_message);
                $order->save();
                $updated = true;

                $this->cielo_log("Pedido #{$order->get_id()} cancelado. Status Cielo: {$cielo_status}");
            }
        }

        return $updated;
    }

    /**
     * Get status message from Cielo status code
     *
     * @param int $status
     * @return string
     */
    private function get_status_message($status)
    {
        $messages = array(
            0 => __('Pagamento não finalizado', 'cielo-ecommerce'),
            1 => __('Pagamento autorizado', 'cielo-ecommerce'),
            2 => __('Pagamento confirmado', 'cielo-ecommerce'),
            3 => __('Pagamento negado', 'cielo-ecommerce'),
            10 => __('Pagamento cancelado', 'cielo-ecommerce'),
            11 => __('Pagamento estornado', 'cielo-ecommerce'),
            12 => __('Aguardando pagamento', 'cielo-ecommerce'),
            13 => __('Pagamento abortado', 'cielo-ecommerce')
        );

        return isset($messages[$status]) ? $messages[$status] : __('Status desconhecido', 'cielo-ecommerce');
    }

    /**
     * Log messages using WooCommerce logger
     *
     * @param string $message
     * @param string $level
     */
    private function cielo_log($message, $level = 'error')
    {
        if (!function_exists('wc_get_logger')) {
            return;
        }

        $logger = wc_get_logger();
        $context = array('source' => 'cielo-ecommerce-pix');

        if (method_exists($logger, $level)) {
            $logger->{$level}($message, $context);
        } else {
            $logger->log($level, $message, $context);
        }
    }
}
