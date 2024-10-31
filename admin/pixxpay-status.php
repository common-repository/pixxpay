<?php

function pixxpay_sub_status(){
    _e('<div class="wrap"><h2>U4c - Status do sistema</h2></div>');
    pixxpay_status_system();
}

function pixxpay_status_system(){
    (phpversion() >= '7.2')? $phpVersion = 'Suportado (Versão atual: ' . phpversion() . ')' : $phpVersion = 'Não suportado (Versão atual: ' . phpversion() . ')';
    (is_plugin_active('woocommerce/woocommerce.php'))? $woocommerce = 'Instalado' : $woocommerce = 'Não instalado';
    (is_plugin_active('brazilian-market-on-woocommerce/brazilian-market-on-woocommerce.php'))? $brazilian_market_on_woocommerce = 'Instalado' : $brazilian_market_on_woocommerce = 'Não instalado';
    _e('<style>
        table {
            border-collapse: collapse;
            width: 80%;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
    </style>');
    _e('<div class="wrap"><table>
        <tr>
            <th>Verificação</th>
            <th>Status</th>
        </tr>
        <tr>
            <td>Versão do PHP</td>
            <td>
                '.$phpVersion.'
            </td>
        </tr>
        <tr>
            <td>Plugin WooCommerce</td>
            <td>
                '.$woocommerce.'
            </td>
        </tr>
        <tr>
            <td>Plugin Brazilian Market on WooCommerce</td>
            <td>
                '.$brazilian_market_on_woocommerce.'
            </td>
        </tr>
    </table></div>');
}