<?php
/**
 * MENU LATERAL ADMINISTRATIVO - SEM EMOJIS
 * Versão: 1.0
 */
if (!isset($_SESSION['admin_logado'])) {
    die('Acesso negado');
}
?>
<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis2.png" alt="Adonis">
        <span class="sidebar-logo-title">Adonis</span>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Principal</div>
        <a href="/backend/admin/dashboard.php" class="sidebar-link"><span class="nav-icon">■</span> Todos os Pedidos</a>
        <a href="/backend/admin/dashboard.php?status=Pre-OS" class="sidebar-link"><span class="nav-icon">■</span> Pré-OS</a>
        <a href="/backend/admin/dashboard.php?status=Em analise" class="sidebar-link"><span class="nav-icon">■</span> Em Análise</a>
        <a href="/backend/admin/dashboard.php?status=Orcada" class="sidebar-link"><span class="nav-icon">■</span> Orçadas</a>
        <a href="/backend/admin/dashboard.php?status=Aguardando aprovacao" class="sidebar-link"><span class="nav-icon">■</span> Aguard. Aprovação</a>
    </div>
    <hr class="sidebar-divider">
    <div class="sidebar-section">
        <div class="sidebar-section-label">Execução</div>
        <a href="/backend/admin/dashboard.php?status=Aprovada" class="sidebar-link"><span class="nav-icon">■</span> Aguard. Pagamento</a>
        <a href="/backend/admin/dashboard.php?status=Instrumento recebido" class="sidebar-link"><span class="nav-icon">■</span> Instr. Recebido</a>
        <a href="/backend/admin/dashboard.php?status=Em desenvolvimento" class="sidebar-link"><span class="nav-icon">■</span> Em Execução</a>
        <a href="/backend/admin/dashboard.php?status=Servico finalizado" class="sidebar-link"><span class="nav-icon">■</span> Serviço Finalizado</a>
        <a href="/backend/admin/dashboard.php?status=Pronto para retirada" class="sidebar-link"><span class="nav-icon">■</span> Pronto p/ Retirada</a>
    </div>
    <hr class="sidebar-divider">
    <div class="sidebar-section">
        <div class="sidebar-section-label">Encerrados</div>
        <a href="/backend/admin/dashboard.php?status=Entregue" class="sidebar-link"><span class="nav-icon">■</span> Entregues</a>
        <a href="/backend/admin/dashboard.php?status=Reprovada" class="sidebar-link"><span class="nav-icon">■</span> Reprovados</a>
        <a href="/backend/admin/dashboard.php?status=Cancelada" class="sidebar-link"><span class="nav-icon">■</span> Cancelados</a>
    </div>
    <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?php echo strtoupper(substr($_SESSION['admin_nome']??'A',0,1)); ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['admin_nome']??'Admin'); ?></div>
            <div class="sidebar-user-role">Administrador</div>
        </div>
        <a href="/backend/admin/logout.php" class="sidebar-logout" title="Sair">→</a>
    </div>
</aside>