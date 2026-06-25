document.addEventListener('DOMContentLoaded', () => {
    const scanBtn = document.getElementById('scan-btn');
    const searchInput = document.getElementById('search-input');
    const siteGrid = document.getElementById('site-grid');
    const loader = document.getElementById('loader');
    const emptyState = document.getElementById('empty-state');

    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const headerActions = document.getElementById('header-actions');
    if (mobileMenuBtn && headerActions) {
        mobileMenuBtn.addEventListener('click', () => {
            headerActions.classList.toggle('show');
        });
    }

    let allSites = [];

    const updateDashboardStats = () => {
        const statSites = document.getElementById('stat-sites');
        const statPlugins = document.getElementById('stat-plugins');
        const statThemes = document.getElementById('stat-themes');
        const statCore = document.getElementById('stat-core');

        if (!statSites) return;

        let totalSites = allSites.length;
        let outdatedPlugins = 0;
        let outdatedThemes = 0;
        let outdatedCore = 0;

        allSites.forEach(site => {
            if (site.has_core_update) outdatedCore++;
            if (site.plugins) outdatedPlugins += site.plugins.filter(p => p.new_version).length;
            if (site.themes) outdatedThemes += site.themes.filter(t => t.new_version).length;
        });

        statSites.innerText = totalSites;
        statPlugins.innerText = outdatedPlugins;
        statThemes.innerText = outdatedThemes;
        statCore.innerText = outdatedCore;
    };

    const fetchSites = async (scrollToSiteName = null) => {
        loader.style.display = 'block';
        if (!scrollToSiteName) {
            siteGrid.innerHTML = '';
        }
        emptyState.style.display = 'none';
        scanBtn.disabled = true;

        try {
            const response = await fetch('api.php');
            const result = await response.json();

            if (result.status === 'success' && result.data.length > 0) {
                allSites = result.data;
                renderSites(allSites);
                
                if (scrollToSiteName) {
                    setTimeout(() => {
                        const targetCard = document.getElementById('site-card-' + scrollToSiteName);
                        if (targetCard) {
                            targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }, 50);
                }
            } else {
                allSites = [];
                emptyState.style.display = 'block';
            }
            updateDashboardStats();
        } catch (error) {
            console.error('Erro ao buscar sites:', error);
            alert('Erro ao realizar a varredura: ' + error.message);
        } finally {
            loader.style.display = 'none';
            scanBtn.disabled = false;
        }
        // Fetch orphan backups after site scan
        fetchOrphanBackups();
    };

    const deleteItem = async (type, sitePath, slug, active, siteName) => {
        if (active) return;
        if (!confirm(`Remover este ${type}? Esta ação é permanente.`)) return;

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', type, sitePath, slug, active })
            });
            const result = await response.json();
            if (result.status === 'success') {
                fetchSites(siteName);
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (error) {
            alert('Erro ao excluir.');
        }
    };

    const renderSites = (sites) => {
        siteGrid.innerHTML = '';
        if (sites.length === 0) {
            emptyState.style.display = 'block';
            return;
        }
        emptyState.style.display = 'none';

        sites.forEach((site, index) => {
            const row = document.createElement('div');
            row.className = 'site-card';
            row.id = 'site-card-' + site.name;
            row.style.animation = `fadeIn 0.3s ease forwards ${index * 0.05}s`;

            const activePlugins = site.plugins.filter(p => p.active).length;

            row.innerHTML = `
                <!-- Column 1: Info -->
                <div class="site-info-main">
                    <div class="site-name">${site.url ? `<a href="${site.url}" target="_blank" style="color: inherit; text-decoration: none;">${site.title}</a>` : site.title}</div>
                    <div class="site-path">Pasta: /${site.path.split(/[\\\/]/).pop()}</div>
                    <div class="site-version">WordPress ${site.version}</div>
                    ${site.url ? `<a href="${site.url}/wp-admin/" target="_blank" class="admin-link">WP Admin ↗</a>` : ''}
                    <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                        <button id="backup-btn-${site.name}" class="btn" style="background-color: #0d6efd; color: white; font-size: 0.75rem; padding: 0.3rem 0.6rem; width: fit-content;" onclick="window.app.backupSite('${site.name}')">💾 Backup Migration</button>
                        <button class="btn" style="background-color: #E55353; color: white; font-size: 0.75rem; padding: 0.3rem 0.6rem; width: fit-content;" onclick="window.app.deleteSite('${site.name}')">🗑️</button>
                    </div>
                </div>

                <!-- Column 2: Meta (DB + Users) -->
                <div>
                    <div class="accordion-header" onclick="this.classList.toggle('active')">
                        Banco de Dados e Backups
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                    <div class="status-column accordion-content">
                        <div>
                            <div class="section-title">Banco de Dados</div>
                            <div class="status-badge ${site.db_connected ? 'status-active' : ''}">
                                <span class="item-status ${site.db_connected ? 'active-dot' : 'inactive-dot'}"></span>
                                ${site.db_connected ? site.db_name : 'Desconectado'}
                            </div>
                        </div>
                        <div style="margin-top: 1rem;">
                            <div class="section-title">Usuários</div>
                            <div class="list-content" style="max-height: 80px;">
                                ${site.users && site.users.length ? site.users.map(u => `<span class="user-tag">${u.display_name}</span>`).join('') : '--'}
                            </div>
                        </div>
                        <div style="margin-top: 1rem;">
                            <div class="section-title">Backups (${site.backups && site.backups.length ? site.backups.length : 0})</div>
                            <div class="list-content" style="max-height: 150px; overflow-y: auto;">
                                ${site.backups && site.backups.length > 0 ? site.backups.map(b => `
                                    <div style="font-size: 0.75rem; margin-bottom: 0.4rem; padding-bottom: 0.4rem; border-bottom: 1px solid #eee; color: #555; display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <a href="happy-backup/${site.name}/${b.name}" download style="color: inherit; text-decoration: none;"><strong>💾 ${b.date}</strong></a><br>
                                            <small style="color: #888;">Tamanho: ${(b.size / 1024 / 1024).toFixed(2)} MB</small>
                                        </div>
                                        <button class="delete-btn" onclick="window.app.deleteBackup('${site.name}', '${b.name}')" title="Excluir Backup">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>
                                    </div>
                                `).join('') : '<span style="font-size: 0.8rem; color: #888;">Nenhum backup</span>'}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Column 3: Plugins -->
                <div>
                    <div class="accordion-header" onclick="this.classList.toggle('active')">
                        Plugins (${activePlugins}/${site.plugins.length})
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                    <div class="accordion-content">
                        <div class="section-title">Plugins (${activePlugins}/${site.plugins.length})</div>
                        <div class="list-content">
                        ${site.plugins.map(plugin => `
                            <div class="list-item">
                                <span class="item-name" title="${plugin.name}" style="${!plugin.active ? 'text-decoration: line-through; color: #999;' : ''}">${plugin.name}</span>
                                <div class="item-meta">
                                    <span class="item-version" style="${plugin.new_version ? 'color: var(--danger); font-weight: 700;' : ''}">
                                        v${plugin.version}
                                        ${plugin.new_version ? `<span style="font-size: 0.6rem; display: block;">Nova: ${plugin.new_version}</span>` : ''}
                                    </span>
                                     <span class="item-status ${plugin.active ? 'active-dot' : 'inactive-dot'}"></span>
                                    ${plugin.active === false ? `
                                        <button class="delete-btn" onclick="window.app.deleteItem('plugin', '${site.path.replace(/\\/g, '\\\\')}', '${plugin.slug}', false, '${site.name}')">
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                        `).join('') || '--'}
                    </div>
                    </div>
                </div>

                <!-- Column 4: Themes -->
                <div>
                    <div class="accordion-header" onclick="this.classList.toggle('active')">
                        Temas (${site.themes.length})
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                    <div class="accordion-content">
                        <div class="section-title">Temas (${site.themes.length})</div>
                        <div class="list-content">
                        ${site.themes.map(theme => `
                            <div class="list-item">
                                <span class="item-name" title="${theme.name}" style="${!theme.active ? 'text-decoration: line-through; color: #999;' : ''}">${theme.name}</span>
                                <div class="item-meta">
                                    <span class="item-version" style="${theme.new_version ? 'color: var(--danger); font-weight: 700;' : ''}">
                                        v${theme.version}
                                        ${theme.new_version ? `<span style="font-size: 0.6rem; display: block;">Nova: ${theme.new_version}</span>` : ''}
                                    </span>
                                     <span class="item-status ${theme.active ? 'active-dot' : 'inactive-dot'}"></span>
                                    ${theme.active === false ? `
                                        <button class="delete-btn" onclick="window.app.deleteItem('theme', '${site.path.replace(/\\/g, '\\\\')}', '${theme.slug}', false, '${site.name}')">
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                        `).join('') || '--'}
                    </div>
                    </div>
                </div>
            `;
            siteGrid.appendChild(row);
        });
    };

    searchInput.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        const filtered = allSites.filter(site =>
            site.title.toLowerCase().includes(term) ||
            site.name.toLowerCase().includes(term) ||
            site.path.toLowerCase().includes(term)
        );
        renderSites(filtered);
    });

    const deleteSite = async (folderName) => {
        const confirmText = prompt(`ATENÇÃO: Você está prestes a excluir todos os arquivos e o banco de dados da pasta "${folderName}" permanentemente.\n\nPara confirmar, digite exatamente o nome da pasta abaixo:\n\n${folderName}`);

        if (confirmText !== folderName) {
            if (confirmText !== null) {
                alert('Nome incorreto. Exclusão cancelada por segurança.');
            }
            return;
        }

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_site', folder: folderName })
            });
            const result = await response.json();
            if (result.status === 'success') {
                alert(result.message);
                fetchSites();
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (error) {
            console.error('Erro ao excluir site:', error);
            alert('Erro ao excluir o site: ' + error.message);
        }
    };

    const backupSite = async (folderName) => {
        if (!confirm(`Deseja gerar um novo Backup Migration para o site "${folderName}"?\n\nIsso pode demorar alguns minutos dependendo do tamanho do site.`)) return;

        const overlay = document.getElementById('backup-overlay');
        const overlayText = document.getElementById('backup-overlay-text');
        const btn = document.getElementById('backup-btn-' + folderName);
        const originalText = btn ? btn.innerHTML : '';

        // Show overlay
        if (overlay) {
            overlayText.textContent = `Gerando backup de "${folderName}"... Aguarde.`;
            overlay.style.display = 'flex';
        }
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '⏳ Aguarde...';
        }

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'backup_site', folder: folderName })
            });
            const text = await response.text();
            if (!text || text.trim() === '') {
                throw new Error('O servidor não retornou resposta. O backup pode ter excedido o tempo limite do PHP. Verifique a pasta happy-backup para confirmar se o arquivo foi gerado.');
            }
            let result;
            try {
                result = JSON.parse(text);
            } catch (parseErr) {
                console.error('Resposta inválida do servidor:', text);
                throw new Error('Resposta inválida do servidor. Possível erro de PHP. Verifique a pasta happy-backup.');
            }
            if (result.status === 'success') {
                alert(result.message);
                fetchSites(folderName);
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (error) {
            console.error('Erro ao gerar backup:', error);
            alert('Erro ao gerar backup: ' + error.message);
        } finally {
            if (overlay) overlay.style.display = 'none';
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    };

    const deleteBackup = async (folderName, backupName) => {
        if (!confirm(`Tem certeza que deseja excluir o backup ${backupName} permanentemente?`)) return;

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_backup', folder: folderName, backup: backupName })
            });
            const result = await response.json();
            if (result.status === 'success') {
                fetchSites(folderName);
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (error) {
            console.error('Erro ao excluir backup:', error);
            alert('Erro ao excluir o backup: ' + error.message);
        }
    };

    // ==================== ORPHAN BACKUPS ====================
    const fetchOrphanBackups = async () => {
        try {
            const response = await fetch('api.php?action=orphan_backups');
            const result = await response.json();
            if (result.status === 'success') {
                renderOrphanBackups(result.data);
            }
        } catch (error) {
            console.error('Erro ao buscar backups órfãos:', error);
        }
    };

    const renderOrphanBackups = (orphans) => {
        const section = document.getElementById('orphan-backups-section');
        const grid = document.getElementById('orphan-backups-grid');
        const countBadge = document.getElementById('orphan-count');

        if (!section || !grid) return;

        if (!orphans || orphans.length === 0) {
            section.style.display = 'none';
            grid.innerHTML = '';
            return;
        }

        section.style.display = 'block';
        const totalFiles = orphans.reduce((sum, o) => sum + o.backups.length, 0);
        countBadge.textContent = totalFiles + (totalFiles === 1 ? ' arquivo' : ' arquivos');

        grid.innerHTML = '';
        orphans.forEach((orphan, idx) => {
            const card = document.createElement('div');
            card.style.cssText = 'background: white; border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-left: 4px solid #ffc107;';
            card.style.animation = `fadeIn 0.3s ease forwards ${idx * 0.08}s`;

            const totalSize = orphan.backups.reduce((sum, b) => sum + b.size, 0);
            const sizeMB = (totalSize / 1024 / 1024).toFixed(2);

            card.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <span style="font-weight: 700; font-size: 1rem; color: #333;">📁 /${orphan.folder}</span>
                        <span style="font-size: 0.75rem; color: #999; margin-left: 0.5rem;">${orphan.backups.length} backup(s) · ${sizeMB} MB</span>
                    </div>
                    <button class="btn" style="background-color: #dc3545; color: white; font-size: 0.7rem; padding: 0.25rem 0.6rem;" onclick="window.app.deleteOrphanFolder('${orphan.folder}')">
                        🗑️ Excluir Todos
                    </button>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                    ${orphan.backups.map(b => `
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; padding: 0.5rem 0.75rem; background: #f8f9fa; border-radius: 4px;">
                            <div>
                                <a href="happy-backup/${orphan.folder}/${b.name}" download style="color: #0d6efd; text-decoration: none; font-weight: 600;">💾 ${b.date}</a>
                                <span style="color: #888; margin-left: 0.5rem;">(${(b.size / 1024 / 1024).toFixed(2)} MB)</span>
                            </div>
                            <button class="delete-btn" onclick="window.app.deleteOrphanBackup('${orphan.folder}', '${b.name}')" title="Excluir este backup">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </div>
                    `).join('')}
                </div>
            `;
            grid.appendChild(card);
        });
    };

    const deleteOrphanBackup = async (folderName, backupName) => {
        if (!confirm(`Excluir o backup ${backupName} permanentemente?`)) return;

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_backup', folder: folderName, backup: backupName })
            });
            const result = await response.json();
            if (result.status === 'success') {
                fetchOrphanBackups();
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (error) {
            alert('Erro ao excluir backup: ' + error.message);
        }
    };

    const deleteOrphanFolder = async (folderName) => {
        if (!confirm(`Excluir TODOS os backups da pasta "${folderName}" permanentemente?\n\nEssa ação não pode ser desfeita.`)) return;

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_orphan_folder', folder: folderName })
            });
            const result = await response.json();
            if (result.status === 'success') {
                alert(result.message);
                fetchOrphanBackups();
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (error) {
            alert('Erro ao excluir backups: ' + error.message);
        }
    };

    window.app = { deleteItem, deleteSite, backupSite, deleteBackup, deleteOrphanBackup, deleteOrphanFolder };

    const installBtn = document.getElementById('install-btn');
    const installModal = document.getElementById('install-modal');
    const cancelInstallBtn = document.getElementById('cancel-install');
    const confirmInstallBtn = document.getElementById('confirm-install');
    const installFolderInput = document.getElementById('install-folder');
    const installDbCheckbox = document.getElementById('install-db');

    if (installBtn) {
        installBtn.addEventListener('click', () => {
            installModal.style.display = 'flex';
        });

        cancelInstallBtn.addEventListener('click', () => {
            installModal.style.display = 'none';
        });

        confirmInstallBtn.addEventListener('click', async () => {
            const folder = installFolderInput.value.trim();
            const createDb = installDbCheckbox.checked;

            if (!folder) {
                alert('Informe o nome da subpasta.');
                return;
            }

            if (!/^[a-zA-Z0-9_-]+$/.test(folder)) {
                alert('O nome da subpasta deve conter apenas letras, números, hifens e underlines.');
                return;
            }

            confirmInstallBtn.disabled = true;
            confirmInstallBtn.innerText = 'Instalando... (Aguarde)';

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'install', folder, createDb })
                });
                const result = await response.json();

                if (result.status === 'success') {
                    installModal.style.display = 'none';
                    installFolderInput.value = '';

                    // Redireciona para a tela de instalação em nova aba
                    window.open(folder + '/wp-admin/install.php', '_blank');

                    fetchSites();
                } else {
                    alert('Erro: ' + result.message);
                }
            } catch (error) {
                console.error(error);
                alert('Erro ao instalar o WordPress.');
            } finally {
                confirmInstallBtn.disabled = false;
                confirmInstallBtn.innerText = 'Instalar';
            }
        });
    }

    scanBtn.addEventListener('click', fetchSites);
    fetchSites();
});
