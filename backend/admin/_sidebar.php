<?php
/**
 * _sidebar.php — sidebar colasável compartilhada
 * Requer: $sidebar_nav, $sidebar_stats, $filtro_status, $current_page
 * Ícones: Material Symbols Outlined (carregado via admin.css ou head)
 */
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="/frontend/public/assets/img/Logo-Adonis2.png" alt="Adonis">
        <span class="sidebar-logo-title">Adonis</span>
    </div>

    <nav style="flex:1;padding:8px 0">
    <?php foreach ($sidebar_nav as $item):
        $is_group_link_active = ($item['tipo']==='link' && basename($current_page)===basename($item['href']) && ($filtro_status==='' || !isset($filtro_status)));
        if ($item['tipo'] === 'link'):
    ?>
        <a href="<?php echo $item['href']; ?>" class="nav-item-link<?php echo $is_group_link_active?' active':''; ?>">
            <span class="nav-icon material-symbols-outlined"><?php echo $item['icon']; ?></span>
            <?php echo htmlspecialchars($item['label']); ?>
        </a>

    <?php elseif ($item['tipo'] === 'group'):
        $grupo_aberto = grupoAtivoSidebar($item['itens'], $filtro_status, $current_page);
        $grupo_total  = 0;
        foreach ($item['itens'] as $it) {
            if (!empty($it['status'])) $grupo_total += ($sidebar_stats[$it['status']] ?? 0);
        }
    ?>
        <div class="nav-group">
            <div class="nav-group-toggle<?php echo $grupo_aberto?' open':''; ?>"
                 onclick="toggleGroup('<?php echo $item['id']; ?>')" id="toggle-<?php echo $item['id']; ?>">
                <span class="nav-icon material-symbols-outlined"><?php echo $item['icon']; ?></span>
                <span class="nav-label"><?php echo htmlspecialchars($item['label']); ?></span>
                <?php if ($grupo_total > 0): ?>
                <span class="nav-total"><?php echo $grupo_total; ?></span>
                <?php endif; ?>
                <span class="nav-chevron material-symbols-outlined">chevron_right</span>
            </div>
            <div class="nav-sub<?php echo $grupo_aberto?' open':''; ?>" id="sub-<?php echo $item['id']; ?>">
                <?php foreach ($item['itens'] as $it):
                    if ($it['status'] === null) {
                        $sub_active = (basename($current_page) === basename($it['href']));
                    } elseif ($it['status'] === '') {
                        $sub_active = ($filtro_status === '' && basename($current_page) === 'dashboard.php');
                    } else {
                        $sub_active = ($filtro_status === $it['status']);
                    }
                    $sub_count = (!empty($it['status'])) ? ($sidebar_stats[$it['status']] ?? 0) : 0;
                ?>
                <a href="<?php echo htmlspecialchars($it['href']); ?>"
                   class="nav-sub-item<?php echo $sub_active?' active':''; ?>">
                    <?php echo htmlspecialchars($it['label']); ?>
                    <?php if ($sub_count > 0): ?>
                    <span class="sub-badge"><?php echo $sub_count; ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

    <?php endif; ?>
    <?php if (in_array($item['id'], ['nav-dashboard','nav-enc'])): ?>
    <hr class="sidebar-divider">
    <?php endif; ?>
    <?php endforeach; ?>
    </nav>

    <a href="/backend/admin/perfil.php" class="sidebar-user" title="Editar perfil">
        <div class="sidebar-user-avatar"><?php echo strtoupper(substr($_SESSION['admin_nome']??'A',0,1)); ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['admin_nome']??'Admin'); ?></div>
            <div class="sidebar-user-role"><?php echo ucfirst($_SESSION['admin_tipo']??'Admin'); ?></div>
        </div>
    </a>
    
    <a href="/backend/admin/logout.php" class="sidebar-logout-btn" title="Sair">
        <span class="material-symbols-outlined">logout</span>
        Sair
    </a>
</aside>
