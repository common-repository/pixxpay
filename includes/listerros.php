<!DOCTYPE html>
<html>
<head>
    <title>Leitura de Arquivos TXT</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
    </style>
</head>
<body>
    <h1>Informações do sistema</h1>

    <h2>Informações do Sistema</h2>
    <table>
        <tr>
            <th>Verificação</th>
            <th>Status</th>
        </tr>
        <tr>
            <td>Versão do PHP</td>
            <td>
                <?php
                $phpVersion = phpversion();
                if (version_compare($phpVersion, '7.2', '>=')) {
                    echo 'Suportado (Versão atual: ' . $phpVersion . ')';
                } else {
                    echo 'Não suportado (Versão atual: ' . $phpVersion . ')';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td>Plugin WooCommerce</td>
            <td>
                <?php
                if (is_plugin_active('woocommerce/woocommerce.php')) {
                    echo 'Instalado';
                } else {
                    echo 'Não instalado';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td>Plugin Brazilian Market on WooCommerce</td>
            <td>
                <?php
                if (is_plugin_active('brazilian-market-on-woocommerce/brazilian-market-on-woocommerce.php')) {
                    echo 'Instalado';
                } else {
                    echo 'Não instalado';
                }
                ?>
            </td>
        </tr>
    </table>

    <h2>Arquivos de pedidos com erro</h2>
    <table>
        <tr>
            <th>Arquivo</th>
            <th>Data e Erro</th>
            <th>Item</th>
            <th>Parâmetro</th>
            <th>Resposta</th>
        </tr>
        <?php
        $diretorio = PIXXPAY_PLUGIN_DIR.'pedidos'; // substitua pelo caminho do diretório onde estão os arquivos

        // Obtém a lista de arquivos com extensão .txt no diretório
        $arquivos = glob($diretorio . '/*.txt');

        // Itera sobre os arquivos
        foreach ($arquivos as $arquivo) {
            $nomeArquivo = basename($arquivo);
            $conteudoArquivo = file_get_contents($arquivo);

            // Extrai as informações do arquivo
            preg_match('/\[(.*?)\]\s*:\s*(.*)\s*-\s*Item:\s*(.*)\s*-\s*(.*?)\s*-\s*Resposta:\s*(.*)/', $conteudoArquivo, $matches);
            $dataErro = $matches[1];
            $erro = $matches[2];
            $item = $matches[3];
            $parametro = $matches[4];
            $resposta = $matches[5];

            // Exibe as informações em uma linha da tabela
            echo '<tr>';
            echo '<td>' . $nomeArquivo . '</td>';
            echo '<td>' . $dataErro . '<br>' . $erro . '</td>';
            echo '<td>' . $item . '</td>';
            echo '<td>' . $parametro . '</td>';
            echo '<td>' . $resposta . '</td>';
            echo '</tr>';
        }
        ?>
    </table>
</body>
</html>
