<?php
session_start();

// 1. Proteção: Só acessa se estiver logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: happymanagerwp.php");
    exit;
}

$config_file = 'auth.php';
$credenciais = include $config_file;
$mensagem = "";
$tipo_mensagem = ""; // 'sucesso' ou 'erro'

// 2. Lógica de reescrita
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    if ($senha_atual !== $credenciais['senha']) {
        $mensagem = "A senha atual está incorreta.";
        $tipo_mensagem = "erro";
    } elseif ($nova_senha !== $confirmar_senha) {
        $mensagem = "A nova senha e a confirmação não coincidem.";
        $tipo_mensagem = "erro";
    } elseif (strlen($nova_senha) < 4) {
        $mensagem = "A nova senha deve ter pelo menos 4 caracteres.";
        $tipo_mensagem = "erro";
    } else {
        // Atualiza a array
        $credenciais['senha'] = $nova_senha;
        
        // Gera o conteúdo PHP formatado
        $conteudo = "<?php\nreturn " . var_export($credenciais, true) . ";\n";
        
        // Grava no arquivo
        if (file_put_contents($config_file, $conteudo)) {
            $mensagem = "Senha alterada com sucesso!";
            $tipo_mensagem = "sucesso";
        } else {
            $mensagem = "Erro: Sem permissão de escrita no servidor.";
            $tipo_mensagem = "erro";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações | Happy Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Outfit', sans-serif; 
            background: #f4f7f6; 
            display: flex; 
            justify-content: center; 
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .card { 
            background: white; 
            padding: 2rem; 
            border-radius: 12px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
            width: 100%; 
            max-width: 400px; 
        }
        h2 { margin-top: 0; color: #333; }
        label { display: block; margin-top: 15px; font-weight: 600; color: #555; font-size: 0.9rem; }
        input { 
            width: 100%; 
            padding: 0.8rem; 
            margin-top: 5px; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
        }
        button { 
            width: 100%; 
            padding: 0.8rem; 
            margin-top: 20px;
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-family: 'Outfit', sans-serif;
            font-weight: 600; 
            font-size: 1rem;
            transition: background 0.3s;
        }
        button:hover { background: #0056b3; }
        .msg { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem; text-align: center; }
        .erro { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .sucesso { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .voltar { display: block; margin-top: 20px; text-align: center; color: #666; text-decoration: none; font-size: 0.9rem; transition: color 0.3s; }
        .voltar:hover { color: #333; }
    </style>
</head>
<body>

<div class="card">
    <h2>Alterar Senha</h2>
    
    <?php if ($mensagem): ?>
        <div class="msg <?php echo $tipo_mensagem; ?>"><?php echo $mensagem; ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Senha Atual</label>
        <input type="password" name="senha_atual" required>
        
        <label>Nova Senha</label>
        <input type="password" name="nova_senha" required>
        
        <label>Confirmar Nova Senha</label>
        <input type="password" name="confirmar_senha" required>
        
        <button type="submit">Salvar Nova Senha</button>
    </form>

    <a href="happymanagerwp.php" class="voltar">← Voltar para o Dashboard</a>
</div>

</body>
</html>