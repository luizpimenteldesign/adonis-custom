<?php
/**
 * HELPER DE WHATSAPP — Adonis Custom
 * Versão: 1.1
 *
 * CallMeBot  → notificações automáticas para o Adonis
 * wa.me      → links para o cliente abrir conversa com o Adonis
 *              links para o Adonis enviar mensagem ao cliente
 *
 * ⚠️  CONFIGURE ANTES DE USAR:
 *   WA_ADONIS_PHONE  → número do Adonis com DDI, sem + nem espaços
 *   WA_ADONIS_APIKEY → chave gerada pelo Adonis no CallMeBot
 *                      (Adonis deve salvar +34 644 95 42 75 nos contatos
 *                       e enviar: "I allow callmebot to send me messages")
 */

define('WA_ADONIS_PHONE',  '5527988137891');   // número do Adonis
define('WA_ADONIS_APIKEY', 'APIKEY_PENDENTE'); // ← substituir pela chave real
define('WA_ACOMPANHA_URL', 'https://adns.luizpimentel.com/adonis-custom/frontend/public/acompanhar.php');

/**
 * Envia mensagem automática para o Adonis via CallMeBot.
 */
function wa_notificar_adonis(string $mensagem): bool
{
    if (WA_ADONIS_APIKEY === 'APIKEY_PENDENTE') {
        error_log('[WhatsApp] APIKEY não configurada — mensagem não enviada.');
        return false;
    }
    $url = 'https://api.callmebot.com/whatsapp.php?'
         . 'phone='  . urlencode(WA_ADONIS_PHONE)
         . '&text='  . urlencode($mensagem)
         . '&apikey='. urlencode(WA_ADONIS_APIKEY);
    $ctx = stream_context_create(['http' => ['method'=>'GET','timeout'=>8,'ignore_errors'=>true]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) { error_log('[WhatsApp] Falha ao chamar CallMeBot.'); return false; }
    return true;
}

/**
 * Gera link wa.me para o CLIENTE abrir conversa com o Adonis.
 */
function wa_link_cliente(string $mensagem = ''): string
{
    $base = 'https://wa.me/' . WA_ADONIS_PHONE;
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

/**
 * Retorna o texto da mensagem do Adonis para o cliente
 * de acordo com o novo status do pedido.
 */
function wa_msg_status_para_cliente(array $pedido, string $status, array $extras = []): string
{
    $nome   = $pedido['cliente_nome']   ?? 'Cliente';
    $id     = $pedido['id']             ?? '?';
    $instr  = trim(($pedido['instrumento_tipo']  ?? '') . ' '
                 . ($pedido['instrumento_marca'] ?? '') . ' '
                 . ($pedido['instrumento_modelo']?? ''));
    $link   = WA_ACOMPANHA_URL . '?token=' . ($pedido['public_token'] ?? '');

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
                 . "📍 Endereço: Rua do Presépio, s/n – Chácara do Conde, Vila Velha – ES\n"
                 . "Qualquer dúvida, é só falar!"
                 . $rodape;

        case 'Aguardando pagamento retirada':
            return $saudacao
                 . "💵 Seu *{$instr}* (Pedido #{$id}) está pronto! Para retirar, realize o pagamento do saldo restante (50%).\n"
                 . "📍 Endereço: Rua do Presépio, s/n – Chácara do Conde, Vila Velha – ES"
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
    $nome  = $pedido['cliente_nome']   ?? 'Cliente';
    $id    = $pedido['id']             ?? '?';
    $instr = trim(($pedido['instrumento_tipo']  ?? '') . ' '
                . ($pedido['instrumento_marca'] ?? '') . ' '
                . ($pedido['instrumento_modelo']?? ''));
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
    $nome  = $pedido['cliente_nome']   ?? 'Cliente';
    $id    = $pedido['id']             ?? '?';
    $instr = trim(($pedido['instrumento_tipo']  ?? '') . ' '
                . ($pedido['instrumento_marca'] ?? '') . ' '
                . ($pedido['instrumento_modelo']?? ''));

    return "❌ *Orçamento REPROVADO*\n"
         . "Pedido: #{$id}\n"
         . "Cliente: {$nome}\n"
         . "Instrumento: {$instr}\n"
         . "Motivo: {$motivo}";
}
