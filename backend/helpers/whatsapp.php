<?php
/**
 * HELPER DE WHATSAPP — Adonis Custom
 * Versão: 1.0
 *
 * CallMeBot  → notificações automáticas para o Adonis
 * wa.me      → links para o cliente abrir conversa com o Adonis
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
 * Retorna true em sucesso, false em falha (não lança exceção).
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

    $ctx = stream_context_create(['http' => [
        'method'  => 'GET',
        'timeout' => 8,
        'ignore_errors' => true,
    ]]);

    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) {
        error_log('[WhatsApp] Falha ao chamar CallMeBot.');
        return false;
    }
    return true;
}

/**
 * Gera link wa.me para o cliente abrir conversa com o Adonis.
 * $mensagem é opcional — se informada, já abre com texto pré-preenchido.
 */
function wa_link_cliente(string $mensagem = ''): string
{
    $base = 'https://wa.me/' . WA_ADONIS_PHONE;
    if (!empty($mensagem)) {
        $base .= '?text=' . rawurlencode($mensagem);
    }
    return $base;
}

/**
 * Gera mensagem padrão de aprovação para o Adonis.
 */
function wa_msg_aprovacao(array $pedido, array $pgto): string
{
    $emoji  = '✅';
    $nome   = $pedido['cliente_nome']   ?? 'Cliente';
    $id     = $pedido['id']             ?? '?';
    $instr  = trim(($pedido['instrumento_tipo']  ?? '') . ' '
                 . ($pedido['instrumento_marca'] ?? '') . ' '
                 . ($pedido['instrumento_modelo']?? ''));
    $valor  = isset($pgto['valor_final'])
                ? 'R$ ' . number_format((float)$pgto['valor_final'], 2, ',', '.')
                : 'não informado';
    $forma  = $pgto['descricao'] ?? $pgto['forma'] ?? 'não informada';
    $link   = WA_ACOMPANHA_URL . '?token=' . ($pedido['public_token'] ?? '');

    return "$emoji *Or\u00e7amento APROVADO*\n"
         . "Pedido: #$id\n"
         . "Cliente: $nome\n"
         . "Instrumento: $instr\n"
         . "Pagamento: $forma\n"
         . "Valor: $valor\n"
         . "Acompanhar: $link";
}

/**
 * Gera mensagem padrão de reprovação para o Adonis.
 */
function wa_msg_reprovacao(array $pedido, string $motivo): string
{
    $nome  = $pedido['cliente_nome']   ?? 'Cliente';
    $id    = $pedido['id']             ?? '?';
    $instr = trim(($pedido['instrumento_tipo']  ?? '') . ' '
                . ($pedido['instrumento_marca'] ?? '') . ' '
                . ($pedido['instrumento_modelo']?? ''));

    return "\u274c *Or\u00e7amento REPROVADO*\n"
         . "Pedido: #$id\n"
         . "Cliente: $nome\n"
         . "Instrumento: $instr\n"
         . "Motivo: $motivo";
}
