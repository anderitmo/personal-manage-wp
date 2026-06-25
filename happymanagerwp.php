<?php
// Inicia a sessão do PHP (deve ser a primeira coisa no arquivo)
session_start();

// 1. Ler as credenciais do arquivo
$credenciais = include 'auth.php';
$USUARIO_CORRETO = $credenciais['usuario'];
$SENHA_CORRETA = $credenciais['senha'];

// 2. Lógica para MUDAR A SENHA (pode ser colocada onde você quiser processar isso)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mudar_senha'])) {
    $senha_antiga = $_POST['senha_antiga'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    
    // Verifica se a senha antiga confere antes de deixar trocar
    if ($senha_antiga === $SENHA_CORRETA) {
        
        // Atualiza a array com a nova senha
        $credenciais['senha'] = $nova_senha;
        
        // Monta o texto que será salvo no arquivo PHP
        $novo_conteudo = "<?php\nreturn " . var_export($credenciais, true) . ";\n";
        
        // Reescreve APENAS o arquivo config.php
        if (file_put_contents('config.php', $novo_conteudo)) {
            $mensagem_sucesso = "Senha alterada com sucesso!";
            // Atualiza a variável na memória para a sessão atual
            $SENHA_CORRETA = $nova_senha; 
        } else {
            $mensagem_erro = "Erro ao salvar o arquivo. Verifique as permissões de pasta.";
        }
    } else {
        $mensagem_erro = "A senha antiga está incorreta!";
    }
}


// 3. Processa o formulário de login
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $usuario_digitado = $_POST['usuario'] ?? '';
    $senha_digitada = $_POST['senha'] ?? '';

    if ($usuario_digitado === $USUARIO_CORRETO && $senha_digitada === $SENHA_CORRETA) {
        $_SESSION['logado'] = true;
        // Recarrega a página para limpar os dados do formulário
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit;
    } else {
        $erro = "Usuário ou senha inválidos!";
    }
}

// 3. Processa o logout (quando o usuário clica em "Sair")
if (isset($_GET['logout'])) {
    session_destroy(); // Destrói a sessão
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 4. Verifica se o usuário está logado
$esta_logado = isset($_SESSION['logado']) && $_SESSION['logado'] === true;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HappyWP | <?php echo $esta_logado ? 'Dashboard' : 'Login'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    
    <style>
        .login-wrapper { display: flex; justify-content: center; align-items: center; height: 100vh; background-color: var(--bg-color, #f4f7f6); }
        .login-box { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .login-box input { width: 100%; padding: 0.8rem; margin: 0.5rem 0 1rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: 'Outfit', sans-serif;}
        .login-box button { width: 100%; padding: 0.8rem; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-family: 'Outfit', sans-serif; font-weight: 600;}
        .login-box button:hover { background: #0056b3; }
        .erro { color: red; margin-bottom: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>

<?php if (!$esta_logado): ?>

    <div class="login-wrapper">
        <div class="login-box">
            <h2>Login</h2>
            <?php if ($erro): ?>
                <p class="erro"><?php echo $erro; ?></p>
            <?php endif; ?>
            
            <form method="POST">
                <input type="text" name="usuario" placeholder="Usuário" required>
                <input type="password" name="senha" placeholder="Senha" required>
                <button type="submit" name="login">Entrar</button>
            </form>
        </div>
    </div>

<?php else: ?>

    <div class="container">
        <header class="main-header">
            <div class="header-top">
                <span style="font-size: 1.5rem; font-weight: 700; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">HappyWP</span>
                <button id="mobile-menu-btn" class="hamburger-btn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>
            </div>

            <div class="header-search">
                <input type="text" id="search-input" placeholder="Pesquisar por pasta ou nome do site...">
            </div>
            
            <div class="header-actions" id="header-actions">
                <button id="install-btn" class="btn" style="background-color: #28a745;">Novo WP</button>
                <button id="scan-btn" class="btn">Scan</button>
				<a href="settings.php" class="btn" style="background-color: blue; text-decoration: none; display: flex; align-items: center; justify-content: center; color: white; padding: 0.5rem 1rem; border-radius: 4px;">Senha</a>
                <a href="happyfiles.php" class="btn" style="background-color: #6f42c1; text-decoration: none; display: flex; align-items: center; justify-content: center; color: white; padding: 0.5rem 1rem; border-radius: 4px;">📁 Files</a>
                <a href="happy-phpinfo.php" target="_blank" class="btn" style="background-color: #000; text-decoration: none; display: flex; align-items: center; justify-content: center; color: white; padding: 0.5rem 1rem; border-radius: 4px;">PHPInfo</a>
                <a href="?logout=true" class="btn" style="background-color: #dc3545; text-decoration: none; display: flex; align-items: center; justify-content: center; color: white; padding: 0.5rem 1rem; border-radius: 4px;">Sair</a>
            </div>
        </header>

        <!-- DASHBOARD -->
        <div id="dashboard-stats" style="display: flex; gap: 1rem; margin-bottom: 2rem; justify-content: space-between; flex-wrap: wrap;">
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; flex: 1; min-width: 200px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #007bff;">
                <h3 style="margin: 0; font-size: 2rem; color: #333;" id="stat-sites">-</h3>
                <p style="margin: 0.5rem 0 0; color: #666; font-size: 0.9rem; font-weight: 600;">Sites Instalados</p>
            </div>
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; flex: 1; min-width: 200px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #ffc107;">
                <h3 style="margin: 0; font-size: 2rem; color: #333;" id="stat-plugins">-</h3>
                <p style="margin: 0.5rem 0 0; color: #666; font-size: 0.9rem; font-weight: 600;">Plugins Desatualizados</p>
            </div>
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; flex: 1; min-width: 200px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #fd7e14;">
                <h3 style="margin: 0; font-size: 2rem; color: #333;" id="stat-themes">-</h3>
                <p style="margin: 0.5rem 0 0; color: #666; font-size: 0.9rem; font-weight: 600;">Temas Desatualizados</p>
            </div>
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 8px; flex: 1; min-width: 200px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #dc3545;">
                <h3 style="margin: 0; font-size: 2rem; color: #333;" id="stat-core">-</h3>
                <p style="margin: 0.5rem 0 0; color: #666; font-size: 0.9rem; font-weight: 600;">WP Desatualizados</p>
            </div>
        </div>

        <div id="loader">
            <div class="spinner"></div>
            <p>Escaneando subpastas...</p>
        </div>

        <div id="site-grid" class="site-grid">
            </div>

        <div id="empty-state" style="text-align: center; padding: 5rem; display: none;">
            <p style="color: var(--text-muted);">Nenhuma instalação do WordPress encontrada.</p>
        </div>

        <!-- Orphan Backups Section -->
        <div id="orphan-backups-section" style="display: none; margin-top: 2rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #ffc107;">
                <span style="font-size: 1.5rem;">📦</span>
                <h2 style="margin: 0; font-size: 1.2rem; color: #333; font-weight: 700;">Backups de sites excluídos</h2>
                <span id="orphan-count" style="background: #ffc107; color: #333; font-size: 0.75rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 10px;"></span>
            </div>
            <p style="color: #888; font-size: 0.85rem; margin-bottom: 1rem;">Esses backups pertencem a sites que já foram excluídos. Você pode baixá-los ou removê-los.</p>
            <div id="orphan-backups-grid"></div>
        </div>

    </div>

        <!-- Backup Overlay -->
        <div id="backup-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; flex-direction: column;">
            <div class="spinner" style="border-top-color: #0d6efd; width: 48px; height: 48px; margin-bottom: 1.5rem;"></div>
            <p id="backup-overlay-text" style="color: white; font-size: 1.1rem; font-weight: 600; text-align: center; max-width: 400px;">Gerando backup... Aguarde.</p>
            <p style="color: #aaa; font-size: 0.8rem; margin-top: 0.5rem;">Não feche esta página.</p>
        </div>

        <div id="install-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: white; padding: 2rem; border-radius: 8px; width: 400px; max-width: 90%;">
                <h2 style="margin-top: 0; color: #333;">Instalar WordPress</h2>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #333;">Nome da Subpasta</label>
                    <input type="text" id="install-folder" placeholder="ex: meunovo-site" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: 'Outfit', sans-serif;">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: #333;">
                        <input type="checkbox" id="install-db" checked>
                        Criar BD e wp-config 
                    </label>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button id="cancel-install" class="btn" style="background: #ccc; color: #333;">Cancelar</button>
                    <button id="confirm-install" class="btn" style="background: #28a745;">Instalar</button>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem; margin-bottom: 2rem;">
        <hr style="width: 70%; border: none; height: 1px; background-color: #ccc; margin: 20px auto;">    
        <a href="manual.php" style="display: inline-block; padding: 0.5rem 1rem; background-color: #f1f5f9; color: #333; text-decoration: none; border-radius: 4px; font-weight: 600; border: 1px solid #cbd5e1;">📖 Ver Manual do Usuário</a>
            <br/><br/>
            
            <b>HappyWP v2.2</b> — Open Source. Gerencie múltiplas instalações WordPress com facilidade.
        </div>

    <script src="app.js?v=<?php echo time(); ?>"></script>

<?php endif; ?>

</body>
</html>