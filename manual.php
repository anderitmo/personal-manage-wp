<?php
session_start();
// Se precisar proteger o manual, pode descomentar a linha abaixo:
// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) { header("Location: happymanagerwp.php"); exit; }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual do Usuário | Happy Manager WP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .manual-content {
            background: white;
            padding: 2rem 3rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-top: 1rem;
            margin-bottom: 3rem;
            line-height: 1.6;
            color: #333;
        }
        @media (max-width: 768px) {
            .manual-content {
                padding: 1.5rem;
            }
        }
        .manual-content h1, .manual-content h2, .manual-content h3 {
            color: var(--text-main);
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .manual-content h1 { font-size: 1.8rem; border-bottom: 2px solid var(--primary); padding-bottom: 0.5rem; }
        .manual-content h2 { font-size: 1.4rem; border-bottom: 1px solid #eee; padding-bottom: 0.3rem; }
        .manual-content h3 { font-size: 1.1rem; }
        .manual-content ul, .manual-content ol { margin-left: 1.5rem; margin-bottom: 1rem; }
        .manual-content p { margin-bottom: 1rem; }
        .manual-content hr { border: none; border-top: 1px solid #eee; margin: 2rem 0; }
        .manual-content code { background: #f1f5f9; padding: 0.2rem 0.4rem; border-radius: 4px; font-family: monospace; }
        .manual-content strong { color: #1e293b; }
    </style>
</head>
<body>

<div class="container">
    <header class="main-header" style="flex-wrap: nowrap;">
        <div class="header-top">
            <span style="font-size: 1.5rem; font-weight: 700; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">HappyWP</span>
        </div>
        <div class="header-actions" style="display: flex;">
            <a href="happymanagerwp.php" class="btn" style="background-color: var(--primary); text-decoration: none; display: flex; align-items: center; justify-content: center; color: white; padding: 0.5rem 1rem; border-radius: 4px;">&larr; Voltar ao Painel</a>
        </div>
    </header>

    <div class="manual-content">
        <h1>Manual do Usuário: HappyWP</h1>
        <p>Bem-vindo ao <strong>Happy Manager WP</strong>, o seu painel centralizado para gerenciar múltiplas instalações do WordPress de forma rápida, visual e segura.</p>
        <p>Este manual descreve como utilizar os principais recursos da aplicação.</p>

        <hr>

        <h2>1. Visão Geral do Dashboard</h2>
        <p>Ao fazer login com sua senha segura, você é recebido pelo Dashboard. Ele apresenta:</p>
        <ul>
            <li><strong>Estatísticas Rápidas:</strong> Quatro blocos no topo informam o total de sites instalados e a quantidade de atualizações pendentes (Plugins, Temas e Núcleo do WordPress).</li>
            <li><strong>Lista de Sites:</strong> Todos os seus sites organizados em cartões práticos.</li>
            <li><strong>Barra de Pesquisa:</strong> Localizada no menu superior, permite que você encontre rapidamente qualquer instalação buscando pelo nome ou pasta.</li>
            <li><strong>Design Responsivo:</strong> Acesse o painel pelo celular ou pelo computador. Pelo celular, as colunas do site se transformam automaticamente em "sanfonas" que você pode abrir e fechar, economizando espaço.</li>
        </ul>

        <hr>

        <h2>2. Instalação e Remoção de Sites</h2>

        <h3>Instalar Novo WordPress</h3>
        <p>No menu superior, clique no botão verde <strong>"Instalar WP"</strong>.</p>
        <ol>
            <li>Digite o nome da subpasta onde o site será instalado (apenas letras minúsculas, números e hifens).</li>
            <li>Marque a caixa para criar automaticamente o Banco de Dados e configurar o arquivo <code>wp-config.php</code>.</li>
            <li>Clique em Instalar. O painel baixará a versão oficial mais recente do WordPress e o redirecionará para a tela final de configuração.</li>
        </ol>

        <div style="background-color: #f8fafc; border-left: 4px solid #007bff; padding: 1rem 1.5rem; margin: 1.5rem 0; border-radius: 4px;">
            <p style="margin-top: 0;"><strong>✨ Instalação Turbo:</strong> Para agilizar o seu desenvolvimento, nosso painel também baixa e pré-instala automaticamente o plugin <strong>Elementor</strong> e o tema oficial <strong>Hello Elementor</strong> em todas as novas instalações!</p>
            <p style="margin-bottom: 0; font-size: 0.95rem; color: #475569;"><em>Dica:</em> Após a instalação ser finalizada, você pode utilizar a lixeira do próprio Happy Manager WP para deletar com 1 clique os plugins nativos (ex: Akismet, Hello Dolly) e os temas padrões do WP (ex: Twenty Twenty-Four) que não for utilizar, deixando sua hospedagem completamente limpa.</p>
        </div>

        <h3>Excluir um Site</h3>
        <p>Se você precisa remover um site antigo, basta clicar no botão vermelho de lixeira (<strong>🗑️</strong>) dentro do cartão correspondente.</p>
        <ul>
            <li>Por segurança, o sistema pedirá que você digite exatamente o nome da pasta do site para confirmar a exclusão.</li>
            <li><strong>Atenção:</strong> Esta ação apaga os arquivos da pasta e destrói o banco de dados permanentemente.</li>
        </ul>

        <hr>

        <h2>3. Gerenciamento de Plugins e Temas</h2>
        <p>O painel exibe um panorama completo dos plugins e temas de cada site.</p>
        <ul>
            <li><strong>Identificando atualizações:</strong> Plugins e temas que possuem uma nova versão disponível terão seu número de versão destacado em <strong>vermelho</strong>, facilitando a identificação de sites que precisam de manutenção.</li>
            <li><strong>Status de Ativação:</strong> Uma "bolinha" verde indica que o plugin ou tema está ativo no momento. Uma cinza, junto ao nome tachado (riscado), indica inatividade.</li>
            <li><strong>Limpeza:</strong> Você notará um ícone de "Lixeira" ao lado de plugins e temas inativos. Com um clique, você pode excluir permanentemente esse item direto pelo painel para economizar espaço e melhorar a segurança da sua hospedagem.</li>
        </ul>

        <hr>

        <h2>4. 💾 Sistema de Backups (Importante!)</h2>
        <p>O Happy Manager WP possui um sistema robusto de backup completo, desenhado para criar arquivos no formato oficial do plugin <strong>Backup Migration</strong>.</p>

        <h3>Como gerar um backup:</h3>
        <ol>
            <li>No cartão do site, clique no botão azul <strong>"💾 Backup Migration"</strong>.</li>
            <li>Confirme a ação. O sistema fará um dump (exportação) completo do seu banco de dados e compactará todos os arquivos da pasta em um único pacote <code>.zip</code>.</li>
            <li>Aguarde o processo finalizar. Pode levar alguns minutos em sites muito grandes.</li>
        </ol>

        <h3>Como visualizar e baixar:</h3>
        <ul>
            <li>Todos os backups criados ficam listados na seção "Banco de Dados e Backups" do site.</li>
            <li>Você verá a data e o tamanho de cada arquivo (ex: <code>💾 2026-05-02 18:42:43 - 92.90 MB</code>).</li>
            <li>Clique sobre a data do backup para iniciar o download direto do arquivo <code>.zip</code> para o seu computador.</li>
            <li>Se o seu servidor ficar com pouco espaço, você pode apagar os backups antigos clicando no botão da lixeira ao lado deles.</li>
        </ul>

        <h3>⚠️ Como Restaurar o Backup (Guia Prático)</h3>
        <p>O arquivo de backup gerado é 100% compatível com o ecossistema do WordPress. Para restaurar o seu site (seja no mesmo local ou em um novo servidor):</p>
        <ol>
            <li>Instale um WordPress "em branco".</li>
            <li>Acesse o painel do WordPress recém-instalado, vá em <strong>Plugins > Adicionar Novo</strong>.</li>
            <li>Pesquise e instale gratuitamente o plugin chamado <strong>"Backup Migration"</strong> (de <em>Inisev</em>).</li>
            <li>Após ativar o plugin, vá até as configurações dele e acesse a aba <strong>"Manage & Restore Backup"</strong> (Gerenciar e Restaurar Backup).</li>
            <li>Procure a opção de fazer <strong>Upload</strong> de um backup ou coloque via FTP o arquivo <code>.zip</code> gerado pelo nosso painel na pasta <code>wp-content/backup-migration/</code>.</li>
            <li>O plugin reconhecerá o seu arquivo. Clique em <strong>Restore</strong> (Restaurar) e aguarde. O plugin cuidará de substituir o banco de dados e todos os arquivos, ressuscitando seu site exatamente como estava no momento do backup.</li>
        </ol>

        <hr>

        <h2>5. Dicas Adicionais</h2>
        <ul>
            <li>Use o botão <strong>"Nova Varredura"</strong> se você fez uma modificação via FTP ou Banco de Dados e deseja forçar o painel a recarregar as estatísticas e listar os novos sites instantaneamente.</li>
            <li><strong>Segurança da Senha:</strong> No menu superior, utilize o botão "Senha" para alterar sua senha de acesso a qualquer momento. Certifique-se de usar senhas fortes.</li>
        </ul>
        <p style="text-align: center; margin-top: 3rem; color: #666; font-size: 0.9rem;"><em>Desenvolvido para facilitar a rotina de quem gerencia múltiplos projetos web!</em></p>
    </div>
</div>

</body>
</html>
