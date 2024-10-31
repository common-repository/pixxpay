<?php

/**
 * Operate PixxPay on the order screen
 */
add_action( 'woocommerce_order_details_after_order_table', 'pixxpay_atpa_field_display_cust_order_meta', 10, 1 );
function pixxpay_atpa_field_display_cust_order_meta($order){
    $method = get_post_meta( $order->get_id(), '_payment_method', true );
    if($method != 'pixxpayatpa'){
        return;
    }

    $pixxpayinterfaceURL = get_post_meta( $order->get_id(), 'pixxpayinterfaceURL', true );
    $pixxpayaccessToken = get_post_meta( $order->get_id(), 'pixxpayaccessToken', true );
    $pixxpayPaymentId = get_post_meta( $order->get_id(), 'pixxpayPaymentId', true );

    $setings = wc_get_payment_gateway_by_order( $order->get_id() );

    /**Verifica se o status do Pedido é aguardando e faz o processo */
    if($order->get_status() == 'on-hold'){
        /** Wordpress get url site */
        $url = get_site_url();

        echo '<p><strong>'. esc_html( $setings->settings['instructions'] ) .'</strong></p>';
        /** if $order date created - date actual <= 5 minute*/
        $order_date_created = $order->get_date_created();
        $order_date_created = $order_date_created->getTimestamp();
        $date_actual = date('Y-m-d H:i:s');
        $date_actual = strtotime($date_actual);
        if(isset($_GET['itpverify'])){
			echo '<script type="text/javascript">
            jQuery("body").append("<div class=\"block-pixxpay\"><div class=\"pixxpay-load\"><div class=\"pixxpay-img-load c-loader\"></div><p id=\"load-text\">Validando o pagamento</p></div></div>");
            jQuery(document).ready(function($){
                setInterval(function(){
                    $.ajax({
                        url: "'.$url.'/?wc-api=wc_pixxpay&id='.$order->get_id().'",
                        type: "GET",
                        dataType: "json",
                        success: function(response){
                            console.log(response);
                            if(response.status == "PAYMENT_COMPLETED"){
                                $("#load-text").innerHTML = "Pagamento concluído com sucesso!";
                                setTimeout(function(){
                                    window.location.href = "'.$order->get_checkout_order_received_url().'";
                                }, 6000);
                            }
                        }
                    });
                }, 2000);
            });
            </script>';
		}else{
            if(isset($_COOKIE['pixxpayatpa'])){
                //echo '<p><a href="'. esc_url( $pixxpayinterfaceURL ). '" target="_blank">Finalizar o pagamento</a></p>';
            }

            /** Gera um novo Token e redireciona o usuário */
            if(
                isset($_GET['atpa']) &&
                filter_var($_GET['atpa'], FILTER_SANITIZE_STRING) == 'new'
            ){
                /** Pagamento expirado, refazer o registro */
                $setings = wc_get_payment_gateway_by_order( $order->get_id() );
                $clientId = $setings->settings['pixxpayatpaclientId'];
                $clientSecret = $setings->settings['pixxpayatpaclientSecret'];
                $nameCustomer = $order->get_billing_first_name().' '.$order->get_billing_last_name();

                /** vat - cpf - cnpj */
                if(strlen(get_post_meta( $order->get_id(), '_billing_cpf', true )) > 0){
                    $vat = preg_replace("/[^0-9]/", "", get_post_meta( $order->get_id(), '_billing_cpf', true ));
                }else{
                    $vat = preg_replace("/[^0-9]/", "", get_post_meta( $order->get_id(), '_billing_cnpj', true ));
                }

                $verify = md5( $order->get_id() );
                setcookie('pixxpayatpa', $verify, time() + 250, '/');
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

                //$urlBillet = "https://consumer.sandbox.u4c-iniciador.com.br/v1/auth/interface";
                ($setings->settings['pixxpayenvironment'] === "0")? $urlBillet = PIXXPAY_PRO : $urlBillet = PIXXPAY_DEV;

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
                if( isset($response->interfaceURL) ){
                    update_post_meta( $order->get_id(), 'pixxpayinterfaceURL', $response->interfaceURL );
                    update_post_meta( $order->get_id(), 'pixxpayaccessToken', $response->accessToken );
                    update_post_meta( $order->get_id(), 'pixxpayPaymentId', $response->paymentId );
                    $pixxpayinterfaceURL = $response->interfaceURL;
                    /** Register cookie validate 250 segunds */
                    setcookie('pixxpayatpa', $verify, time() + 250, '/');
                    wp_redirect($response->interfaceURL);
                }
            }


            /** jquery ajax request */
            echo '<script type="text/javascript">
            jQuery("body").append("<div class=\"block-pixxpay\"><div class=\"pixxpay-load\"><div class=\"pixxpay-img-load c-loader\"></div><p id=\"load-text\">Iniciando o processo de pagamento</p></div></div>");
            jQuery(document).ready(function($){
                //Interval 10seg
                setInterval(function(){
                    $.ajax({
                        url: "'.$url.'/?wc-api=wc_pixxpay&id='.$order->get_id().'",
                        type: "GET",
                        dataType: "json",
                        success: function(response){
                            console.log(response);
                            if(response.status == "PAYMENT_COMPLETED"){
                                $("#load-text").innerHTML = "Pagamento concluído com sucesso!";
                                setTimeout(function(){
                                    window.location.href = "'.$order->get_checkout_order_received_url().'";
                                }, 5000);
                            }
                            else if(response.code == 401){
                                window.location.href = "'.$order->get_checkout_order_received_url().'&atpa=new";
                            }
                            else if(response.status == "CONSENT_REJECTED"){
                                window.location.href = "'.$order->get_checkout_order_received_url().'";
                            }
                            else if(response.status == "STARTED" || response.status == "PAYMENT_PENDING" || response.status == "CONSENT_AUTHORIZED" || response.status == "CONSENT_AWAITING_AUTHORIZATION"){
                                /** append gif load in page-wrap class */
                                $("#load-text").innerHTML = "Aguardando o processamento do pagamento";
                                setTimeout(function(){
                                    window.location.href = "'.$pixxpayinterfaceURL.'";
                                }, 1000);
                            }
                        }
                    });
                }, 2000);
            });
            </script>';
        }

        /**register CSS */
        wp_register_style( 'pixxpay-css', plugin_dir_url( __DIR__ ).'assets/css/pixxpay.css', array(), '1.0.0', 'all' );
        wp_enqueue_style( 'pixxpay-css' );

    }

}