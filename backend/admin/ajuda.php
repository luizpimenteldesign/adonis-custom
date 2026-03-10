<?php
/**
 * AJUDA — DOCUMENTAÇÃO DO SISTEMA ADONIS ADMIN
 */
require_once 'auth.php';
$current_page = 'ajuda.php';
require_once '_sidebar_data.php';
$v = time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adonis Admin — Ajuda</title>
    <meta name="theme-color" content="#1976d2">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/pages.css?v=<?php echo $v; ?>">
    <style>
        /* ── Ajuda page styles ── */
        .ajuda-wrap {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 0 40px;
        }

        .ajuda-hero {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            border-radius: var(--g-radius-lg);
            padding: 28px 28px 24px;
            margin-bottom: 24px;
            color: #fff;
        }

        .ajuda-hero h1 {
            font-family: 'Google Sans', sans-serif;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .ajuda-hero p {
            font-size: 14px;
            opacity: 0.88;
            line-height: 1.6;
            margin: 0;
        }

        /* Índice */
        .ajuda-indice {
            background: var(--g-surface);
            border: 1px solid var(--g-border);
            border-radius: var(--g-radius-lg);
            padding: 20px 24px;
            margin-bottom: 28px;
        }

        .ajuda-indice-titulo {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--g-text-3);
            margin-bottom: 12px;
        }

        .ajuda-indice ol {
            padding-left: 20px;
            margin: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 20px;
        }

        @media (max-width: 540px) {
            .ajuda-indice ol { grid-template-columns: 1fr; }
        }

        .ajuda-indice li { font-size: 13px; color: var(--g-blue); }
        .ajuda-indice a  { color: var(--g-blue); }
        .ajuda-indice a:hover { text-decoration: underline; }

        /* Seções */
        .ajuda-secao {
            background: var(--g-surface);
            border: 1px solid var(--g-border);
            border-radius: var(--g-radius-lg);
            overflow: hidden;
            margin-bottom: 16px;
        }

        .ajuda-secao-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px 24px;
            cursor: pointer;
            user-select: none;
            transition: background 0.15s;
            border-bottom: 1px solid transparent;
        }

        .ajuda-secao-header:hover { background: var(--g-hover); }

        .ajuda-secao-header.aberto {
            border-bottom-color: var(--g-border);
            background: var(--g-bg);
        }

        .ajuda-secao-icone {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--g-blue-light);
            color: var(--g-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .ajuda-secao-titulo {
            font-family: 'Google Sans', sans-serif;
            font-size: 15px;
            font-weight: 500;
            color: var(--g-text);
            flex: 1;
        }

        .ajuda-secao-sub {
            font-size: 12px;
            color: var(--g-text-3);
            margin-top: 1px;
        }

        .ajuda-chevron {
            color: var(--g-text-3);
            transition: transform 0.2s;
        }

        .ajuda-secao-header.aberto .ajuda-chevron {
            transform: rotate(180deg);
        }

        .ajuda-secao-corpo {
            display: none;
            padding: 22px 24px 26px;
        }

        .ajuda-secao-corpo.aberto { display: block; }

        /* Tipografia interna */
        .ajuda-secao-corpo h3 {
            font-family: 'Google Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: var(--g-text);
            margin: 20px 0 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--g-border);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ajuda-secao-corpo h3:first-child { margin-top: 0; }

        .ajuda-secao-corpo h3 .material-symbols-outlined {
            font-size: 18px;
            color: var(--g-blue);
        }

        .ajuda-secao-corpo p {
            font-size: 13px;
            color: var(--g-text-2);
            line-height: 1.65;
            margin: 0 0 10px;
        }

        .ajuda-secao-corpo ul,
        .ajuda-secao-corpo ol {
            font-size: 13px;
            color: var(--g-text-2);
            line-height: 1.65;
            padding-left: 20px;
            margin: 0 0 10px;
        }

        .ajuda-secao-corpo li { margin-bottom: 4px; }

        .ajuda-secao-corpo strong { color: var(--g-text); }

        /* Blocos de status */
        .status-flow {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 10px 0 16px;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid transparent;
        }

        .status-item .material-symbols-outlined { font-size: 13px; }

        .s-new      { background: #e8f0fe; color: #1a73e8; border-color: #c5d8fb; }
        .s-info     { background: #e3f2fd; color: #1565c0; border-color: #b3d9f9; }
        .s-warn     { background: #fef3e2; color: #e37400; border-color: #fcd8a0; }
        .s-success  { background: #e6f4ea; color: #1e8e3e; border-color: #b9e1c3; }
        .s-purple   { background: #f3e8fd; color: #7b1fa2; border-color: #dbb9f7; }
        .s-dark     { background: #f1f3f4; color: #5f6368; border-color: #dadce0; }
        .s-danger   { background: #fce8e6; color: #c5221f; border-color: #f7b9b7; }

        .seta { font-size: 16px; color: var(--g-text-3); align-self: center; }

        /* Tabela de campos */
        .campo-tabela {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin: 10px 0 16px;
            border: 1px solid var(--g-border);
            border-radius: 8px;
            overflow: hidden;
        }

        .campo-tabela thead th {
            background: var(--g-bg);
            color: var(--g-text-2);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 9px 14px;
            text-align: left;
            border-bottom: 1px solid var(--g-border);
        }

        .campo-tabela tbody td {
            padding: 9px 14px;
            color: var(--g-text-2);
            border-bottom: 1px solid var(--g-border);
            vertical-align: top;
        }

        .campo-tabela tbody tr:last-child td { border-bottom: none; }
        .campo-tabela tbody tr:hover td { background: var(--g-hover); }

        .campo-nome { font-weight: 600; color: var(--g-text); }
        .campo-obrig { color: var(--g-red); font-weight: 700; font-size: 10px; }
        .campo-opc   { color: var(--g-text-3); font-size: 10px; }

        /* Alerta / dica */
        .ajuda-dica {
            display: flex;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.55;
            margin: 12px 0;
        }

        .ajuda-dica .material-symbols-outlined { font-size: 18px; flex-shrink: 0; margin-top: 1px; }

        .dica-info    { background: #e8f0fe; color: #1565c0; }
        .dica-warn    { background: #fef3e2; color: #b06000; }
        .dica-success { background: #e6f4ea; color: #1e6e34; }
        .dica-danger  { background: #fce8e6; color: #a50e0e; }

        /* Numeração de passos */
        .passo-list { list-style: none; padding: 0; margin: 10px 0 16px; }
        .passo-list li {
            display: flex;
            gap: 12px;
            margin-bottom: 10px;
            font-size: 13px;
            color: var(--g-text-2);
            line-height: 1.5;
        }
        .passo-num {
            width: 24px; height: 24px;
            border-radius: 50%;
            background: var(--g-blue);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="app-layout">
<?php require_once '_sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <button class="btn-menu" onclick="toggleSidebar()">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <span class="topbar-title">Ajuda &amp; Documentação</span>
        <a href="logout.php" class="material-symbols-outlined sidebar-logout" title="Sair">logout</a>
    </div>

    <div class="page-content" style="max-width:860px">
    <div class="ajuda-wrap">

        <!-- Hero -->
        <div class="ajuda-hero">
            <h1><span class="material-symbols-outlined" style="vertical-align:middle;font-size:26px;margin-right:8px">menu_book</span>Documentação do Sistema Adonis</h1>
            <p>Guia completo de uso do painel administrativo: como cadastrar dados, gerenciar pedidos, configurar serviços e insumos, e entender cada etapa do fluxo de atendimento.</p>
        </div>

        <!-- Índice -->
        <div class="ajuda-indice">
            <div class="ajuda-indice-titulo">Índice</div>
            <ol>
                <li><a href="#sec-visao">Visão Geral do Sistema</a></li>
                <li><a href="#sec-dashboard">Dashboard (Painel Principal)</a></li>
                <li><a href="#sec-fluxo">Fluxo Completo de um Pedido</a></li>
                <li><a href="#sec-clientes">Clientes</a></li>
                <li><a href="#sec-instrumentos">Instrumentos</a></li>
                <li><a href="#sec-servicos">Serviços</a></li>
                <li><a href="#sec-insumos">Insumos (Materiais)</a></li>
                <li><a href="#sec-analise">Análise de Insumos</a></li>
                <li><a href="#sec-orcamento">Orçamento e Simulador</a></li>
                <li><a href="#sec-whatsapp">WhatsApp Automático</a></li>
                <li><a href="#sec-configuracoes">Configurações</a></li>
                <li><a href="#sec-usuarios">Usuários do Sistema</a></li>
            </ol>
        </div>

        <!-- 1. Visão Geral -->
        <div class="ajuda-secao" id="sec-visao">
            <div class="ajuda-secao-header aberto" onclick="toggleSec(this)">
                <div class="ajuda-secao-icone"><span class="material-symbols-outlined">info</span></div>
                <div>
                    <div class="ajuda-secao-titulo">1. Visão Geral do Sistema</div>
                    <div class="ajuda-secao-sub">O que é e como o Adonis funciona</div>
                </div>
                <span class="material-symbols-outlined ajuda-chevron">expand_more</span>
            </div>
            <div class="ajuda-secao-corpo aberto">
                <p>O <strong>Adonis Admin</strong> é o painel de gerenciamento de uma luthieria. Ele centraliza todo o processo de atendimento: desde a chegada de um pedido pelo formulário público até a entrega final do instrumento ao cliente.</p>

                <h3><span class="material-symbols-outlined">hub</span>Módulos do sistema</h3>
                <ul>
                    <li><strong>Dashboard</strong> — lista todos os pedidos com filtros por status e busca rápida</li>
                    <li><strong>Clientes</strong> — cadastro de nome, telefone e e-mail</li>
                    <li><strong>Instrumentos</strong> — cadastro vinculado a um cliente (tipo, marca, modelo, cor)</li>
                    <li><strong>Serviços</strong> — catálogo de serviços com valor base e insumos fixos associados</li>
                    <li><strong>Insumos</strong> — estoque de materiais com preço e quantidade</li>
                    <li><strong>Configurações</strong> — dados do luthier, feriados e integrações</li>
                    <li><strong>Usuários</strong> — controle de quem acessa o painel</li>
                </ul>

                <div class="ajuda-dica dica-info">
                    <span class="material-symbols-outlined">lightbulb</span>
                    <span>O fluxo começa com uma <strong>Pré-OS</strong> (pré-ordem de serviço). Um cliente preenche o formulário público ou o luthier cadastra manualmente. A partir daí, o pedido percorre etapas até ser entregue.</span>
                </div>
            </div>
        </div>

        <!-- 2. Dashboard -->
        <div class="ajuda-secao" id="sec-dashboard">
            <div class="ajuda-secao-header" onclick="toggleSec(this)">
                <div class="ajuda-secao-icone"><span class="material-symbols-outlined">dashboard</span></div>
                <div>
                    <div class="ajuda-secao-titulo">2. Dashboard — Painel Principal</div>
                    <div class="ajuda-secao-sub">Como usar a lista de pedidos</div>
                </div>
                <span class="material-symbols-outlined ajuda-chevron">expand_more</span>
            </div>
            <div class="ajuda-secao-corpo">
                <p>O Dashboard é a tela inicial do painel. Mostra todos os pedidos em ordem de atualização mais recente.</p>

                <h3><span class="material-symbols-outlined">bar_chart</span>Cards de estatísticas (topo)</h3>
                <ul>
                    <li><strong>Total</strong> — todos os pedidos cadastrados</li>
                    <li><strong>Pendentes</strong> — pedidos em status Pré-OS ou Em Análise (ainda não orçados)</li>
                    <li><strong>Orçadas</strong> — pedidos com orçamento gerado, aguardando aprovação do cliente</li>
                    <li><strong>Em Execução</strong> — pedidos aprovados que estão em andamento</li>
                </ul>
                <p>Clicar em qualquer card filtra a lista automaticamente.</p>

                <h3><span class="material-symbols-outlined">filter_list</span>Filtros por status (chips)</h3>
                <p>Use os chips coloridos para filtrar por etapa do pedido. É possível combinar filtro de status com busca por texto.</p>

                <h3><span class="material-symbols-outlined">search</span>Busca</h3>
                <p>Pesquise por <strong>nome do cliente</strong>, <strong>telefone</strong>, <strong>tipo/marca/modelo</strong> do instrumento ou pelo <strong>ID numérico</strong> do pedido. A busca acontece automaticamente enquanto você digita.</p>

                <h3><span class="material-symbols-outlined">touch_app</span>Painel lateral / sheet mobile</h3>
                <p>Ao clicar em qualquer pedido da lista, um painel lateral abre (desktop) ou uma bottom sheet sobe (celular) com as ações disponíveis para aquele status. Todas as transições de status são feitas por aqui, sem precisar abrir a página de detalhes.</p>

                <div class="ajuda-dica dica-info">
                    <span class="material-symbols-outlined">info</span>
                    <span>O link <strong>"Ver detalhes completos →"</strong> no painel leva para a página <code>detalhes.php</code> com histórico de status, dados do cliente, instrumento e serviços do pedido.</span>
                </div>
            </div>
        </div>

        <!-- 3. Fluxo de Pedido -->
        <div class="ajuda-secao" id="sec-fluxo">
            <div class="ajuda-secao-header" onclick="toggleSec(this)">
                <div class="ajuda-secao-icone"><span class="material-symbols-outlined">account_tree</span></div>
                <div>
                    <div class="ajuda-secao-titulo">3. Fluxo Completo de um Pedido</div>
                    <div class="ajuda-secao-sub">Do Pré-OS à entrega — todos os status</div>
                </div>
                <span class="material-symbols-outlined ajuda-chevron">expand_more</span>
            </div>
            <div class="ajuda-secao-corpo">
                <p>Cada pedido passa por uma sequência de status. Veja o fluxo completo abaixo:</p>

                <div class="status-flow">
                    <div class="status-item s-new"><span class="material-symbols-outlined">note_add</span> Pré-OS</div>
                    <span class="seta material-symbols-outlined">arrow_forward</span>
                    <div class="status-item s-info"><span class="material-symbols-outlined">search</span> Em Análise</div>
                    <span class="seta material-symbols-outlined">arrow_forward</span>
                    <div class="status-item s-warn"><span class="material-symbols-outlined">request_quote</span> Orçada</div>
                    <span class="seta material-symbols-outlined">arrow_forward</span>
                    <div class="status-item s-warn"><span class="material-symbols-outlined">hourglass_empty</span> Aguard. Aprovação</div>
                    <span class="seta material-symbols-outlined">arrow_forward</span>
                    <div class="status-item s-success"><span class="material-symbols-outlined">credit_card</span> Aguard. Pagamento</div>
                    <span class="seta material-symbols-outlined">arrow_forward</span>
                    <div class="status-item s-success"><span class="material-symbols-outlined">check_circle</span> Pagamento Recebido</div>
                    <span class="seta material-symbols-outlined">arrow_forward</span>
                    <div class="status-item s-success"><span class="material-symbols-outlined">inventory_2</span> Instrumento Recebido</div>
                    <span class="seta material-symbols-outlined">arrow_forward</span>
                    <div class="status-item s-purple"><span class="material-symbols-outlined">build</span> Serviço Iniciado</div>
                    <span class="seta material-symbols-outlined">arrow_forward</span>
                    <div class="status-item s-purple"><span class="material-symbols-outlined">settings</span> Em Desenvolvimento</div>
                    <span class="seta material-symbols-outlined">arrow_forward</span>
                    <div class="status-item s-success"><span class="material-symbols-outlined">done_all</span> Serviço Finalizado</div>
                    <span class="seta material-symbols-outlined">arrow_forward</span>
                    <div class="status-item s-warn"><span class="material-symbols-outlined">store</span> Pronto p/ Retirada</div>
                    <span class="seta material-symbols-outlined">arrow_forward</span>
                    <div class="status-item s-dark"><span class="material-symbols-outlined">verified</span> Entregue</div>
                </div>

                <h3><span class="material-symbols-outlined">description</span>Descrição de cada etapa</h3>
                <table class="campo-tabela">
                    <thead><tr><th>Status</th><th>O que significa / O que fazer</th></tr></thead>
                    <tbody>
                        <tr><td class="campo-nome">Pré-OS</td><td>Pedido recém-chegado pelo formulário público ou cadastrado manualmente. Ainda não foi analisado.</td></tr>
                        <tr><td class="campo-nome">Em Análise</td><td>O luthier abriu a análise de insumos: está definindo quais materiais serão usados. Neste ponto, insumos fixos já são carregados automaticamente e os variáveis podem ser adicionados.</td></tr>
                        <tr><td class="campo-nome">Orçada</td><td>O valor foi definido e o orçamento enviado ao cliente via WhatsApp. O cliente ainda não aprovou.</td></tr>
                        <tr><td class="campo-nome">Aguard. Aprovação</td><td>O cliente foi notificado e está avaliando o orçamento. Aguardar resposta.</td></tr>
                        <tr><td class="campo-nome">Aguard. Pagamento</td><td>Cliente aprovou. Aguardando o pagamento (total ou entrada).</td></tr>
                        <tr><td class="campo-nome">Pagamento Recebido</td><td>Pagamento confirmado. Aguardar o cliente trazer o instrumento.</td></tr>
                        <tr><td class="campo-nome">Instrumento Recebido</td><td>O instrumento deu entrada na luthieria. Pronto para iniciar o serviço.</td></tr>
                        <tr><td class="campo-nome">Serviço Iniciado</td><td>O luthier começou a trabalhar no instrumento.</td></tr>
                        <tr><td class="campo-nome">Em Desenvolvimento</td><td>Trabalho em andamento.</td></tr>
                        <tr><td class="campo-nome">Serviço Finalizado</td><td>Trabalho concluído. Definir se vai aguardar retirada ou pagamento na retirada.</td></tr>
                        <tr><td class="campo-nome">Pronto p/ Retirada</td><td>Instrumento pronto, cliente pode vir buscar.</td></tr>
                        <tr><td class="campo-nome">Entregue</td><td>Instrumento devolvido ao cliente. Pedido encerrado.</td></tr>
                        <tr><td class="campo-nome s-danger">Reprovada</td><td>O luthier reprovou o pedido (fora de escopo, peça indisponível etc.). Pode ser reaberto.</td></tr>
                        <tr><td class="campo-nome s-dark">Cancelada</td><td>Pedido cancelado pelo cliente ou luthier. Pode ser reaberto como Pré-OS.</td></tr>
                    </tbody>
                </table>

                <div class="ajuda-dica dica-warn">
                    <span class="material-symbols-outlined">warning</span>
                    <span>A etapa <strong>Em Análise → Orçada</strong> é a única que exige interação com o modal de insumos. Todas as outras transições de status são feitas com um clique no botão correspondente no painel lateral.</span>
                </div>
            </div>
        </div>

        <!-- 4. Clientes -->
        <div class="ajuda-secao" id="sec-clientes">
            <div class="ajuda-secao-header" onclick="toggleSec(this)">
                <div class="ajuda-secao-icone"><span class="material-symbols-outlined">person</span></div>
                <div>
                    <div class="ajuda-secao-titulo">4. Clientes</div>
                    <div class="ajuda-secao-sub">Cadastro e vínculos</div>
                </div>
                <span class="material-symbols-outlined ajuda-chevron">expand_more</span>
            </div>
            <div class="ajuda-secao-corpo">
                <p>Clientes são o ponto de entrada do sistema. Todo pedido precisa ter um cliente associado.</p>

                <h3><span class="material-symbols-outlined">edit_note</span>Campos do cadastro</h3>
                <table class="campo-tabela">
                    <thead><tr><th>Campo</th><th>Obrig.?</th><th>Observação</th></tr></thead>
                    <tbody>
                        <tr><td class="campo-nome">Nome completo</td><td><span class="campo-obrig">SIM</span></td><td>Usado nas iniciais do avatar e identificação nos pedidos</td></tr>
                        <tr><td class="campo-nome">Telefone / WhatsApp</td><td><span class="campo-obrig">SIM</span></td><td>Formato recomendado: <code>5527999990000</code> (sem + ou espaços). Essencial para o envio automático de mensagens pelo WhatsApp</td></tr>
                        <tr><td class="campo-nome">E-mail</td><td><span class="campo-opc">OPCIONAL</span></td><td>Para contato alternativo. Não é usado em automações ainda</td></tr>
                    </tbody>
                </table>

                <div class="ajuda-dica dica-warn">
                    <span class="material-symbols-outlined">warning</span>
                    <span>O campo <strong>telefone</strong> deve conter apenas números, no formato internacional: <strong>55 + DDD + número</strong>. Exemplo: <code>5527999990000</code>. Qualquer formatação diferente (parênteses, traços, espaços) pode impedir o envio do WhatsApp.</span>
                </div>

                <h3><span class="material-symbols-outlined">link</span>Vínculos</h3>
                <p>Cada cliente pode ter <strong>múltiplos instrumentos</strong> cadastrados e <strong>múltiplos pedidos</strong> associados. Um cliente não pode ser excluído se houver instrumentos ou pedidos vinculados a ele.</p>
            </div>
        </div>

        <!-- 5. Instrumentos -->
        <div class="ajuda-secao" id="sec-instrumentos">
            <div class="ajuda-secao-header" onclick="toggleSec(this)">
                <div class="ajuda-secao-icone"><span class="material-symbols-outlined">music_note</span></div>
                <div>
                    <div class="ajuda-secao-titulo">5. Instrumentos</div>
                    <div class="ajuda-secao-sub">Cadastro de instrumentos dos clientes</div>
                </div>
                <span class="material-symbols-outlined ajuda-chevron">expand_more</span>
            </div>
            <div class="ajuda-secao-corpo">
                <p>Instrumentos são vinculados a um cliente específico. Um pedido sempre referencia um instrumento.</p>

                <h3><span class="material-symbols-outlined">edit_note</span>Campos do cadastro</h3>
                <table class="campo-tabela">
                    <thead><tr><th>Campo</th><th>Obrig.?</th><th>Observação</th></tr></thead>
                    <tbody>
                        <tr><td class="campo-nome">Cliente</td><td><span class="campo-obrig">SIM</span></td><td>Selecionar o cliente dono do instrumento</td></tr>
                        <tr><td class="campo-nome">Tipo</td><td><span class="campo-obrig">SIM</span></td><td>Ex: Guitarra, Violão, Baixo, Cavaco. Aparece na busca do dashboard</td></tr>
                        <tr><td class="campo-nome">Marca</td><td><span class="campo-obrig">SIM</span></td><td>Ex: Fender, Gibson, Tagima. Aparece na busca e na identificação do pedido</td></tr>
                        <tr><td class="campo-nome">Modelo</td><td><span class="campo-obrig">SIM</span></td><td>Ex: Stratocaster, Les Paul, TG-530</td></tr>
                        <tr><td class="campo-nome">Cor / Acabamento</td><td><span class="campo-opc">OPCIONAL</span></td><td>Ex: Sunburst, Natural, Preta. Ajuda na identificação física</td></tr>
                        <tr><td class="campo-nome">Número de série</td><td><span class="campo-opc">OPCIONAL</span></td><td>Para controle patrimonial</td></tr>
                        <tr><td class="campo-nome">Observações</td><td><span class="campo-opc">OPCIONAL</span></td><td>Detalhes adicionais sobre o instrumento</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 6. Serviços -->
        <div class="ajuda-secao" id="sec-servicos">
            <div class="ajuda-secao-header" onclick="toggleSec(this)">
                <div class="ajuda-secao-icone"><span class="material-symbols-outlined">handyman</span></div>
                <div>
                    <div class="ajuda-secao-titulo">6. Serviços</div>
                    <div class="ajuda-secao-sub">Catálogo de serviços e insumos fixos</div>
                </div>
                <span class="material-symbols-outlined ajuda-chevron">expand_more</span>
            </div>
            <div class="ajuda-secao-corpo">
                <p>O catálogo de serviços define o que a luthieria oferece. Cada pedido tem um ou mais serviços associados, e o valor base do orçamento é a soma dos valores de todos os serviços do pedido.</p>

                <h3><span class="material-symbols-outlined">edit_note</span>Campos do cadastro</h3>
                <table class="campo-tabela">
                    <thead><tr><th>Campo</th><th>Obrig.?</th><th>Observação</th></tr></thead>
                    <tbody>
                        <tr><td class="campo-nome">Nome do serviço</td><td><span class="campo-obrig">SIM</span></td><td>Ex: "Setup Completo Guitarra", "Troca de Captadores". Nome claro e específico</td></tr>
                        <tr><td class="campo-nome">Descrição</td><td><span class="campo-opc">OPCIONAL</span></td><td>Detalhamento do que inclui o serviço</td></tr>
                        <tr><td class="campo-nome">Valor base (R$)</td><td><span class="campo-obrig">SIM</span></td><td>Valor da mão de obra, sem insumos. É a base para o cálculo do orçamento</td></tr>
                        <tr><td class="campo-nome">Categoria</td><td><span class="campo-opc">OPCIONAL</span></td><td>Ex: Setup, Elétrica, Estrutural. Ajuda a organizar o catálogo</td></tr>
                        <tr><td class="campo-nome">Insumos fixos</td><td><span class="campo-opc">OPCIONAL</span></td><td>Materiais que sempre são usados nesse serviço. São carregados automaticamente na análise de insumos</td></tr>
                    </tbody>
                </table>

                <div class="ajuda-dica dica-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    <span>Associar <strong>insumos fixos</strong> a um serviço economiza tempo: sempre que este serviço for adicionado a um pedido, seus insumos fixos aparecem automaticamente pré-selecionados na análise, sem precisar buscá-los manualmente.</span>
                </div>
            </div>
        </div>

        <!-- 7. Insumos -->
        <div class="ajuda-secao" id="sec-insumos">
            <div class="ajuda-secao-header" onclick="toggleSec(this)">
                <div class="ajuda-secao-icone"><span class="material-symbols-outlined">inventory</span></div>
                <div>
                    <div class="ajuda-secao-titulo">7. Insumos (Materiais)</div>
                    <div class="ajuda-secao-sub">Estoque de materiais e preços</div>
                </div>
                <span class="material-symbols-outlined ajuda-chevron">expand_more</span>
            </div>
            <div class="ajuda-secao-corpo">
                <p>Insumos são os materiais consumíveis usados nos serviços: cordas, trastes, parafusos, cola, solda, pestanas etc. O sistema controla o estoque e o custo de cada item.</p>

                <h3><span class="material-symbols-outlined">edit_note</span>Campos do cadastro</h3>
                <table class="campo-tabela">
                    <thead><tr><th>Campo</th><th>Obrig.?</th><th>Observação</th></tr></thead>
                    <tbody>
                        <tr><td class="campo-nome">Nome</td><td><span class="campo-obrig">SIM</span></td><td>Nome claro do material. Ex: "Corda Elixir .009", "Traste 2.0mm", "Cola Instantânea Super Bonder"</td></tr>
                        <tr><td class="campo-nome">Categoria</td><td><span class="campo-obrig">SIM</span></td><td>Agrupa os insumos no modal de análise. Ex: Cordas, Trastes, Hardware, Consumíveis, Pestanas</td></tr>
                        <tr><td class="campo-nome">Unidade</td><td><span class="campo-obrig">SIM</span></td><td>Ex: un, jogo, metro, ml, g. Aparece nos cards da análise de insumos</td></tr>
                        <tr><td class="campo-nome">Tipo</td><td><span class="campo-obrig">SIM</span></td><td><strong>Fixo</strong>: sempre usado, não varia por pedido. <strong>Variável</strong>: depende do serviço e é escolhido na análise</td></tr>
                        <tr><td class="campo-nome">Valor unitário (R$)</td><td><span class="campo-obrig">SIM</span></td><td>Custo do insumo por unidade. Usado para calcular o total de insumos do pedido</td></tr>
                        <tr><td class="campo-nome">Estoque atual</td><td><span class="campo-obrig">SIM</span></td><td>Quantidade disponível. Fica vermelho quando zero, laranja quando abaixo de 5 unidades</td></tr>
                        <tr><td class="campo-nome">Estoque mínimo</td><td><span class="campo-opc">OPCIONAL</span></td><td>Ponto de reposição. Usado para alertas futuros</td></tr>
                        <tr><td class="campo-nome">Fornecedor</td><td><span class="campo-opc">OPCIONAL</span></td><td>Nome do fornecedor para referência</td></tr>
                    </tbody>
                </table>

                <h3><span class="material-symbols-outlined">category</span>Categorias recomendadas</h3>
                <p>Mantenha as categorias padronizadas para que o modal de análise fique organizado. Sugestões:</p>
                <ul>
                    <li><strong>Cordas</strong> — jogos de corda por instrumento/bitola</li>
                    <li><strong>Trastes</strong> — nickel, aço inox, por tamanho</li>
                    <li><strong>Hardware</strong> — tarraxas, pontalete, strap pin, porca de tração</li>
                    <li><strong>Elétrica</strong> — captadores, potenciômetros, capacitores, fio</li>
                    <li><strong>Pestanas</strong> — por material e espessura</li>
                    <li><strong>Consumíveis</strong> — cola, solda, lixa, polish, óleo de escala</li>
                </ul>

                <div class="ajuda-dica dica-info">
                    <span class="material-symbols-outlined">info</span>
                    <span>Quando o <strong>estoque é zero</strong>, o sistema sugere marcar o insumo como "Cliente fornece" automaticamente ao adicioná-lo à análise. Isso evita cobrar ao cliente algo que você não tem em estoque.</span>
                </div>
            </div>
        </div>

        <!-- 8. Análise de Insumos -->
        <div class="ajuda-secao" id="sec-analise">
            <div class="ajuda-secao-header" onclick="toggleSec(this)">
                <div class="ajuda-secao-icone" style="background:#e3f2fd;color:#1565c0"><span class="material-symbols-outlined">search</span></div>
                <div>
                    <div class="ajuda-secao-titulo">8. Análise de Insumos</div>
                    <div class="ajuda-secao-sub">O coração do processo de orçamento</div>
                </div>
                <span class="material-symbols-outlined ajuda-chevron">expand_more</span>
            </div>
            <div class="ajuda-secao-corpo">
                <p>A análise de insumos é o passo que transforma um pedido Pré-OS em um orçamento. É aqui que você define exatamente quais materiais serão usados e em que quantidade.</p>

                <h3><span class="material-symbols-outlined">play_circle</span>Como iniciar</h3>
                <ol class="passo-list">
                    <li><span class="passo-num">1</span><span>No Dashboard, clique no pedido com status <strong>Pré-OS</strong></span></li>
                    <li><span class="passo-num">2</span><span>No painel lateral, clique em <strong>"Iniciar Análise"</strong></span></li>
                    <li><span class="passo-num">3</span><span>O modal de análise abre. Os serviços do pedido são exibidos no topo</span></li>
                    <li><span class="passo-num">4</span><span>Os insumos fixos vinculados aos serviços já aparecem pré-selecionados</span></li>
                    <li><span class="passo-num">5</span><span>Selecione uma categoria e adicione os insumos variáveis necessários</span></li>
                    <li><span class="passo-num">6</span><span>Ajuste quantidades e marque "Cliente fornece" quando necessário</span></li>
                    <li><span class="passo-num">7</span><span>Clique em <strong>"Confirmar e Orçar →"</strong> para gerar o orçamento</span></li>
                </ol>

                <h3><span class="material-symbols-outlined">list_alt</span>Entendendo os cards de insumos selecionados</h3>
                <p>Cada card na lista inferior representa um insumo adicionado ao pedido. Os controles são:</p>
                <ul>
                    <li><strong>Campo numérico (quantidade)</strong> — ajuste a quantidade usada. Aceita decimais (ex: 0.5 para meia corda)</li>
                    <li><strong>"Cliente fornece"</strong> — marque quando o cliente traz o material. O valor desse insumo é zerado no total (aparece riscado)</li>
                    <li><strong>Valor</strong> — calculado automaticamente: quantidade × valor unitário</li>
                    <li><strong>Botão ×</strong> — remove o insumo da lista</li>
                </ul>

                <div class="ajuda-dica dica-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    <span>O <strong>Total insumos</strong> no rodapé do modal soma apenas os insumos onde "Cliente fornece" está desmarcado. Esse valor é passado automaticamente para o simulador de orçamento.</span>
                </div>

                <div class="ajuda-dica dica-warn">
                    <span class="material-symbols-outlined">warning</span>
                    <span>Um pedido em status <strong>"Reprovada"</strong> também pode ter sua análise revisada: use o botão "Reabrir — Rever Insumos" para corrigir e gerar um novo orçamento.</span>
                </div>
            </div>
        </div>

        <!-- 9. Orçamento -->
        <div class="ajuda-secao" id="sec-orcamento">
            <div class="ajuda-secao-header" onclick="toggleSec(this)">
                <div class="ajuda-secao-icone" style="background:#fef3e2;color:#e37400"><span class="material-symbols-outlined">request_quote</span></div>
                <div>
                    <div class="ajuda-secao-titulo">9. Orçamento e Simulador</div>
                    <div class="ajuda-secao-sub">Como definir e enviar o valor ao cliente</div>
                </div>
                <span class="material-symbols-outlined ajuda-chevron">expand_more</span>
            </div>
            <div class="ajuda-secao-corpo">
                <p>Após confirmar a análise de insumos, o modal de orçamento abre automaticamente com o valor sugerido preenchido (soma de serviços + insumos).</p>

                <h3><span class="material-symbols-outlined">calculate</span>Campos do orçamento</h3>
                <table class="campo-tabela">
                    <thead><tr><th>Campo</th><th>O que preencher</th></tr></thead>
                    <tbody>
                        <tr><td class="campo-nome">Valor total (R$)</td><td>Valor base que você quer cobrar. Pode ser editado livremente — o valor sugerido é apenas uma referência</td></tr>
                        <tr><td class="campo-nome">Prazo (dias úteis)</td><td>Estimativa de prazo para conclusão. Não conta finais de semana nem feriados cadastrados em Configurações</td></tr>
                    </tbody>
                </table>

                <h3><span class="material-symbols-outlined">credit_card</span>Simulador de máquina de cartão</h3>
                <p>O simulador calcula automaticamente dois valores para você escolher qual enviar ao cliente:</p>
                <ul>
                    <li><strong>Valor Base</strong> — o valor que você digitou, sem taxa de cartão. Use para pagamentos em dinheiro/PIX</li>
                    <li><strong>Valor Máquina (10x)</strong> — valor ajustado para cobrir a taxa da máquina no pior caso (Elo/Amex 10x). O sistema indica exatamente qual valor digitar na maquininha para que você receba o valor base líquido</li>
                </ul>

                <div class="ajuda-dica dica-info">
                    <span class="material-symbols-outlined">info</span>
                    <span>A taxa usada é <strong>21,58%</strong> para valores até R$ 2.000 e <strong>15,38%</strong> para valores acima. Esses percentuais representam o pior caso para a bandeira com maior taxa em 10 parcelas, garantindo que você nunca receba menos do que o orçado.</span>
                </div>

                <h3><span class="material-symbols-outlined">send</span>Envio do orçamento</h3>
                <p>Ao clicar em <strong>"Enviar Orçamento"</strong>, o status muda para <strong>Orçada</strong> e uma mensagem de WhatsApp é preparada automaticamente com o valor, prazo e link de acompanhamento.</p>
            </div>
        </div>

        <!-- 10. WhatsApp -->
        <div class="ajuda-secao" id="sec-whatsapp">
            <div class="ajuda-secao-header" onclick="toggleSec(this)">
                <div class="ajuda-secao-icone" style="background:#e6f4ea;color:#1e8e3e"><span class="material-symbols-outlined">chat</span></div>
                <div>
                    <div class="ajuda-secao-titulo">10. WhatsApp Automático</div>
                    <div class="ajuda-secao-sub">Notificações ao cliente nas mudanças de status</div>
                </div>
                <span class="material-symbols-outlined ajuda-chevron">expand_more</span>
            </div>
            <div class="ajuda-secao-corpo">
                <p>A cada mudança de status, o sistema verifica se há uma mensagem de WhatsApp configurada para aquela transição. Se sim, um modal aparece com um botão de atalho para o WhatsApp Web/App.</p>

                <h3><span class="material-symbols-outlined">how_it_works</span>Como funciona</h3>
                <ul>
                    <li>O link gerado usa o formato <code>wa.me/[telefone]?text=[mensagem]</code></li>
                    <li>A mensagem inclui nome do cliente, instrumento, status e (quando orçada) o valor e prazo</li>
                    <li>Você pode clicar em <strong>"WhatsApp"</strong> para abrir a conversa ou em <strong>"Pular"</strong> para só recarregar a lista</li>
                    <li>O recarregamento só acontece depois que você fecha o modal — isso evita perder a mensagem antes de enviá-la</li>
                </ul>

                <div class="ajuda-dica dica-warn">
                    <span class="material-symbols-outlined">warning</span>
                    <span>O WhatsApp automático só funciona se o <strong>telefone do cliente</strong> estiver cadastrado no formato correto: <strong>55 + DDD + número</strong>, sem espaços ou símbolos. Exemplo: <code>5527999990000</code>.</span>
                </div>
            </div>
        </div>

        <!-- 11. Configurações -->
        <div class="ajuda-secao" id="sec-configuracoes">
            <div class="ajuda-secao-header" onclick="toggleSec(this)">
                <div class="ajuda-secao-icone"><span class="material-symbols-outlined">settings</span></div>
                <div>
                    <div class="ajuda-secao-titulo">11. Configurações</div>
                    <div class="ajuda-secao-sub">Dados da luthieria, feriados e integrações</div>
                </div>
                <span class="material-symbols-outlined ajuda-chevron">expand_more</span>
            </div>
            <div class="ajuda-secao-corpo">
                <p>A página de configurações centraliza os dados que afetam o funcionamento geral do sistema.</p>

                <h3><span class="material-symbols-outlined">store</span>Dados da luthieria</h3>
                <ul>
                    <li><strong>Nome do negócio</strong> — aparece nas mensagens de WhatsApp e na página pública do cliente</li>
                    <li><strong>Telefone de contato</strong> — exibido na página do cliente</li>
                    <li><strong>Endereço</strong> — exibido na página do cliente</li>
                </ul>

                <h3><span class="material-symbols-outlined">event_busy</span>Feriados</h3>
                <p>Cadastre os feriados locais e nacionais. Eles são descontados automaticamente do cálculo de prazo em dias úteis no orçamento. Formato: <strong>DD/MM/YYYY</strong>.</p>

                <h3><span class="material-symbols-outlined">link</span>Link público de acompanhamento</h3>
                <p>O sistema gera um link único por pedido (token) que permite ao cliente acompanhar o status sem precisar de login. Esse link é enviado automaticamente via WhatsApp na primeira notificação.</p>
            </div>
        </div>

        <!-- 12. Usuários -->
        <div class="ajuda-secao" id="sec-usuarios">
            <div class="ajuda-secao-header" onclick="toggleSec(this)">
                <div class="ajuda-secao-icone"><span class="material-symbols-outlined">manage_accounts</span></div>
                <div>
                    <div class="ajuda-secao-titulo">12. Usuários do Sistema</div>
                    <div class="ajuda-secao-sub">Controle de acesso ao painel</div>
                </div>
                <span class="material-symbols-outlined ajuda-chevron">expand_more</span>
            </div>
            <div class="ajuda-secao-corpo">
                <p>O módulo de usuários controla quem pode acessar o painel administrativo.</p>

                <h3><span class="material-symbols-outlined">edit_note</span>Campos do cadastro</h3>
                <table class="campo-tabela">
                    <thead><tr><th>Campo</th><th>Observação</th></tr></thead>
                    <tbody>
                        <tr><td class="campo-nome">Nome</td><td>Nome de exibição do usuário no painel</td></tr>
                        <tr><td class="campo-nome">E-mail</td><td>Usado como login de acesso</td></tr>
                        <tr><td class="campo-nome">Senha</td><td>Mínimo 6 caracteres. Armazenada com hash seguro</td></tr>
                        <tr><td class="campo-nome">Nível de acesso</td><td><strong>Admin</strong>: acesso total. <strong>Operador</strong>: acesso às funções do dia a dia sem poder excluir dados ou acessar usuários</td></tr>
                    </tbody>
                </table>

                <div class="ajuda-dica dica-danger">
                    <span class="material-symbols-outlined">security</span>
                    <span>Nunca compartilhe sua senha. Ao suspeitar de acesso indevido, altere a senha imediatamente em <strong>Perfil → Alterar Senha</strong>.</span>
                </div>
            </div>
        </div>

        <!-- Rodapé -->
        <div style="text-align:center;padding:20px 0 8px;font-size:12px;color:var(--g-text-3)">
            Adonis Admin &mdash; Sistema de Gerenciamento de Luthieria &mdash; Documentação v1.0
        </div>

    </div>
    </div>
</main>
</div>

<!-- Bottom nav mobile -->
<nav class="bottom-nav">
    <a href="dashboard.php">
        <span class="material-symbols-outlined nav-icon">dashboard</span>Painel
    </a>
    <a href="ajuda.php" class="active">
        <span class="material-symbols-outlined nav-icon">help</span>Ajuda
    </a>
    <a href="logout.php">
        <span class="material-symbols-outlined nav-icon">logout</span>Sair
    </a>
</nav>

<script>
function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}
function closeSidebar(){
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
}

function toggleSec(header) {
    const corpo = header.nextElementSibling;
    const aberto = header.classList.contains('aberto');
    header.classList.toggle('aberto', !aberto);
    corpo.classList.toggle('aberto', !aberto);
}

// Abrir seção via âncora (#sec-xxx)
document.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash;
    if (hash) {
        const secao = document.querySelector(hash);
        if (secao) {
            const header = secao.querySelector('.ajuda-secao-header');
            const corpo  = secao.querySelector('.ajuda-secao-corpo');
            if (header && corpo) {
                header.classList.add('aberto');
                corpo.classList.add('aberto');
                setTimeout(() => secao.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
            }
        }
    }

    // Restaura estado dos grupos da sidebar
    const estado = JSON.parse(localStorage.getItem('nav_grupos') || '{}');
    for (const [id, aberto] of Object.entries(estado)) {
        const toggle = document.getElementById('toggle-' + id);
        const sub    = document.getElementById('sub-'    + id);
        if (!toggle || !sub) continue;
        if (aberto) { toggle.classList.add('open'); sub.classList.add('open'); }
    }
});

function toggleGroup(id) {
    const toggle = document.getElementById('toggle-' + id);
    const sub    = document.getElementById('sub-'    + id);
    toggle.classList.toggle('open');
    sub.classList.toggle('open');
    const estado = JSON.parse(localStorage.getItem('nav_grupos') || '{}');
    estado[id] = toggle.classList.contains('open');
    localStorage.setItem('nav_grupos', JSON.stringify(estado));
}
</script>
</body>
</html>
