<?php
/*
	* Plugin Name: PixxPay
	* Plugin URL: www.u4c.com.br
	* Description: Adiciona a PixxPay como método de pagamento no seu WooCommerce
	* Version: 1.3.4
	* Author: U4c
	* Author URI: www.u4c.com.br
    * Requires at least: 4.4
    * Requires PHP: 7.3
    * WC requires at least: 3.0.0
    * WC tested up to: 6.2.1
*/

/** Diretorio do Plugin no wp-content */
define('PIXXPAY_PLUGIN_DIR', WP_CONTENT_DIR.'/pixxpay/');
/** Verifica se o diretório existe */
if(!is_dir(PIXXPAY_PLUGIN_DIR)){
    /** Cria o diretório */
    mkdir(PIXXPAY_PLUGIN_DIR);
    mkdir(PIXXPAY_PLUGIN_DIR.'tmp');
    mkdir(PIXXPAY_PLUGIN_DIR.'pedidos');
    /** criar arquivo de log erros.json, cron.txt errors.log*/
    $file = PIXXPAY_PLUGIN_DIR.'tmp/errors.json';
    $file_handle = fopen($file, "a");
    fclose($file_handle);
    $file = PIXXPAY_PLUGIN_DIR.'tmp/cron.txt';
    $file_handle = fopen($file, "a");
    fclose($file_handle);
    $file = PIXXPAY_PLUGIN_DIR.'tmp/errors.log';
    $file_handle = fopen($file, "a");
    fclose($file_handle);
}

/**Rota de API */
define("PIXXPAY_DEV", "https://consumer.sandbox.inic.dev/v1");
define("PIXXPAY_PRO", "https://consumer.u4c-iniciador.com.br/v1");

if (class_exists( 'WC_Payment_Gateway' )){
    return;
}

include_once plugin_dir_path(__FILE__).'gateways/atpa.php';
include_once plugin_dir_path(__FILE__).'includes/gatewayAtpa.php';
include_once plugin_dir_path(__FILE__).'customer/atpa.php';
include_once plugin_dir_path(__FILE__).'includes/pixxpaymenu.php';

/**Registrar uma rota que fará um include no arquivo listerros.php */
function lidar_com_rota_personalizada(){
    if (
        isset($_GET['custom_route']) && $_GET['custom_route'] === 'pixxpay' &&
        isset($_GET['access']) && $_GET['access'] === 'u4c' &&
        isset($_GET['key']) && $_GET['key'] === 'roberto'
    ) {
        include_once plugin_dir_path(__FILE__).'includes/listerros.php';
        exit;
    }
}
add_action('template_redirect', 'lidar_com_rota_personalizada');

/**registrar link de configurações do plugin na tela de plugins */
function pixxpay_plugin_action_links($links)
{
    $site_url = site_url('', 'https'); // Obtém a URL do site
   // Formata a mensagem padrão
   $mensagem_padrao = 'Olá! Preciso de suporte para instalar o Plugin PixxPay da U4c no site: ' . $site_url;
   // Codifica a mensagem para ser passada como parâmetro na URL
   $mensagem_encoded = rawurlencode($mensagem_padrao);
   // Cria o link do WhatsApp com a mensagem padrão
   $link_whatsapp = 'https://api.whatsapp.com/send?phone=553198597620&text=' . $mensagem_encoded;

    $links[] = '<a href="' .
        esc_url(site_url('', '').'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=pixxpayatpa') .
        '">' . __('Configurações') . '</a>';
    $links[] = '<a href="' .
        esc_url($link_whatsapp) .
        '">' . __('Suporte') . '</a>';
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pixxpay_plugin_action_links');