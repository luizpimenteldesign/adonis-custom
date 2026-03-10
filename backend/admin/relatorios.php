<?php
/**
 * relatorios.php — Relatórios Adonis
 * 7 relatórios | Exportação PDF (print) e XLS (SheetJS)
 */
require_once 'auth.php';
require_once '_sidebar_data.php';
$current_page = 'relatorios.php';
$v = time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Relatórios — Adonis Admin</title>
<link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
<link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
<link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo $v; ?>">
<!-- SheetJS para exportação XLS -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<style>
/* ── Layout ── */
.page-content{flex:1;padding:20px}
.rel-header{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.rel-header-title{font-family:'Google Sans',sans-serif;font-size:20px;font-weight:500;color:var(--g-text);flex:1}
.rel-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:20px}
.rel-tab{padding:8px 16px;border-radius:20px;font-size:13px;font-weight:500;cursor:pointer;border:1px solid var(--g-border);background:var(--g-surface);color:var(--g-text-2);transition:all .15s;display:flex;align-items:center;gap:6px}
.rel-tab.active{background:var(--g-blue);color:#fff;border-color:var(--g-blue)}
.rel-tab:hover:not(.active){background:var(--g-hover)}
/* ── Filtros ── */
.rel-filtros{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:20px;padding:14px 16px;background:var(--g-surface);border:1px solid var(--g-border);border-radius:var(--g-radius-lg)}
.rel-filtros label{font-size:12px;font-weight:600;color:var(--g-text-2);text-transform:uppercase;letter-spacing:.4px}
.rel-filtros input[type=date]{padding:7px 10px;border:1px solid var(--g-border);border-radius:8px;font-size:13px;background:var(--g-bg);color:var(--g-text)}
.rel-filtros .btn{padding:8px 16px;font-size:13px}
.rel-export{display:flex;gap:8px;margin-left:auto}
/* ── Cards resumo ── */
.rel-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
.rel-card{background:var(--g-surface);border:1px solid var(--g-border);border-radius:var(--g-radius-lg);padding:16px 18px}
.rel-card-label{font-size:11px;font-weight:600;color:var(--g-text-2);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px}
.rel-card-val{font-family:'Google Sans',sans-serif;font-size:22px;font-weight:500;color:var(--g-text)}
.rel-card-val.green{color:var(--g-green)}
.rel-card-val.red{color:var(--g-red)}
.rel-card-val.orange{color:#f59e0b}
/* ── Seções ── */
.rel-sect{background:var(--g-surface);border:1px solid var(--g-border);border-radius:var(--g-radius-lg);margin-bottom:16px;overflow:hidden}
.rel-sect-title{font-family:'Google Sans',sans-serif;font-size:12px;font-weight:600;color:var(--g-text-2);text-transform:uppercase;letter-spacing:.4px;padding:14px 20px 10px;border-bottom:1px solid var(--g-border)}
/* ── Tabelas ── */
.rel-table{width:100%;border-collapse:collapse}
.rel-table thead th{text-align:left;padding:10px 16px;font-size:11px;font-weight:600;color:var(--g-text-2);text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--g-border);background:var(--g-bg)}
.rel-table tbody td{padding:11px 16px;font-size:13px;border-bottom:1px solid var(--g-border);color:var(--g-text);vertical-align:middle}
.rel-table tbody tr:last-child td{border-bottom:none}
.rel-table tbody tr:hover{background:var(--g-hover)}
.rel-table .num{text-align:right}
.rel-table tfoot td{padding:11px 16px;font-size:13px;font-weight:600;border-top:2px solid var(--g-border);background:var(--g-bg)}
/* ── Barra de progresso ── */
.bar-wrap{height:6px;background:var(--g-border);border-radius:3px;overflow:hidden;min-width:80px}
.bar-fill{height:100%;border-radius:3px;background:var(--g-blue);transition:width .4s}
.bar-fill.green{background:var(--g-green)}
.bar-fill.orange{background:#f59e0b}
.bar-fill.red{background:var(--g-red)}
/* ── Badge estoque ── */
.est-ok{color:var(--g-green);font-weight:600}
.est-baixo{color:#f59e0b;font-weight:600}
.est-zero{color:var(--g-red);font-weight:600}
/* ── Loading / vazio ── */
.rel-loading{padding:48px 20px;text-align:center;color:var(--g-text-3);font-size:14px}
.rel-loading .spin{display:inline-block;width:28px;height:28px;border:3px solid var(--g-border);border-top-color:var(--g-blue);border-radius:50%;animation:spin .7s linear infinite;margin-bottom:12px}
@keyframes spin{to{transform:rotate(360deg)}}
/* ── Print ── */
@media print{
    .sidebar,.topbar,.bottom-nav,.rel-tabs,.rel-filtros .btn,.rel-export,button{display:none!important}
    .app-layout{display:block!important}
    .main-content{margin:0!important;padding:0!important}
    .rel-sect{break-inside:avoid;border:1px solid #ccc!important}
    body{background:#fff!important}
    .page-content{padding:0!important}
}
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="app-layout">
<?php require_once '_sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <button class="btn-menu" onclick="toggleSidebar()"><span class="material-symbols-outlined">menu</span></button>
        <div style="display:flex;align-items:center;gap:8px">
            <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis2.png" style="height:26px" alt="Adonis">
        </div>
        <span class="topbar-title">Relatórios</span>
        <a href="logout.php" class="material-symbols-outlined sidebar-logout" title="Sair">logout</a>
    </div>

    <div class="page-content">

        <div class="rel-header">
            <div>
                <div class="rel-header-title"><span class="material-symbols-outlined" style="vertical-align:middle;margin-right:6px">bar_chart</span>Relatórios</div>
            </div>
        </div>

        <!-- ABAS -->
        <div class="rel-tabs">
            <button class="rel-tab active" data-rel="financeiro" onclick="trocarAba(this)">
                <span class="material-symbols-outlined" style="font-size:16px">payments</span> Financeiro
            </button>
            <button class="rel-tab" data-rel="status" onclick="trocarAba(this)">
                <span class="material-symbols-outlined" style="font-size:16px">list_alt</span> Pedidos por Status
            </button>
            <button class="rel-tab" data-rel="clientes" onclick="trocarAba(this)">
                <span class="material-symbols-outlined" style="font-size:16px">group</span> Clientes
            </button>
            <button class="rel-tab" data-rel="servicos" onclick="trocarAba(this)">
                <span class="material-symbols-outlined" style="font-size:16px">build</span> Serviços
            </button>
            <button class="rel-tab" data-rel="insumos" onclick="trocarAba(this)">
                <span class="material-symbols-outlined" style="font-size:16px">inventory_2</span> Estoque
            </button>
            <button class="rel-tab" data-rel="tempo" onclick="trocarAba(this)">
                <span class="material-symbols-outlined" style="font-size:16px">schedule</span> Tempo de Execução
            </button>
            <button class="rel-tab" data-rel="instrumentos" onclick="trocarAba(this)">
                <span class="material-symbols-outlined" style="font-size:16px">music_note</span> Instrumentos
            </button>
        </div>

        <!-- FILTROS -->
        <div class="rel-filtros" id="rel-filtros">
            <label>De</label>
            <input type="date" id="filtro-de" value="<?php echo date('Y-m-01'); ?>">
            <label>Até</label>
            <input type="date" id="filtro-ate" value="<?php echo date('Y-m-d'); ?>">
            <button class="btn btn-primary" onclick="carregarRelatorio()"><span class="material-symbols-outlined" style="font-size:16px">refresh</span> Atualizar</button>
            <div class="rel-export">
                <button class="btn btn-secondary" onclick="exportarPDF()" title="Exportar PDF">
                    <span class="material-symbols-outlined" style="font-size:16px">picture_as_pdf</span> PDF
                </button>
                <button class="btn btn-secondary" onclick="exportarXLS()" title="Exportar Excel">
                    <span class="material-symbols-outlined" style="font-size:16px">table_view</span> XLS
                </button>
            </div>
        </div>

        <!-- CONTEÚDO DINÂMICO -->
        <div id="rel-corpo">
            <div class="rel-loading"><div class="spin"></div><br>Carregando...</div>
        </div>

        <div style="height:24px"></div>
    </div>
</main>
</div>

<nav class="bottom-nav">
    <a href="dashboard.php"><span class="material-symbols-outlined nav-icon">dashboard</span>Painel</a>
    <a href="relatorios.php" class="active"><span class="material-symbols-outlined nav-icon">bar_chart</span>Relatórios</a>
    <a href="insumos.php"><span class="material-symbols-outlined nav-icon">inventory_2</span>Insumos</a>
    <a href="logout.php"><span class="material-symbols-outlined nav-icon">logout</span>Sair</a>
</nav>

<script>
let _abaAtual = 'financeiro';
let _dadosAtual = null;

const STATUS_LABEL = {
    'Pre-OS':'Pré-OS','Em analise':'Em Análise','Orcada':'Orçada',
    'Aguardando aprovacao':'Aguard. Aprovação','Aprovada':'Aguard. Pagamento',
    'Pagamento recebido':'Pgto Recebido','Instrumento recebido':'Instr. Recebido',
    'Servico iniciado':'Serviço Iniciado','Em desenvolvimento':'Em Execução',
    'Servico finalizado':'Serviço Finalizado','Pronto para retirada':'Pronto p/ Retirada',
    'Aguardando pagamento retirada':'Pag. Pend. Retirada','Entregue':'Entregue',
    'Reprovada':'Reprovada','Cancelada':'Cancelada'
};

function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}
function closeSidebar(){
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
}
function toggleGroup(id){
    const t=document.getElementById('toggle-'+id);
    const s=document.getElementById('sub-'+id);
    t.classList.toggle('open'); s.classList.toggle('open');
    const e=JSON.parse(localStorage.getItem('nav_grupos')||'{}');
    e[id]=t.classList.contains('open');
    localStorage.setItem('nav_grupos',JSON.stringify(e));
}
document.addEventListener('DOMContentLoaded',()=>{
    const e=JSON.parse(localStorage.getItem('nav_grupos')||'{}');
    for(const[id,ab] of Object.entries(e)){
        const t=document.getElementById('toggle-'+id);
        const s=document.getElementById('sub-'+id);
        if(!t||!s)continue;
        if(ab&&!t.classList.contains('open')){t.classList.add('open');s.classList.add('open');}
    }
    carregarRelatorio();
});

function trocarAba(el){
    document.querySelectorAll('.rel-tab').forEach(t=>t.classList.remove('active'));
    el.classList.add('active');
    _abaAtual = el.dataset.rel;
    // oculta filtro de data para estoque (não tem período)
    document.getElementById('rel-filtros').style.display = (_abaAtual==='insumos') ? 'none' : 'flex';
    carregarRelatorio();
}

function carregarRelatorio(){
    const de  = document.getElementById('filtro-de').value;
    const ate = document.getElementById('filtro-ate').value;
    document.getElementById('rel-corpo').innerHTML = '<div class="rel-loading"><div class="spin"></div><br>Carregando...</div>';
    fetch(`api_relatorios.php?tipo=${_abaAtual}&de=${de}&ate=${ate}`)
        .then(r=>r.json())
        .then(data=>{
            if(!data.ok){ document.getElementById('rel-corpo').innerHTML='<div class="rel-loading">Erro: '+esc(data.erro||'desconhecido')+'</div>'; return; }
            _dadosAtual = data;
            document.getElementById('rel-corpo').innerHTML = renderizarAba(_abaAtual, data);
        })
        .catch(e=>{ document.getElementById('rel-corpo').innerHTML='<div class="rel-loading">Erro de conexão</div>'; });
}

function fmt(v){ const n=parseFloat(v)||0; return 'R$\u00a0'+n.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
function fmtN(v){ return (parseFloat(v)||0).toFixed(1).replace('.',','); }
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function mesLabel(m){ const [y,mo]=m.split('-'); const nomes=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez']; return nomes[parseInt(mo)-1]+'/'+y.slice(2); }
function barHtml(v,max,cls=''){
    const pct=max>0?Math.round((v/max)*100):0;
    return `<div class="bar-wrap"><div class="bar-fill ${cls}" style="width:${pct}%"></div></div>`;
}

// ─── RENDERIZADORES ───────────────────────────────────────────────────────────

function renderizarAba(tipo, d){
    switch(tipo){
        case 'financeiro':    return renderFinanceiro(d);
        case 'status':        return renderStatus(d);
        case 'clientes':      return renderClientes(d);
        case 'servicos':      return renderServicos(d);
        case 'insumos':       return renderInsumos(d);
        case 'tempo':         return renderTempo(d);
        case 'instrumentos':  return renderInstrumentos(d);
        default: return '';
    }
}

function renderFinanceiro(d){
    const t = d.totais||{};
    const maxMes = Math.max(...(d.porMes||[]).map(m=>parseFloat(m.total)||0), 1);
    let html = `
    <div class="rel-cards">
        <div class="rel-card"><div class="rel-card-label">Receita total</div><div class="rel-card-val green">${fmt(t.total||0)}</div></div>
        <div class="rel-card"><div class="rel-card-label">Pedidos entregues</div><div class="rel-card-val">${t.qtd||0}</div></div>
        <div class="rel-card"><div class="rel-card-label">Ticket médio</div><div class="rel-card-val">${fmt(t.ticket||0)}</div></div>
    </div>`;

    // Por mês
    html += `<div class="rel-sect"><div class="rel-sect-title">Receita por mês</div><div style="overflow-x:auto"><table class="rel-table">
    <thead><tr><th>Mês</th><th class="num">Pedidos</th><th class="num">Receita</th><th>Distribuição</th></tr></thead><tbody>`;
    if(!(d.porMes||[]).length){ html+='<tr><td colspan="4" style="text-align:center;color:var(--g-text-3);padding:20px">Nenhum dado no período</td></tr>'; }
    (d.porMes||[]).forEach(m=>{
        html+=`<tr><td>${esc(mesLabel(m.mes))}</td><td class="num">${m.qtd}</td><td class="num">${fmt(m.total)}</td><td style="min-width:120px">${barHtml(parseFloat(m.total),maxMes,'green')}</td></tr>`;
    });
    html+='</tbody></table></div></div>';

    // Formas de pagamento
    const maxF = Math.max(...(d.formas||[]).map(f=>parseFloat(f.total)||0), 1);
    html += `<div class="rel-sect"><div class="rel-sect-title">Formas de pagamento</div><div style="overflow-x:auto"><table class="rel-table">
    <thead><tr><th>Forma</th><th class="num">Pedidos</th><th class="num">Total</th><th>%</th></tr></thead><tbody>`;
    const totFormas = (d.formas||[]).reduce((s,f)=>s+(parseFloat(f.total)||0),0);
    if(!(d.formas||[]).length){ html+='<tr><td colspan="4" style="text-align:center;color:var(--g-text-3);padding:20px">Nenhum dado</td></tr>'; }
    (d.formas||[]).forEach(f=>{
        const pct = totFormas>0?((parseFloat(f.total)/totFormas)*100).toFixed(1):0;
        html+=`<tr><td>${esc(f.forma_pagamento||'—')}</td><td class="num">${f.qtd}</td><td class="num">${fmt(f.total)}</td><td>${pct}%</td></tr>`;
    });
    html+='</tbody></table></div></div>';
    return html;
}

function renderStatus(d){
    const total = (d.porStatus||[]).reduce((s,x)=>s+(parseInt(x.qtd)||0),0);
    const maxQ  = Math.max(...(d.porStatus||[]).map(x=>parseInt(x.qtd)||0), 1);
    const tm    = d.tempoMedio||{};
    let html = `
    <div class="rel-cards">
        <div class="rel-card"><div class="rel-card-label">Total de pedidos</div><div class="rel-card-val">${total}</div></div>
        <div class="rel-card"><div class="rel-card-label">Tempo médio (dias)</div><div class="rel-card-val">${fmtN(tm.media_dias||0)}</div></div>
        <div class="rel-card"><div class="rel-card-label">Mais rápido</div><div class="rel-card-val green">${tm.minimo||'—'} dias</div></div>
        <div class="rel-card"><div class="rel-card-label">Mais demorado</div><div class="rel-card-val orange">${tm.maximo||'—'} dias</div></div>
    </div>
    <div class="rel-sect"><div class="rel-sect-title">Pedidos por status</div><div style="overflow-x:auto"><table class="rel-table">
    <thead><tr><th>Status</th><th class="num">Quantidade</th><th class="num">%</th><th>Distribuição</th></tr></thead><tbody>`;
    if(!(d.porStatus||[]).length){ html+='<tr><td colspan="4" style="text-align:center;color:var(--g-text-3);padding:20px">Nenhum dado no período</td></tr>'; }
    (d.porStatus||[]).forEach(x=>{
        const pct = total>0?((parseInt(x.qtd)/total)*100).toFixed(1):0;
        html+=`<tr><td>${esc(STATUS_LABEL[x.status]||x.status)}</td><td class="num">${x.qtd}</td><td class="num">${pct}%</td><td style="min-width:120px">${barHtml(parseInt(x.qtd),maxQ)}</td></tr>`;
    });
    html+='</tbody></table></div></div>';
    return html;
}

function renderClientes(d){
    const tc   = d.totalClientes||{};
    const maxR = Math.max(...(d.clientes||[]).map(c=>parseFloat(c.receita)||0), 1);
    let html = `
    <div class="rel-cards">
        <div class="rel-card"><div class="rel-card-label">Clientes ativos</div><div class="rel-card-val">${tc.total||0}</div></div>
        <div class="rel-card"><div class="rel-card-label">Top clientes (50)</div><div class="rel-card-val">${(d.clientes||[]).length}</div></div>
    </div>
    <div class="rel-sect"><div class="rel-sect-title">Top clientes por receita</div><div style="overflow-x:auto"><table class="rel-table">
    <thead><tr><th>#</th><th>Cliente</th><th>Telefone</th><th class="num">Pedidos</th><th class="num">Receita</th><th>Distribuição</th></tr></thead><tbody>`;
    if(!(d.clientes||[]).length){ html+='<tr><td colspan="6" style="text-align:center;color:var(--g-text-3);padding:20px">Nenhum dado no período</td></tr>'; }
    (d.clientes||[]).forEach((c,i)=>{
        html+=`<tr><td style="color:var(--g-text-3)">${i+1}</td><td>${esc(c.nome)}</td><td><a href="https://wa.me/55${(c.telefone||'').replace(/\D/g,'')}" target="_blank">${esc(c.telefone||'—')}</a></td><td class="num">${c.pedidos}</td><td class="num">${fmt(c.receita)}</td><td style="min-width:100px">${barHtml(parseFloat(c.receita),maxR,'green')}</td></tr>`;
    });
    html+='</tbody></table></div></div>';
    return html;
}

function renderServicos(d){
    const maxQ = Math.max(...(d.servicos||[]).map(s=>parseInt(s.qtd)||0), 1);
    const totQ = (d.servicos||[]).reduce((s,x)=>s+(parseInt(x.qtd)||0),0);
    let html = `
    <div class="rel-cards">
        <div class="rel-card"><div class="rel-card-label">Serviços distintos</div><div class="rel-card-val">${(d.servicos||[]).length}</div></div>
        <div class="rel-card"><div class="rel-card-label">Total executado</div><div class="rel-card-val">${totQ}</div></div>
        <div class="rel-card"><div class="rel-card-label">Receita base</div><div class="rel-card-val green">${fmt((d.servicos||[]).reduce((s,x)=>s+(parseFloat(x.receita_base)||0),0))}</div></div>
    </div>
    <div class="rel-sect"><div class="rel-sect-title">Serviços mais executados</div><div style="overflow-x:auto"><table class="rel-table">
    <thead><tr><th>#</th><th>Serviço</th><th class="num">Qtd</th><th class="num">Valor base unit.</th><th class="num">Receita base</th><th>Distribuição</th></tr></thead><tbody>`;
    if(!(d.servicos||[]).length){ html+='<tr><td colspan="6" style="text-align:center;color:var(--g-text-3);padding:20px">Nenhum dado no período</td></tr>'; }
    (d.servicos||[]).forEach((s,i)=>{
        html+=`<tr><td style="color:var(--g-text-3)">${i+1}</td><td>${esc(s.nome)}</td><td class="num">${s.qtd}</td><td class="num">${fmt(s.valor_base)}</td><td class="num">${fmt(s.receita_base)}</td><td style="min-width:120px">${barHtml(parseInt(s.qtd),maxQ)}</td></tr>`;
    });
    html+='</tbody></table></div></div>';
    return html;
}

function renderInsumos(d){
    const maxE = Math.max(...(d.insumos||[]).map(i=>parseFloat(i.estoque)||0), 1);
    let html = `
    <div class="rel-cards">
        <div class="rel-card"><div class="rel-card-label">Total de itens</div><div class="rel-card-val">${d.total||0}</div></div>
        <div class="rel-card"><div class="rel-card-label">Estoque zerado</div><div class="rel-card-val red">${d.zerados||0}</div></div>
        <div class="rel-card"><div class="rel-card-label">Estoque crítico</div><div class="rel-card-val orange">${d.criticos||0}</div></div>
        <div class="rel-card"><div class="rel-card-label">Valor total estoque</div><div class="rel-card-val green">${fmt(d.valorTotal||0)}</div></div>
    </div>
    <div class="rel-sect"><div class="rel-sect-title">Todos os insumos</div><div style="overflow-x:auto"><table class="rel-table">
    <thead><tr><th>Insumo</th><th>Categoria</th><th>Tipo</th><th>Unid.</th><th class="num">Estoque</th><th class="num">Valor unit.</th><th class="num">Valor total</th></tr></thead><tbody>`;
    if(!(d.insumos||[]).length){ html+='<tr><td colspan="7" style="text-align:center;color:var(--g-text-3);padding:20px">Nenhum insumo cadastrado</td></tr>'; }
    (d.insumos||[]).forEach(i=>{
        const est = parseFloat(i.estoque)||0;
        const cls = est<=0?'est-zero':(est<5?'est-baixo':'est-ok');
        html+=`<tr><td>${esc(i.nome)}</td><td>${esc(i.categoria||'—')}</td><td>${esc(i.tipo_insumo||'—')}</td><td>${esc(i.unidade)}</td><td class="num ${cls}">${fmtN(est)}</td><td class="num">${fmt(i.valorunitario)}</td><td class="num">${fmt(i.valor_total)}</td></tr>`;
    });
    const totValor = (d.insumos||[]).reduce((s,i)=>s+(parseFloat(i.valor_total)||0),0);
    html+=`</tbody><tfoot><tr><td colspan="6">Total</td><td class="num">${fmt(totValor)}</td></tr></tfoot></table></div></div>`;
    return html;
}

function renderTempo(d){
    const g = d.geral||{};
    const maxM = Math.max(...(d.porEtapa||[]).map(e=>parseFloat(e.media_dias)||0), 1);
    let html = `
    <div class="rel-cards">
        <div class="rel-card"><div class="rel-card-label">Média geral</div><div class="rel-card-val">${fmtN(g.media_total||0)} dias</div></div>
        <div class="rel-card"><div class="rel-card-label">Mais rápido</div><div class="rel-card-val green">${g.minimo||'—'} dias</div></div>
        <div class="rel-card"><div class="rel-card-label">Mais demorado</div><div class="rel-card-val orange">${g.maximo||'—'} dias</div></div>
    </div>
    <div class="rel-sect"><div class="rel-sect-title">Tempo médio até cada etapa</div><div style="overflow-x:auto"><table class="rel-table">
    <thead><tr><th>Etapa</th><th class="num">Passagens</th><th class="num">Média (dias)</th><th>Proporção</th></tr></thead><tbody>`;
    if(!(d.porEtapa||[]).length){ html+='<tr><td colspan="4" style="text-align:center;color:var(--g-text-3);padding:20px">Nenhum dado no período</td></tr>'; }
    (d.porEtapa||[]).forEach(e=>{
        html+=`<tr><td>${esc(STATUS_LABEL[e.status]||e.status)}</td><td class="num">${e.qtd}</td><td class="num">${fmtN(e.media_dias)}</td><td style="min-width:120px">${barHtml(parseFloat(e.media_dias),maxM,'orange')}</td></tr>`;
    });
    html+='</tbody></table></div></div>';
    return html;
}

function renderInstrumentos(d){
    const maxQ  = Math.max(...(d.instrumentos||[]).map(i=>parseInt(i.pedidos)||0), 1);
    const maxTP = Math.max(...(d.porTipo||[]).map(t=>parseInt(t.pedidos)||0), 1);
    let html = `
    <div class="rel-cards">
        <div class="rel-card"><div class="rel-card-label">Combinações tipo/marca</div><div class="rel-card-val">${(d.instrumentos||[]).length}</div></div>
        <div class="rel-card"><div class="rel-card-label">Tipos diferentes</div><div class="rel-card-val">${(d.porTipo||[]).length}</div></div>
    </div>`;

    html+=`<div class="rel-sect"><div class="rel-sect-title">Pedidos por tipo</div><div style="overflow-x:auto"><table class="rel-table">
    <thead><tr><th>Tipo</th><th class="num">Pedidos</th><th>Distribuição</th></tr></thead><tbody>`;
    (d.porTipo||[]).forEach(t=>{
        html+=`<tr><td>${esc(t.tipo)}</td><td class="num">${t.pedidos}</td><td style="min-width:120px">${barHtml(parseInt(t.pedidos),maxTP)}</td></tr>`;
    });
    html+='</tbody></table></div></div>';

    html+=`<div class="rel-sect"><div class="rel-sect-title">Top 30 tipo × marca</div><div style="overflow-x:auto"><table class="rel-table">
    <thead><tr><th>#</th><th>Tipo</th><th>Marca</th><th class="num">Pedidos</th><th class="num">Receita</th><th>Distribuição</th></tr></thead><tbody>`;
    (d.instrumentos||[]).forEach((i,idx)=>{
        html+=`<tr><td style="color:var(--g-text-3)">${idx+1}</td><td>${esc(i.tipo)}</td><td>${esc(i.marca||'—')}</td><td class="num">${i.pedidos}</td><td class="num">${fmt(i.receita||0)}</td><td style="min-width:120px">${barHtml(parseInt(i.pedidos),maxQ)}</td></tr>`;
    });
    html+='</tbody></table></div></div>';
    return html;
}

// ─── EXPORTAÇÕES ──────────────────────────────────────────────────────────────

function exportarPDF(){
    const nomes = {
        financeiro:'Financeiro', status:'Pedidos por Status', clientes:'Clientes',
        servicos:'Serviços', insumos:'Estoque', tempo:'Tempo de Execução', instrumentos:'Instrumentos'
    };
    const de  = document.getElementById('filtro-de').value;
    const ate = document.getElementById('filtro-ate').value;
    // Adiciona título antes de imprimir
    const titulo = document.createElement('div');
    titulo.id = 'print-titulo';
    titulo.style.cssText = 'font-family:Google Sans,sans-serif;font-size:18px;font-weight:500;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid #eee';
    titulo.innerHTML = `<strong>Adonis — Relatório: ${nomes[_abaAtual]||_abaAtual}</strong><br><span style="font-size:13px;color:#888">Período: ${de} a ${ate}</span>`;
    const corpo = document.getElementById('rel-corpo');
    corpo.prepend(titulo);
    window.print();
    titulo.remove();
}

function exportarXLS(){
    if(!_dadosAtual){ alert('Carregue o relatório primeiro'); return; }
    const wb = XLSX.utils.book_new();
    const sheets = montarSheetsXLS(_abaAtual, _dadosAtual);
    sheets.forEach(s=> XLSX.utils.book_append_sheet(wb, s.ws, s.nome));
    const de  = document.getElementById('filtro-de').value;
    const ate = document.getElementById('filtro-ate').value;
    const nomes = {
        financeiro:'Financeiro', status:'Status', clientes:'Clientes',
        servicos:'Servicos', insumos:'Estoque', tempo:'Tempo', instrumentos:'Instrumentos'
    };
    XLSX.writeFile(wb, `Adonis_${nomes[_abaAtual]||_abaAtual}_${de}_${ate}.xlsx`);
}

function montarSheetsXLS(tipo, d){
    switch(tipo){
        case 'financeiro':
            return [
                { nome:'Por Mes',  ws: XLSX.utils.aoa_to_sheet([['Mês','Pedidos','Receita (R$)','Ticket Médio'],...(d.porMes||[]).map(m=>[mesLabel(m.mes),+m.qtd,+(+m.total).toFixed(2),+(+m.ticket).toFixed(2)])]) },
                { nome:'Pagamentos', ws: XLSX.utils.aoa_to_sheet([['Forma','Pedidos','Total (R$)'],...(d.formas||[]).map(f=>[f.forma_pagamento,+f.qtd,+(+f.total).toFixed(2)])]) },
            ];
        case 'status':
            return [{ nome:'Status', ws: XLSX.utils.aoa_to_sheet([['Status','Quantidade'],...(d.porStatus||[]).map(x=>[STATUS_LABEL[x.status]||x.status,+x.qtd])]) }];
        case 'clientes':
            return [{ nome:'Clientes', ws: XLSX.utils.aoa_to_sheet([['#','Nome','Telefone','Pedidos','Receita (R$)'],...(d.clientes||[]).map((c,i)=>[i+1,c.nome,c.telefone,+c.pedidos,+(+c.receita).toFixed(2)])]) }];
        case 'servicos':
            return [{ nome:'Serviços', ws: XLSX.utils.aoa_to_sheet([['#','Serviço','Qtd','Valor base','Receita base'],...(d.servicos||[]).map((s,i)=>[i+1,s.nome,+s.qtd,+(+s.valor_base).toFixed(2),+(+s.receita_base).toFixed(2)])]) }];
        case 'insumos':
            return [{ nome:'Estoque', ws: XLSX.utils.aoa_to_sheet([['Insumo','Categoria','Tipo','Unid','Estoque','Valor unit','Valor total'],...(d.insumos||[]).map(i=>[i.nome,i.categoria||'',i.tipo_insumo||'',i.unidade,+(+i.estoque).toFixed(3),+(+i.valorunitario).toFixed(2),+(+i.valor_total).toFixed(2)])]) }];
        case 'tempo':
            return [{ nome:'Por Etapa', ws: XLSX.utils.aoa_to_sheet([['Etapa','Passagens','Média (dias)'],...(d.porEtapa||[]).map(e=>[STATUS_LABEL[e.status]||e.status,+e.qtd,+(+e.media_dias).toFixed(1)])]) }];
        case 'instrumentos':
            return [
                { nome:'Por Tipo', ws: XLSX.utils.aoa_to_sheet([['Tipo','Pedidos'],...(d.porTipo||[]).map(t=>[t.tipo,+t.pedidos])]) },
                { nome:'Por Marca', ws: XLSX.utils.aoa_to_sheet([['#','Tipo','Marca','Pedidos','Receita'],...(d.instrumentos||[]).map((i,idx)=>[idx+1,i.tipo,i.marca||'',+i.pedidos,+(+i.receita||0).toFixed(2)])]) },
            ];
        default: return [];
    }
}
</script>
<script src="assets/js/admin.js?v=<?php echo $v; ?>"></script>
</body>
</html>
