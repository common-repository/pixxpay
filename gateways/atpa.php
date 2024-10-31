<?php

/**
 * Custom Payment Gateway.
 *
 * Provides a Custom Payment Gateway, mainly for testing purposes.
 */
add_action('plugins_loaded', 'init_pixxpay_gateway_atpa_class', 0);
function init_pixxpay_gateway_atpa_class(){
    if ( !class_exists( 'WC_Payment_Gateway' ) ) {
        add_action ('admin_notices', 'pixxpay_gateway_atpa_class_wc_notice');
        return;
    }

    class WC_Gateway_PixxPay_Atpa extends WC_Payment_Gateway {

        public $domain;


        /**
         * Constructor for the gateway.
         */
        public function __construct() {

            $this->domain = 'pixxpay_atpa';

            $this->id                 = 'pixxpayatpa';
            $this->icon               = apply_filters('woocommerce_custom_gateway_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __( 'PixxPay - Debito em Conta pelo OpenFinance ', $this->domain );
            $this->method_description =
            sprintf (
                __( 'Aceite pagamentos utilizando o PixxPay.', $this->domain )
            ).
            sprintf (
                __( ' <a href="%s">Testar conexão com o banco.</a>', $this->domain),
                 esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=pixxpayatpa&test=pixxpayapi' ) )
            );

            // https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/
            $this->supports = array(
               'products',
               'subscriptions',
               'subscription_cancellation',
               'subscription_suspension',
               'subscription_reactivation',
               'subscription_amount_changes',
               'subscription_date_changes',
               'subscription_payment_method_change',
               'subscription_payment_method_change_customer',
               'subscription_payment_method_change_admin',
               'multiple_subscriptions',
            );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
            if(isset($_GET['test']) AND $_GET['test'] == 'pixxpayapi'){$this->pixxpay_test_api();}

            // Define user set variables
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->order_status = $this->get_option( 'order_status', 'on-hold' );

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

            // Customer Emails
            //add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

            $plugin = plugin_basename( __FILE__ );
            // Initialize settings.

            add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [ $this, 'scheduled_subscription_payment' ], 10, 2 );
            /**callback */
            add_action( 'woocommerce_api_wc_pixxpay', array( $this, 'webhook') );
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', $this->domain ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable PixxPay', $this->domain ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => __( 'Title', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', $this->domain ),
                    'default'     => __( 'PixxPay', $this->domain ),
                    'desc_tip'    => true,
                ),
                'order_status' => array(
                    'title'       => __( 'Order Status', $this->domain ),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => __( 'Choose whether status you wish after checkout.', $this->domain ),
                    'default'     => 'wc-on-hold',
                    'desc_tip'    => true,
                    'options'     => wc_get_order_statuses()
                ),
                'description' => array(
                    'title'       => __( 'Description', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', $this->domain ),
                    'default'     => __('Payment Information', $this->domain),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __( 'Instructions', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', $this->domain ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'pixxpayatpaclientId' => array(
                    'title'       => __( 'Client ID', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'Para integração com a API da PixxPay deve ser utilizada o Client ID', $this->domain ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'pixxpayatpaclientSecret' => array(
                    'title'       => __( 'Client Secret', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'Para integração com a API da PixxPay deve ser utilizada o Client Secret', $this->domain ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'pixxpayenvironment' => array(
                    'title'       => __( 'Ambiente de produção' ),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => __( 'Should this payment use the Production route?', $this->domain ),
                    'default'     => 'Desativado',
                    'desc_tip'    => true,
                    'options'     => array('Ativado', 'Desativado')
                ),
            );
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ( $this->instructions )
                return wpautop( wptexturize( $this->instructions ) );
        }

        public function payment_fields(){

            if ( $description = $this->get_description() ) {
                echo esc_html( wptexturize( $description ) );
            }
            if (!class_exists( 'Extra_Checkout_Fields_For_Brazil' )){
                ?>
                <div id="custom_input_u4">
                    <div class="form-group">
                        <label>
                            CPF/CNPJ
                            <abbr class="required" title="obrigatório">*</abbr>
                        </label><br/>
                        <input type="number" name="vat_u4" required="required" class="pixxpay-cpf" id="cpf" pattern="/^-?\d+\.?\d*$/" onKeyPress="if(this.value.length==14) return false;">
                    </div>
                </div>
                <?php
            }

        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {

            $order = wc_get_order( $order_id );

            $status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;

            // Set order status
            $order->update_status( $status, __( 'Checkout with PixxPay. ', $this->domain ) );

            // Reduce stock levels
            wc_reduce_stock_levels( $order_id );

            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect'  => $this->get_return_url( $order )
            );
        }

        /**https://wordpress.stackexchange.com/questions/188481/callback-url-in-wordpress */
        public function callback_handler(){
            $raw_post = file_get_contents( 'php://input' );
            //print_r($raw_post);
        }

        public function webhook(){
            $data = json_decode(file_get_contents("php://input"),true);
			$headers = getallheaders();

            if(isset($_GET['id'])){
                $id = sanitize_text_field($_GET['id']);
            }else{
                $id = sanitize_text_field($data['id']);
            }

            /** pedido */
            $setings = wc_get_payment_gateway_by_order( $id );
            $order = wc_get_order( $id );
            if($order){
                /** setings */
                $pixxpayatpaclientId = $setings->settings['pixxpayatpaclientId'];
                $pixxpayatpaclientSecret = $setings->settings['pixxpayatpaclientSecret'];
                $pixxpayenvironment = $setings->settings['pixxpayenvironment'];

                /** Verificação de pagamento GET itpverify */
                ($setings->settings['pixxpayenvironment'] === "0")? $urlBillet = PIXXPAY_PRO : $urlBillet = PIXXPAY_DEV;

                /** dados do pedido */
                $pixxpayinterfaceURL = get_post_meta( $order->get_id(), 'pixxpayinterfaceURL', true );
                $pixxpayaccessToken = get_post_meta( $order->get_id(), 'pixxpayaccessToken', true );
                $pixxpayPaymentId = get_post_meta( $order->get_id(), 'pixxpayPaymentId', true );


                /** Verificação de pagamento GET itpverify */
                ($setings->settings['pixxpayenvironment'] === "0")? $urlBillet = PIXXPAY_PRO : $urlBillet = PIXXPAY_DEV;
                $urlStatus = $urlBillet."/payments/{$pixxpayPaymentId}/status";
                $argsBillet = array(
                        'timeout'       => 30,
                        'method'        => 'GET',
                        'httpversion'   => '1.1',
                        'headers'       => [
                            "Authorization" => "Bearer {$pixxpayaccessToken}",
                            "Content-Type" => "application/json"
                        ],
                    );

                $http = _wp_http_get_object();
                $tuBillet = $http->post($urlStatus, $argsBillet);
                $response = json_decode( sanitize_text_field( $tuBillet['body'] ) );
                //echo PHP_EOL."---------Status----------".PHP_EOL;
                //echo '<pre>';var_dump($response);echo '</pre>';
                //exit;
                /** IF $response == CONSENT_REJECTED, echo não foi possível processar o seu pagamento, elseif $response == PAYMENT_COMPLETED pagamento realizado com sucesso else aguardo o processamento do pagamento */
                if( isset($response->status) ){
                    if($response->status == "PAYMENT_COMPLETED"){
                        /** update payment complet */
                        $order->payment_complete();
                    }
                    // if($response->status == "CONSENT_REJECTED"){
                    //     echo "Não foi possível processar o seu pagamento";
                    // }elseif($response->status == "PAYMENT_PENDING"){
                    //     echo '';
                    // }elseif($response->status == "PAYMENT_COMPLETED"){
                    //     echo "Pagamento realizado com sucesso";
                    //     /** update payment complet */
                    //     $order->payment_complete();
                    // }else{
                    //     echo "Aguardando o processamento do pagamento";
                    // }
                }
                _e(sanitize_text_field( $tuBillet['body']));
                exit;
            }else{
                /**Recebemos um webhook
                * {"id":"0efb38d0-b4c7-4d02-9bdc-6f93b2110b0e","date":"2023-06-06","createdAt":"2023-06-07T00:00:22.402Z","updatedAt":"2023-06-07T00:00:56.401Z","consentId":"urn:raidiambank:5e5832db-49ff-42b0-bc33-3bc289c42d99","status":"CONSENT_AUTHORIZED","amount":133300,"externalId":"a35b6c25-d0ab-4499-89b9-3cb8a3f0ba8c"}
                */
                try {
                    global $wpdb;
                    $meta_key = 'pixxpayPaymentId';
                    $post_id = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = %s AND meta_value = %s",
                            $meta_key,
                            $id
                        )
                    );

                    if($post_id){
                        if($data['status'] == "PAYMENT_COMPLETED"){
                            /**Atualizar o status do pedido */
                            $order = wc_get_order( $post_id );
                            $order->payment_complete();
                        }

                    }else{
                        /**Não encontramos o pedido usando o ID fornecido*/
                        echo "Não encontramos o pedido usando o ID fornecido";
                    }
                } catch (\Throwable $th) {
                    //throw $th;
                }
                exit;
            }
        }

        /**
         * getSettingspixxpayAtpa
         */
        public function getSettingsPixxpayAtpa(){
            global $wpdb;
            $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}options
                WHERE option_name = 'woocommerce_pixxpayatpa_settings'
            ");

            return [
                "settings" => [
                    unserialize( $results[0]->option_value )
                ]
            ];
        }

        /** Registro de Logs Passo a Passo*/
		public static function pixxpay_registro_logs_atpa($content, $ar){
			$archive = fopen(PIXXPAY_PLUGIN_DIR.'pedidos/'.$ar.'.txt', 'a');
            try {
                fwrite($archive, '['.date("Y-m-d H:i:s").'] : '.$content . PHP_EOL);
                fclose($archive);
            } catch (\Throwable $th) {
                // https://developer.wordpress.org/apis/security/escaping/
                echo 'Sem permissões para escrever no arquivo.' . PHP_EOL;
                echo "Arquivo: ".esc_html( $archive ). PHP_EOL;
                echo "Mensagem: ".esc_html( $content ). PHP_EOL;
                throw $th;
                exit;
            }
		}

        /**Recuperar informações da api */
        public static function setingsApi(){
            global $wpdb;
            $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}options
                WHERE option_name = 'woocommerce_pixxpayatpa_settings'
            ");

            return [
                "settings" => [
                    unserialize( $results[0]->option_value )
                ]
            ];

        }

        /**Teste de credenciais */
        public function pixxpay_test_api(){
            $api = $this->setingsApi();

            $pixxpayatpatoken = $api["settings"][0]['pixxpayatpatoken'];
            $pixxpayatpaclientId = $api["settings"][0]['pixxpayatpaclientId'];
            $pixxpayatpaclientSecret = $api["settings"][0]['pixxpayatpaclientSecret'];

            ($api["settings"][0]['pixxpayenvironment'] === "0")? $urlBillet = PIXXPAY_PRO : $urlBillet = PIXXPAY_DEV;

            $payload = [
                "clientId" => $pixxpayatpaclientId,
                "clientSecret" => $pixxpayatpaclientSecret
            ];

            $payJson = json_encode($payload);
            $payFinal = str_replace('\/', '/', $payJson);
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
            if(isset($response->accessToken)){
                /**Exibir mensagem para o usuário usando o notice do wordpress*/
                echo '<div class="notice notice-success is-dismissible">
                        <p>Autenticação com a U4c realizada com sucesso!</p>
                    </div>';
            }elseif(isset($response->statusCode) AND $response->statusCode == 401){
                /**Exibir mensagem para o usuário usando o notice do wordpress*/
                echo '<div class="notice notice-error is-dismissible">
                        <p>Erro ao realizar a autenticação com a U4c! '.$response->errorCode.': '.$response->message[0].'</p>
                    </div>';
            }else{
                /**Exibir mensagem para o usuário usando o notice do wordpress*/
                echo '<div class="notice notice-error is-dismissible">
                        <p>Erro ao realizar a autenticação com a U4c! '.$response->errorCode.': '.$response->message[0].'</p>
                    </div>';
            }
            // var_dump($response);
        }

    }
}

/**
* Add pixxpay as a gateway method
*/
add_filter( 'woocommerce_payment_gateways', 'add_pixxpay_gateway_atpa_class' );
function add_pixxpay_gateway_atpa_class( $methods ) {
    $methods[] = 'WC_Gateway_PixxPay_Atpa';
    return $methods;
}