<?php
/**
 * _sidebar_data.php
 * Carrega os dados necessários para a sidebar (contagens por status).
 * Incluído antes do HTML em cada página do admin.
 */
if (!isset($conn)) {
    require_once '../config/Database.php';
    $db   = new Database();
    $conn = $db->getConnection();
}
if (!isset($current_page)) $current_page = '';
if (!isset($filtro_status)) $filtro_status = '';

try {
    $rows = $conn->query('SELECT status, COUNT(*) as total FROM pre_os GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
    $sidebar_stats = [];
    foreach ($rows as $r) $sidebar_stats[$r['status']] = (int)$r['total'];
} catch (Exception $e) { $sidebar_stats = []; }

$sidebar_nav = [
    [
        'id'    => 'nav-dashboard',
        'icon'  => 'dashboard',
        'label' => 'Dashboard',
        'href'  => 'dashboard.php',
        'tipo'  => 'link',
    ],
    [
        'id'    => 'nav-os',
        'icon'  => 'receipt_long',
        'label' => 'Ordens de Serviço',
        'tipo'  => 'group',
        'itens' => [
            ['href'=>'dashboard.php',                            'label'=>'Todos os Pedidos',   'status'=>''],
            ['href'=>'dashboard.php?status=Pre-OS',              'label'=>'Pré-OS',             'status'=>'Pre-OS'],
            ['href'=>'dashboard.php?status=Em analise',          'label'=>'Em Análise',         'status'=>'Em analise'],
            ['href'=>'dashboard.php?status=Orcada',              'label'=>'Orçadas',            'status'=>'Orcada'],
            ['href'=>'dashboard.php?status=Aguardando aprovacao','label'=>'Aguard. Aprovação', 'status'=>'Aguardando aprovacao'],
        ],
    ],
    [
        'id'    => 'nav-exec',
        'icon'  => 'build',
        'label' => 'Execução',
        'tipo'  => 'group',
        'itens' => [
            ['href'=>'dashboard.php?status=Aprovada',             'label'=>'Aguard. Pagamento',  'status'=>'Aprovada'],
            ['href'=>'dashboard.php?status=Instrumento recebido', 'label'=>'Instr. Recebido',    'status'=>'Instrumento recebido'],
            ['href'=>'dashboard.php?status=Em desenvolvimento',   'label'=>'Em Execução',        'status'=>'Em desenvolvimento'],
            ['href'=>'dashboard.php?status=Servico finalizado',   'label'=>'Serviço Finalizado', 'status'=>'Servico finalizado'],
            ['href'=>'dashboard.php?status=Pronto para retirada', 'label'=>'Pronto p/ Retirada', 'status'=>'Pronto para retirada'],
        ],
    ],
    [
        'id'    => 'nav-enc',
        'icon'  => 'archive',
        'label' => 'Encerrados',
        'tipo'  => 'group',
        'itens' => [
            ['href'=>'dashboard.php?status=Entregue',  'label'=>'Entregues',  'status'=>'Entregue'],
            ['href'=>'dashboard.php?status=Reprovada', 'label'=>'Reprovados', 'status'=>'Reprovada'],
            ['href'=>'dashboard.php?status=Cancelada', 'label'=>'Cancelados', 'status'=>'Cancelada'],
        ],
    ],
    [
        'id'    => 'nav-cad',
        'icon'  => 'folder_open',
        'label' => 'Cadastros',
        'tipo'  => 'group',
        'itens' => [
            ['href'=>'clientes.php',     'label'=>'Clientes',     'status'=>null],
            ['href'=>'instrumentos.php', 'label'=>'Catálogo',     'status'=>null],
            ['href'=>'servicos.php',     'label'=>'Serviços',     'status'=>null],
            ['href'=>'insumos.php',      'label'=>'Insumos',      'status'=>null],
        ],
    ],
    [
        'id'    => 'nav-cfg',
        'icon'  => 'settings',
        'label' => 'Configurações',
        'href'  => 'configuracoes.php',
        'tipo'  => 'link',
    ],
];

// Adiciona item Usuários apenas para admins
if (($_SESSION['admin_tipo'] ?? '') === 'admin') {
    $sidebar_nav[] = [
        'id'    => 'nav-users',
        'icon'  => 'admin_panel_settings',
        'label' => 'Usuários',
        'href'  => 'users.php',
        'tipo'  => 'link',
    ];
}

function grupoAtivoSidebar($itens, $filtro_status, $current_page) {
    foreach ($itens as $it) {
        if ($it['status'] === null) {
            if (basename($current_page) === basename($it['href'])) return true;
        } else {
            if ($filtro_status === $it['status']) return true;
        }
    }
    return false;
}
