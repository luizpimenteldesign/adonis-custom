<?php
/**
 * HELPER DE WHATSAPP — Adonis Custom
 * Versão: 2.0 — phone, token e endereço lidos da tabela configuracoes
 *
 * CallMeBot  → notificações automáticas para o Adonis
 * wa.me      → links para o cliente abrir conversa com o Adonis
 *              links para o Adonis enviar mensagem ao cliente
 */

require_once __DIR__ . '/../config/Database.php';

/**
 * Lê uma chave da tabela configuracoes.
 * Retorna $padrao se não encontrar.
 */
function _wa_cfg(string $chave, string $padrao = ''): string {
    static $cache = [];
    if (isset($cache[$chave])) return $cache[$chave];
    try {
        $db   = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare('SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1');
        $stmt->execute([$chave]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        $cache[$chave] = $row ? (string)$row['valor'] : $padrao;
    } catch (Throwable $e) {
        error_log('[wa_cfg] ' . $e->getMessage());
        $cache[$chave] = $padrao;
    }
    return $cache[$chave];
}

define('WA_ACOMPANHA_URL', 'https://adns.luizpimentel.com/adonis-custom/frontend/public/acompanhar.php');

/**
 * Envia mensagem automática para o Adonis via CallMeBot.
 */
function wa_notificar_adonis(string $mensagem): bool
{
    $phone  = _wa_cfg('whatsapp_admin');
    $apikey = _wa_cfg('callmebot_token');

    if (empty($phone) || empty($apikey)) {
        error_log('[WhatsApp] whatsapp_admin ou callmebot_token não configurados.');
        return false;
    }

    $url = 'https://api.callmebot.com/whatsapp.php?'
         . 'phone='   . urlencode($phone)
         . '&text='   . urlencode($mensagem)
         . '&apikey=' . urlencode($apikey);

    $ctx  = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 8, 'ignore_errors' => true]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) { error_log('[WhatsApp] Falha ao chamar CallMeBot.'); return false; }
    return true;
}

/**
 * Gera link wa.me para o CLIENTE abrir conversa com o Adonis.
 */
function wa_link_cliente(string $mensagem = ''): string
{
    $phone = _wa_cfg('whatsapp_admin');
    $base  = 'https://wa.me/' . preg_replace('/\D/', '', $phone);
    if (!empty($mensagem)) $base .= '?text=' . rawurlencode($mensagem);
    return $base;
}

/**
 * Gera link wa.me para o ADONIS enviar mensagem ao cliente.
 * $telefone_cliente deve ter DDI, sem + nem espaços (ex: 5527999998888)
 */
function wa_link_para_cliente(string $telefone_cliente, string $mensagem = ''): string
{
    $base = 'https://wa.me/' . preg_replace('/\D/', '', $telefone_cliente);
    if (!empty($mensagem)) $base .= '?text=' . rawurlencode($mensagem);
    return $base;
}

// ─────────────────────────────────────────────────────────────────
// MENSAGENS ADONIS → CLIENTE  (por status)
// ─────────────────────────────────────────────────────────────────

function wa_msg_status_para_cliente(array $pedido, string $status, array $extras = []): string
{
    $nome   = $pedido['cliente_nome']    ?? 'Cliente';
    $id     = $pedido['id']              ?? '?';
    $instr  = trim(($pedido['instrumento_tipo']   ?? '') . ' '
                 . ($pedido['instrumento_marca']  ?? '') . ' '
                 . ($pedido['instrumento_modelo'] ?? ''));
    $link   = WA_ACOMPANHA_URL . '?token=' . ($pedido['public_token'] ?? '');
    $end    = _wa_cfg('endereco_loja', 'Endereço não configurado');

    $saudacao = "Olá, *{$nome}*! 👋\n";
    $rodape   = "\n\n🔗 Acompanhe seu pedido:\n{$link}";

    switch ($status) {
        case 'Em analise':
            return $saudacao
                 . "Seu instrumento (*{$instr}* — Pedido #{$id}) chegou até nós e já está em análise pelo técnico. Em breve seu orçamento estará pronto! 🔍"
                 . $rodape;

        case 'Orcada':
            $valor = isset($extras['valor'])
                ? 'R$ ' . number_format((float)$extras['valor'], 2, ',', '.')
                : 'disponível no link';
            $prazo = isset($extras['prazo']) ? (int)$extras['prazo'] . ' dias úteis' : '';
            return $saudacao
                 . "O orçamento do seu *{$instr}* (Pedido #{$id}) está pronto! 💰\n"
                 . "Valor: *{$valor}*" . ($prazo ? " | Prazo estimado: *{$prazo}*" : "") . "\n\n"
                 . "Acesse o link abaixo para escolher a forma de pagamento e aprovar (ou não) o orçamento:"
                 . $rodape;

        case 'Pagamento recebido':
            return $saudacao
                 . "✅ Confirmamos o recebimento do seu pagamento! (Pedido #{$id} — *{$instr}*)\n"
                 . "Agora, por favor, traga ou envie seu instrumento para que possamos iniciar o serviço."
                 . $rodape;

        case 'Instrumento recebido':
            return $saudacao
                 . "📦 Recebemos seu instrumento (*{$instr}* — Pedido #{$id})! Em breve o serviço será iniciado."
                 . $rodape;

        case 'Servico iniciado':
            return $saudacao
                 . "🔧 O serviço no seu *{$instr}* (Pedido #{$id}) foi *iniciado*! Estamos cuidando com atenção."
                 . $rodape;

        case 'Em desenvolvimento':
            return $saudacao
                 . "⚙️ Seu *{$instr}* (Pedido #{$id}) está *em desenvolvimento*. Nosso técnico está trabalhando nele agora!"
                 . $rodape;

        case 'Servico finalizado':
            return $saudacao
                 . "🎸 O serviço no seu *{$instr}* (Pedido #{$id}) foi *finalizado*! Em breve ele estará disponível para retirada."
                 . $rodape;

        case 'Pronto para retirada':
            return $saudacao
                 . "🎉 Seu *{$instr}* (Pedido #{$id}) está *pronto para retirada*!\n"
                 . "📍 Endereço: {$end}\n"
                 . "Qualquer dúvida, é só falar!"
                 . $rodape;

        case 'Aguardando pagamento retirada':
            $perc = _wa_cfg('perc_retirada', '40');
            return $saudacao
                 . "💵 Seu *{$instr}* (Pedido #{$id}) está pronto! Para retirar, realize o pagamento do saldo restante ({$perc}%).\n"
                 . "📍 Endereço: {$end}"
                 . $rodape;

        case 'Entregue':
            return $saudacao
                 . "🏁 Seu *{$instr}* (Pedido #{$id}) foi *entregue*! Obrigado pela confiança! 🙏\n"
                 . "Se precisar de qualquer coisa, estamos à disposição.";

        case 'Cancelada':
            return $saudacao
                 . "⚠️ Infelizmente o Pedido #{$id} (*{$instr}*) foi *cancelado*. Entre em contato para mais informações."
                 . $rodape;

        default:
            return $saudacao
                 . "Seu pedido #{$id} (*{$instr}*) foi atualizado para o status: *{$status}*."
                 . $rodape;
    }
}

// ─────────────────────────────────────────────────────────────────
// MENSAGENS CLIENTE → ADONIS (notificações automáticas)
// ─────────────────────────────────────────────────────────────────

function wa_msg_aprovacao(array $pedido, array $pgto): string
{
    $nome  = $pedido['cliente_nome']    ?? 'Cliente';
    $id    = $pedido['id']              ?? '?';
    $instr = trim(($pedido['instrumento_tipo']   ?? '') . ' '
                . ($pedido['instrumento_marca']  ?? '') . ' '
                . ($pedido['instrumento_modelo'] ?? ''));
    $valor = isset($pgto['valor_final'])
               ? 'R$ ' . number_format((float)$pgto['valor_final'], 2, ',', '.')
               : 'não informado';
    $forma = $pgto['descricao'] ?? $pgto['forma'] ?? 'não informada';
    $link  = WA_ACOMPANHA_URL . '?token=' . ($pedido['public_token'] ?? '');

    return "✅ *Orçamento APROVADO*\n"
         . "Pedido: #{$id}\n"
         . "Cliente: {$nome}\n"
         . "Instrumento: {$instr}\n"
         . "Pagamento: {$forma}\n"
         . "Valor: {$valor}\n"
         . "Acompanhar: {$link}";
}

function wa_msg_reprovacao(array $pedido, string $motivo): string
{
    $nome  = $pedido['cliente_nome']    ?? 'Cliente';
    $id    = $pedido['id']              ?? '?';
    $instr = trim(($pedido['instrumento_tipo']   ?? '') . ' '
                . ($pedido['instrumento_marca']  ?? '') . ' '
                . ($pedido['instrumento_modelo'] ?? ''));

    return "❌ *Orçamento REPROVADO*\n"
         . "Pedido: #{$id}\n"
         . "Cliente: {$nome}\n"
         . "Instrumento: {$instr}\n"
         . "Motivo: {$motivo}";
}
