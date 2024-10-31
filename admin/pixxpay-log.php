<?php
function pixxpay_sub_log(){
    _e('<div class="wrap"><h2>U4c - Log</h2></div>');
    _e('<style>
       table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
    </style>');
    pixxpay_log();
}

function pixxpay_log(){
    $diretorio = PIXXPAY_PLUGIN_DIR.'pedidos'; // substitua pelo caminho do diretório onde estão os arquivos

    // Obtém a lista de arquivos com extensão .txt no diretório
    $arquivos = glob($diretorio . '/*.txt');

    _e('<div class="wrap"><table>
        <tr>
            <th>Arquivo</th>
            <th>Data e Erro</th>
            <th>Item</th>
            <th>Parâmetro</th>
            <th>Resposta</th>
        </tr>');

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
    echo '</table></div>';
}