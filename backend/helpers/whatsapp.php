<?php
/**
 * HELPER DE WHATSAPP — Adonis Custom
 * Versão: 3.0 — sem emojis (evita losangos com ?), formatação pura WA
 */

require_once __DIR__ . '/../config/Database.php';

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

function wa_notificar_adonis(string $mensagem): bool
{
    $phone  = _wa_cfg('whatsapp_admin');
    $apikey = _wa_cfg('callmebot_token');
    if (empty($phone) || empty($apikey)) {
        error_log('[WhatsApp] whatsapp_admin ou callmebot_token nao configurados.');
        return false;
    }
    $url = 'https://api.callmebot.com/whatsapp.php?'
         . 'phone='   . urlencode($phone)
         . '&text='   . urlencode($mensagem)
         . '&apikey=' . urlencode($apikey);
    $ctx  = stream_context_create(['http' => ['method'=>'GET','timeout'=>8,'ignore_errors'=>true]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) { error_log('[WhatsApp] Falha ao chamar CallMeBot.'); return false; }
    return true;
}

function wa_link_cliente(string $mensagem = ''): string
{
    $phone = _wa_cfg('whatsapp_admin');
    $base  = 'https://wa.me/' . preg_replace('/\D/', '', $phone);
    if (!empty($mensagem)) $base .= '?text=' . rawurlencode($mensagem);
    return $base;
}

function wa_link_para_cliente(string $telefone_cliente, string $mensagem = ''): string
{
    $base = 'https://wa.me/' . preg_replace('/\D/', '', $telefone_cliente);
    if (!empty($mensagem)) $base .= '?text=' . rawurlencode($mensagem);
    return $base;
}

// ─────────────────────────────────────────────────────────────────
// MENSAGENS ADONIS → CLIENTE (sem emojis)
// ─────────────────────────────────────────────────────────────────

function wa_msg_status_para_cliente(array $pedido, string $status, array $extras = []): string
{
    $nome  = $pedido['cliente_nome']    ?? 'Cliente';
    $id    = $pedido['id']              ?? '?';
    $instr = trim(($pedido['instrumento_tipo']   ?? '') . ' '
                . ($pedido['instrumento_marca']  ?? '') . ' '
                . ($pedido['instrumento_modelo'] ?? ''));
    $link  = WA_ACOMPANHA_URL . '?token=' . ($pedido['public_token'] ?? '');
    $end   = _wa_cfg('endereco_loja', 'Endereco nao configurado');

    $cab    = "Ola, *{$nome}*!\n";
    $rodape = "\n\nAcompanhe seu pedido:\n{$link}";

    switch ($status) {

        case 'Em analise':
            return $cab
                 . "Seu instrumento (*{$instr}* - Pedido #{$id}) chegou ate nos e ja esta em analise pelo tecnico. Em breve o orcamento estara pronto!";

        case 'Orcada':
            $valor = isset($extras['valor'])
                ? 'R$ ' . number_format((float)$extras['valor'], 2, ',', '.')
                : 'disponivel no link';
            $prazo = isset($extras['prazo']) ? (int)$extras['prazo'] . ' dias uteis' : '';
            return $cab
                 . "O orcamento do seu *{$instr}* (Pedido #{$id}) esta pronto!\n"
                 . "Valor: *{$valor}*" . ($prazo ? " | Prazo estimado: *{$prazo}*" : '') . "\n\n"
                 . "Acesse o link abaixo para escolher a forma de pagamento e aprovar (ou nao) o orcamento:"
                 . $rodape;

        case 'Pagamento recebido':
            return $cab
                 . "Confirmamos o recebimento do seu pagamento! (Pedido #{$id} - *{$instr}*)\n"
                 . "Agora, por favor, traga ou envie seu instrumento para que possamos iniciar o servico."
                 . $rodape;

        case 'Instrumento recebido':
            return $cab
                 . "Recebemos seu instrumento (*{$instr}* - Pedido #{$id})! Em breve o servico sera iniciado."
                 . $rodape;

        case 'Servico iniciado':
            return $cab
                 . "O servico no seu *{$instr}* (Pedido #{$id}) foi *iniciado*! Estamos cuidando com atencao."
                 . $rodape;

        case 'Em desenvolvimento':
            return $cab
                 . "Seu *{$instr}* (Pedido #{$id}) esta *em desenvolvimento*. Nosso tecnico esta trabalhando nele agora!"
                 . $rodape;

        case 'Servico finalizado':
            return $cab
                 . "O servico no seu *{$instr}* (Pedido #{$id}) foi *finalizado*! Em breve estara disponivel para retirada."
                 . $rodape;

        case 'Pronto para retirada':
            return $cab
                 . "Seu *{$instr}* (Pedido #{$id}) esta *pronto para retirada*!\n"
                 . "Endereco: {$end}\n"
                 . "Qualquer duvida, e so falar!"
                 . $rodape;

        case 'Aguardando pagamento retirada':
            $perc = _wa_cfg('perc_retirada', '40');
            return $cab
                 . "Seu *{$instr}* (Pedido #{$id}) esta pronto! Para retirar, realize o pagamento do saldo restante ({$perc}%).\n"
                 . "Endereco: {$end}"
                 . $rodape;

        case 'Entregue':
            return $cab
                 . "Seu *{$instr}* (Pedido #{$id}) foi *entregue*! Obrigado pela confianca!\n"
                 . "Se precisar de qualquer coisa, estamos a disposicao.";

        case 'Cancelada':
            return $cab
                 . "Infelizmente o Pedido #{$id} (*{$instr}*) foi *cancelado*. Entre em contato para mais informacoes."
                 . $rodape;

        default:
            return $cab
                 . "Seu pedido #{$id} (*{$instr}*) foi atualizado para o status: *{$status}*."
                 . $rodape;
    }
}

// ─────────────────────────────────────────────────────────────────
// MENSAGENS CLIENTE → ADONIS (notificacoes automaticas)
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
               : 'nao informado';
    $forma = $pgto['descricao'] ?? $pgto['forma'] ?? 'nao informada';
    $link  = WA_ACOMPANHA_URL . '?token=' . ($pedido['public_token'] ?? '');

    return "*Orcamento APROVADO*\n"
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

    return "*Orcamento REPROVADO*\n"
         . "Pedido: #{$id}\n"
         . "Cliente: {$nome}\n"
         . "Instrumento: {$instr}\n"
         . "Motivo: {$motivo}";
}
