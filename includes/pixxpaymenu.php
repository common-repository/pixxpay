<?php
add_action( 'admin_menu', 'pixxpay_admin_menu' );

function pixxpay_admin_menu() {
    /** Add awaiting-mod count-1 */
    $awaiting_mod = '1';
    $awaiting_mod = $awaiting_mod ? "<span class='awaiting-mod count-1'><span class='pending-count'>$awaiting_mod</span></span>" : '';

   $site_url = site_url('', 'https'); // Obtém a URL do site
   // Formata a mensagem padrão
   $mensagem_padrao = 'Olá! Preciso de suporte para instalar o Plugin PixxPay da U4c no site: ' . $site_url;
   // Codifica a mensagem para ser passada como parâmetro na URL
   $mensagem_encoded = rawurlencode($mensagem_padrao);
   // Cria o link do WhatsApp com a mensagem padrão
   $link_whatsapp = 'https://api.whatsapp.com/send?phone=553198597620&text=' . $mensagem_encoded;


   add_menu_page('U4c','U4c '.$awaiting_mod,'manage_options','pixxpay','pixxpay_sub_registro','dashicons-bank',7);
   add_submenu_page('pixxpay','Configurações','Configurações','manage_options', site_url('', '').'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=pixxpayatpa');
   add_submenu_page('pixxpay','Status','Status','manage_options','pixxpay-sub-status','pixxpay_sub_status');
   add_submenu_page('pixxpay','Log','Log','manage_options','pixxpay-sub-log','pixxpay_sub_log');
   add_submenu_page('pixxpay','Teste','Teste','manage_options', site_url('', '').'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=pixxpayatpa&test=pixxpayapi');
   add_submenu_page('pixxpay','Suporte','Suporte','manage_options',$link_whatsapp);
   unset($GLOBALS['submenu']['pixxpay'][0]);
}

include_once(plugin_dir_path(__DIR__)."admin/pixxpay-status.php");
include_once(plugin_dir_path(__DIR__)."admin/pixxpay-log.php");