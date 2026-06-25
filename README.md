# 🚀 HappyWP

**HappyWP** is a self-hosted PHP panel for managing multiple WordPress installations from a single dashboard. Install, scan, backup, and manage WordPress sites — all in one place.

> ⚠️ **Important:** This is a self-hosted tool. It has powerful file and database management capabilities. Use it only in secure environments. Never expose it to the public internet without proper authentication and HTTPS.

---

## ✨ Features

- **📊 Dashboard** — Overview of all WordPress sites, plugins, themes, and core updates
- **🔍 Auto Scanner** — Automatically detects WordPress installations in subdirectories
- **⚡ One-Click Install** — Installs WordPress + Elementor + Hello Elementor automatically
- **💾 Backup System** — Full backups (files + database) compatible with **Backup Migration** plugin
- **📁 File Manager** — Browse, upload, edit, delete files with Monaco Code Editor
- **🗄️ Database Manager** — Browse databases, run SQL queries, view table structures
- **🗑️ Site Removal** — Delete entire sites including their databases
- **🔎 Search** — Find sites by name or folder path
- **📱 Responsive** — Works on desktop and mobile

---

## 📋 Requirements

- **PHP** 7.4+ (8.0+ recommended)
- **MySQL** / MariaDB
- PHP Extensions: `PDO`, `pdo_mysql`, `curl`, `zip`, `json`
- Apache with `mod_rewrite` or equivalent (recommended, not required)

---

## 🚀 Installation

1. **Download** the latest release and extract it to your server's web root or a subfolder
2. **Set permissions**: Ensure the web server user has write permissions to the directory
3. **Configure authentication**:
   - Edit `auth.php` and change the default credentials:
     ```php
     'usuario' => 'your_username',
     'senha' => 'your_strong_password',
     ```
4. **Configure database** (optional, for auto-create feature):
   - Edit `api.php` and `happyfiles.php`: set your MySQL credentials
5. Access `http://yourserver/path-to-happywp/` and log in

---

## 🔧 Configuration

### Database Credentials
If you want the auto-create database feature to work, update these files:

**`api.php`** (around line 236):
```php
$dbUser = 'your_mysql_user';
$dbPass = 'your_mysql_password';
```

**`happyfiles.php`** (around line 18):
```php
define('DB_USERNAME', 'your_mysql_user');
define('DB_PASSWORD', 'your_mysql_password');
```

> 💡 **Tip:** On Linux with MySQL socket, uncomment `define('DB_SOCKET', '/var/run/mysqld/mysqld.sock');` in `happyfiles.php`

---

## 📖 Usage

1. Open the dashboard in your browser
2. Log in with the credentials set in `auth.php`
3. The scanner will automatically list all WordPress sites in subdirectories
4. Use the buttons to:
   - **Novo WP** — Install a new WordPress site
   - **Scan** — Re-scan for WordPress installations
   - **Senha** — Change your login password
   - **📁 Files** — Open the file manager
   - **PHPInfo** — View PHP configuration (requires login)

---

## 📁 Project Structure

```
/
├── happymanagerwp.php   # Main dashboard & login
├── api.php              # REST API backend
├── scanner.php          # WordPress detection & info
├── auth.php             # Login credentials (EDIT THIS!)
├── settings.php         # Password change page
├── happyfiles.php       # File & database manager
├── manual.php           # User manual
├── style.css            # Dashboard styles
├── app.js               # Frontend logic
├── index.html           # Landing page
├── happy-phpinfo.php    # Protected phpinfo()
└── happy-backup/        # Backup storage folder
```

---

## 🔒 Security Notes

- **Change the default password** in `auth.php` immediately after installation
- Use **HTTPS** to protect credentials and data in transit
- The File Manager and Database Manager are powerful tools — be careful
- `happy-phpinfo.php` requires login, but still exposes server configuration
- Keep the application updated and monitor access logs

---

## 🤝 Contributing

Contributions are welcome! Feel free to submit issues and pull requests.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- Built with PHP, vanilla JavaScript, and ❤️
- [Monaco Editor](https://microsoft.github.io/monaco-editor/) by Microsoft
- [Tailwind CSS](https://tailwindcss.com/) for styling
- [Google Fonts](https://fonts.google.com/) (Outfit)