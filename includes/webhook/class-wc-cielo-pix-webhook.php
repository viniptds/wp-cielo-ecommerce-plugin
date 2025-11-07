
<?php

if ( ! defined( 'ABSPATH' ) ) exit;

// ======================================================
// 1️⃣ - REGISTRA A ROTA REST API
// ======================================================

add_action( 'rest_api_init', function () {
    register_rest_route( 'cielo/v1', '/webhook', array(
        'methods'  => 'POST',
        'callback' => 'cielo_handle_webhook',
        'permission_callback' => '__return_true', // cuidado: pode adicionar autenticação depois
    ));
});

// ======================================================
// 2️⃣ - FUNÇÃO DE CALLBACK DO WEBHOOK
// ======================================================
function cielo_handle_webhook( WP_REST_Request $request ) {
    $body = $request->get_json_params();

    if ( empty( $body ) ) {
        return new WP_REST_Response( array(
            'success' => false,
            'message' => 'JSON vazio ou inválido'
        ), 400 );
    }
  // Exemplo de payload vindo da Cielo (ajuste conforme sua integração)
    // {
    //   "MerchantOrderId": "12345",
    //   "Payment": {
    //     "Status": 2,
    //     "Tid": "123456789",
    //     "PaymentId": "uuid"
    //   }
    // }

    // ======================================================
    // 3️⃣ - ENCONTRA O PEDIDO PELO MerchantOrderId
    // ======================================================
    $order_id = isset( $body['MerchantOrderId'] ) ? intval( $body['MerchantOrderId'] ) : 0;
    if ( ! $order_id ) {
        return new WP_REST_Response( array(
            'success' => false,
            'message' => 'MerchantOrderId ausente'
        ), 400 );
    }

    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return new WP_REST_Response( array(
            'success' => false,
            'message' => 'Pedido não encontrado'
        ), 404 );
    }

    // ======================================================
    // 4️⃣ - INTERPRETA O STATUS DO PAGAMENTO
    // ======================================================
    $payment_status = isset( $body['Payment']['Status'] ) ? intval( $body['Payment']['Status'] ) : null;

    // Status 2 na Cielo = Pagamento Confirmado
    if ( $payment_status === 2 ) {
        if ( $order->get_status() !== 'completed' ) {
            $order->payment_complete( $body['Payment']['PaymentId'] ?? '' );
            $order->add_order_note( 'Pagamento confirmado via Webhook Cielo. TID: ' . ( $body['Payment']['Tid'] ?? '' ) );
        }
    }
    // Status 1 = Autorizado, mas não capturado ainda (exemplo)
    elseif ( $payment_status === 1 ) {
        $order->update_status( 'on-hold', 'Pagamento autorizado, aguardando captura.' );
    }
    // Status 3 = Negado
    elseif ( $payment_status === 3 ) {
        $order->update_status( 'failed', 'Pagamento negado pela Cielo.' );
    }

    // ======================================================
    // 5️⃣ - RETORNA HTTP 200
    // ======================================================
    return new WP_REST_Response( array(
        'success' => true,
        'order_id' => $order_id,
        'order_status' => $order->get_status()
    ), 200 );
}