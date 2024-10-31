<?php

add_action('woocommerce_checkout_process', 'process_pixxpay_payment', 10);
function process_pixxpay_payment(){
    if($_POST['payment_method'] != 'pixxpayatpa'){
        return;
    }

    if (class_exists( 'Extra_Checkout_Fields_For_Brazil' )){
        if(isset($_POST['billing_cpf']) AND strlen($_POST['billing_cpf']) != 0){
            $vat = preg_replace("/[^0-9]/", "", filter_var($_POST['billing_cpf'], FILTER_SANITIZE_STRING));
        }
		if(isset($_POST['billing_cnpj']) AND strlen($_POST['billing_cnpj']) != 0){
			if($_POST['billing_persontype'] == 2){
                $vat = preg_replace("/[^0-9]/", "", filter_var($_POST['billing_cnpj'], FILTER_SANITIZE_STRING));
			}
        }
    }else{
        if(strlen($_POST['billing_first_name']) > 0 AND strlen($_POST['billing_last_name']) > 0){
            $nameCustomer = $_POST['billing_first_name'].' '.$_POST['billing_last_name'];
        }else{
            $nameCustomer = $_POST['shipping_first_name'].' '.$_POST['shipping_last_name'];
        }
        ( isset($_POST['vat_u4'])
            AND strlen($_POST['vat_u4']) != 0
        ) ?
            $vat = preg_replace("/[^0-9]/", "", sanitize_text_field( $_POST['vat_u4'] )) :
            false;
    }

    if($vat == false OR strlen($vat) == 0 OR $vat == NULL OR $vat == '' OR $vat == ' ' OR (strlen($vat) > 14 OR strlen($vat) < 11)){
        echo $vat;
        wc_add_notice( sprintf( '<strong>CNPJ ou CPF</strong> é obrigatório. '.$vat ), 'error' );
        return false; exit;
    }
}

add_action('woocommerce_checkout_update_order_meta', 'pixxpay_atpa_payment_update_order_meta');
function pixxpay_atpa_payment_update_order_meta($order_id) {

    if($_POST['payment_method'] != 'pixxpayatpa'){
        return;
    }

    $setings = wc_get_payment_gateway_by_order( $order_id );
    /**remover os caracretes amp; de pixxpayatpaclientId */
    $clientId = str_replace('amp;', '', $setings->settings['pixxpayatpaclientId']);
    $clientSecret = str_replace('amp;', '', $setings->settings['pixxpayatpaclientSecret']);

    $order = wc_get_order( $order_id );

    if (class_exists( 'Extra_Checkout_Fields_For_Brazil' )){
        if(isset($_POST['billing_cpf']) AND strlen($_POST['billing_cpf']) != 0){
            $vat = preg_replace("/[^0-9]/", "", filter_var($_POST['billing_cpf'], FILTER_SANITIZE_STRING));

            if(strlen($order->get_billing_first_name()) > 0 AND strlen($order->get_billing_last_name()) > 0){
                $nameCustomer = $order->get_billing_first_name().' '.$order->get_billing_last_name();
            }else{
                $nameCustomer = $order->get_shipping_first_name().' '.$order->get_shipping_last_name();
            }
        }
		if(isset($_POST['billing_cnpj']) AND strlen($_POST['billing_cnpj']) != 0){
			if($_POST['billing_persontype'] == 2){
                $vat = preg_replace("/[^0-9]/", "", filter_var($_POST['billing_cnpj'], FILTER_SANITIZE_STRING));
			    $nameCustomer = filter_var($_POST['billing_company'], FILTER_SANITIZE_STRING);
			}
        }
    }else{
        if(strlen($order->get_billing_first_name()) > 0 AND strlen($order->get_billing_last_name()) > 0){
            $nameCustomer = $order->get_billing_first_name().' '.$order->get_billing_last_name();
        }else{
            $nameCustomer = $order->get_shipping_first_name().' '.$order->get_shipping_last_name();
        }
        ( isset($_POST['vat_u4'])
            AND strlen($_POST['vat_u4']) != 0
        ) ?
            $vat = preg_replace("/[^0-9]/", "", sanitize_text_field( $_POST['vat_u4'] )) :
            false;
    }

    if($vat == false OR strlen($vat) == 0 OR $vat == NULL OR $vat == '' OR $vat == ' ' OR (strlen($vat) > 14 OR strlen($vat) < 11)){
        wc_add_notice( sprintf( '<strong>CNPJ ou CPF</strong> é obrigatório.' ), 'error' );
       return false; exit;
    }

    $verify = md5( $order->get_id() );
    //setcookie('pixxpayatpa', $verify, time() + 250, '/');
    $redirect = $order->get_checkout_order_received_url().'&itpverify='.$verify;
    /**se o $vat for CNPJ(>11)  mudar de user para businessEntity*/
    if(strlen($vat) > 11){
        $payload = [
            "clientId" => $clientId,
            "clientSecret" => $clientSecret,
            "payment" => [
                "redirectURL" => $redirect,
                "businessEntity" => [
                    "name" => $nameCustomer,
                    "taxId" => preg_replace("/[^0-9]/", "", $vat),
                ],
                'amount' => (int)preg_replace("/[^0-9]/", "", $order->get_total()),
            ],
        ];
    }else{
        $payload = [
            "clientId" => $clientId,
            "clientSecret" => $clientSecret,
            "payment" => [
                "redirectURL" => $redirect,
                "user" => [
                    "name" => $nameCustomer,
                    "taxId" => preg_replace("/[^0-9]/", "", $vat),
                ],
                'amount' => (int)preg_replace("/[^0-9]/", "", $order->get_total()),
            ],
        ];
    }
    $payJson = json_encode($payload);
    $payFinal = str_replace('\/', '/', $payJson);

    ($setings->settings['pixxpayenvironment'] === "0")? $urlBillet = PIXXPAY_PRO : $urlBillet = PIXXPAY_DEV;
    //$urlBillet = "https://consumer.sandbox.u4c-iniciador.com.br/v1/auth/interface";

    $argsBillet = array(
        'timeout'       => 30,
        'method'        => 'POST',
        'httpversion'   => '1.1',
        'body'          => $payFinal,
        'headers'       => [
            "Content-Type" => "application/json"
        ],
    );


    $http = _wp_http_get_object();
    $tuBillet = $http->post($urlBillet."/auth/interface", $argsBillet);
    $response = json_decode( sanitize_text_field( $tuBillet['body'] ) );

    if( $response->interfaceURL ){
        update_post_meta( $order_id, 'pixxpayinterfaceURL', $response->interfaceURL );
        update_post_meta( $order_id, 'pixxpayaccessToken', $response->accessToken );
        update_post_meta( $order_id, 'pixxpayPaymentId', $response->paymentId );
    }else{

        /**Creat archive of error */
        $content = json_encode( ["error" => $response, "data" => $payload], true );
        WC_Gateway_PixxPay_Atpa::pixxpay_registro_logs_atpa( $tuBillet["response"]["message"].": ".$tuBillet["response"]["code"]." - Item: Novo pedido por ITP - Usuário "." - Resposta: ".$content, $order_id );
    }
}
