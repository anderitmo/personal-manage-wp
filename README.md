# 🚀 HappyWP — Gerenciador Multi-Site WordPress

**HappyWP** é um painel PHP auto-hospedado para gerenciar múltiplas instalações WordPress a partir de um único dashboard. Instale, escaneie, faça backups e gerencie seus sites — tudo em um só lugar.

> 🇧🇷 Documentação em Português · Feito para desenvolvedores que gerenciam múltiplos projetos WP

---

## ✨ Funcionalidades

| Funcionalidade | Descrição |
|----------------|-----------|
| **📊 Dashboard** | Visão geral de todos os sites WordPress, plugins, temas e atualizações do core |
| **🔍 Auto Scanner** | Detecta automaticamente instalações WordPress em subpastas |
| **⚡ Instalação Turbo** | Instala WordPress + Elementor + Hello Elementor com 1 clique |
| **💾 Backup System** | Backups completos (arquivos + BD) compatíveis com plugin **Backup Migration** |
| **📁 File Manager** | Navegue, faça upload, edite arquivos com **Monaco Editor** (VS Code) |
| **🗄️ Database Manager** | Navegue em bancos de dados, execute queries SQL, veja estrutura de tabelas |
| **🗑️ Remoção Completa** | Exclua sites inteiros incluindo seus bancos de dados |
| **🔎 Busca** | Encontre sites pelo nome ou caminho da pasta |
| **📱 Responsivo** | Funciona em desktop e mobile (layout adaptável com accordion) |
| **🔧 Configuração Visual** | Interface para alterar senha e configurar credenciais MySQL sem editar arquivos |

---

## 📋 Requisitos

- **PHP** 7.4+ (8.0+ recomendado)
- **MySQL** / MariaDB
- Extensões PHP: `PDO`, `pdo_mysql`, `curl`, `zip`, `json`
- Apache com `mod_rewrite` ou equivalente (recomendado, não obrigatório)
- Laragon / XAMPP / WAMP / servidor Linux com suporte aos requisitos acima

---

## 🚀 Instalação

### 1. Download
Baixe ou clone o repositório para a pasta do seu servidor web:

```bash
# Exemplo com Laragon: e:/laragon/www/gestor-wp/
git clone https://github.com/seu-usuario/happywp.git
```

### 2. Permissões
Garanta que o usuário do servidor web tenha permissão de escrita no diretório:

```bash
# Linux
chmod -R 775 /caminho/para/happywp/

# Windows (Laragon/XAMPP): geralmente o usuário já tem permissão
```

### 3. Acessar e Configurar
1. Acesse `http://localhost/gestor-wp/` (ou o caminho onde instalou)
2. Faça login com as credenciais padrão:
   - **Usuário:** `admin`
   - **Senha:** `CHANGE_ME_PLEASE`
3. **IMPORTANTE:** Altere a senha imediatamente!
4. Configure as credenciais do MySQL em **Configurações > Configuração do MySQL**

### 4. Configuração Inicial pelo Painel

Após o login, vá em **Configurações** (botão "Senha" no dashboard) e:

1. **🔑 Altere a senha do painel**
2. **🗄️ Configure o MySQL:**
   - Host: `localhost` (padrão)
   - Usuário: `root` (padrão Laragon)
   - Senha: deixe vazio (padrão Laragon)
   - Clique em **🔌 Testar Conexão** para verificar
   - Clique em **💾 Salvar Configurações**

> ✅ Pronto! Agora você pode instalar WordPress, fazer backups e gerenciar arquivos!

---

## 📖 Como Usar

### Dashboard
- Ao fazer login, o scanner lista automaticamente todos os sites WordPress encontrados nas subpastas
- Use a barra de pesquisa para filtrar sites pelo nome ou pasta
- Os cards exibem: versão do WP, plugins, temas, banco de dados, usuários e backups

### Instalar Novo WordPress
1. Clique em **"Novo WP"** no menu superior
2. Digite o nome da subpasta (ex: `meu-site-novo`)
3. Marque **"Criar BD e wp-config"** para configurar automaticamente
4. Clique em **Instalar**
5. O WordPress será baixado, descompactado e o banco de dados criado
6. Uma nova aba abrirá com a tela final de instalação do WP
7. **Bônus:** Elementor e Hello Elementor já vêm instalados! 🎉

### Gerenciar Plugins e Temas
- Visualize plugins e temas ativos/inativos de cada site
- Identifique atualizações disponíveis (destacadas em vermelho)
- Exclua plugins e temas inativos com 1 clique (ícone de lixeira)

### Backup
1. Clique em **"💾 Backup Migration"** no card do site
2. O sistema gera um dump do banco de dados + zip dos arquivos
3. Formato compatível com o plugin **Backup Migration** do WordPress
4. Baixe ou gerencie backups diretamente pelo painel

### Excluir Site
1. Clique no ícone 🗑️ no card do site
2. Digite exatamente o nome da pasta para confirmar
3. O sistema remove arquivos + banco de dados

### File Manager
- Acesse pelo botão **"📁 Files"** no menu superior
- Navegue pelas pastas, faça upload, crie arquivos/pastas
- Edite arquivos com o **Monaco Editor** (mesmo editor do VS Code)
- Sistema de permissões detalhado para Linux

### Database Manager
- Na seção Database do File Manager
- Navegue por bancos de dados e tabelas
- Execute queries SQL com templates prontos
- Exporte resultados em CSV ou JSON

---

## 📁 Estrutura do Projeto

```
/
├── happymanagerwp.php   # Dashboard principal + tela de login
├── api.php              # API REST (scan, install, backup, delete)
├── scanner.php          # Classe WPScanner — detecção de sites
├── auth.php             # Credenciais centralizadas (painel + MySQL)
├── settings.php         # Configurações visuais (senha + MySQL)
├── happyfiles.php       # Gerenciador de arquivos + banco de dados
├── manual.php           # Manual do usuário
├── style.css            # Estilos do dashboard
├── app.js               # Lógica frontend (JavaScript vanilla)
├── index.html           # Landing page
├── happy-phpinfo.php    # phpinfo() protegido por login
├── README.md            # Este arquivo
├── LICENSE              # Licença MIT
└── happy-backup/        # Pasta de armazenamento de backups
```

---

## 🔧 Configuração Avançada

### Credenciais MySQL
Todas as credenciais ficam centralizadas no arquivo `auth.php`:

```php
<?php
return array (
  'usuario' => 'admin',
  'senha' => 'sua_senha',
  'db_host' => 'localhost',
  'db_user' => 'root',
  'db_pass' => '',
  'db_socket' => '',
);
```

> Você pode editar manualmente ou usar a interface em **Configurações > Configuração do MySQL**

### Linux com MySQL Socket
Se estiver no Linux e quiser usar socket MySQL:
1. Vá em **Configurações > Configuração do MySQL**
2. Preencha o campo **Socket**: `/var/run/mysqld/mysqld.sock`
3. Clique em **Testar Conexão** para validar
4. Clique em **Salvar Configurações**

### Modo Laragon (subpastas irmãs)
Se os sites ficam em pastas irmãs ao HappyWP (não dentro), descomente no `api.php`:
```php
// $basePath = __DIR__ . '/..'; // Descomente para modo pastas irmãs
```

---

## 🔒 Segurança

- ✅ Todas as páginas sensíveis exigem autenticação por sessão
- ✅ CSRF token no gerenciador de arquivos
- ✅ Validação contra path traversal
- ✅ `auth.php` no `.gitignore` (não versiona senhas)
- ✅ Senha do MySQL configurável via interface (sem editar arquivos)
- ⚠️ Use **HTTPS** em produção para proteger credenciais em trânsito
- ⚠️ Nunca exponha o HappyWP diretamente na internet sem autenticação forte

---

## 🤝 Contribuindo

Contribuições são bem-vindas! Sinta-se à vontade para abrir issues e pull requests.

1. Fork o repositório
2. Crie sua branch (`git checkout -b feature/nova-feature`)
3. Commit suas mudanças (`git commit -m 'Adiciona nova feature'`)
4. Push para a branch (`git push origin feature/nova-feature`)
5. Abra um Pull Request

---

## 📄 Licença

Este projeto é licenciado sob a **MIT License** — veja o arquivo [LICENSE](LICENSE) para detalhes.

---

## 🙏 Agradecimentos

- Construído com PHP, JavaScript vanilla e ❤️
- [Monaco Editor](https://microsoft.github.io/monaco-editor/) by Microsoft
- [Tailwind CSS](https://tailwindcss.com/) para estilização (File Manager)
- [Google Fonts](https://fonts.google.com/) (Outfit)
- Plugin [Backup Migration](https://wordpress.org/plugins/backup-migration/) pela compatibilidade

---

<p align="center">
  <strong>HappyWP v2.2</strong> · Open Source · Gerencie seus WordPress com facilidade
</p>
