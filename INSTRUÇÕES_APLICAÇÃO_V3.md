# Instruções para Aplicar V3 no detalhes.php

## 🎯 Objetivo

Substituir o sistema antigo de análise de insumos (que sugeria 71 itens automaticamente) pela nova interface de categorias (seleção manual).

---

## 🛠️ Arquivos Já Criados

✅ `backend/admin/assets/js/analise_insumos_v3.js` - JavaScript novo
✅ `backend/admin/assets/css/analise_insumos_v3.css` - CSS novo
✅ `backend/admin/analise_insumos.php` - API atualizada

---

## 📝 Alterações no `detalhes.php`

### **Passo 1: Adicionar novos CSS e JS no `<head>`**

Localize a seção `<head>` do arquivo e **adicione estas duas linhas** logo após os outros links de CSS:

```html
<link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
<link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
<link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo $v; ?>">
<!-- ADICIONE AQUI ↓ -->
<link rel="stylesheet" href="assets/css/analise_insumos_v3.css?v=<?php echo $v; ?>">
```

---

### **Passo 2: Adicionar JavaScript antes do `</body>`**

No final do arquivo, logo **ANTES** da tag `</body>`, adicione:

```html
<script src="assets/js/admin.js?v=<?php echo $v; ?>"></script>
<!-- ADICIONE AQUI ↓ -->
<script src="assets/js/analise_insumos_v3.js?v=<?php echo $v; ?>"></script>
</body>
```

---

### **Passo 3: Remover JavaScript antigo do modal de análise**

Localize e **DELETE TODO O BLOCO** de JavaScript que começa com:

```javascript
// ── MODAL ANÁLISE ──────────────────────────────────
 let _insumos = [];

function abrirModalAnalise() {
    ...
}
```

Até:

```javascript
function confirmarAnalise() {
    ...
}
```

**Delete tudo entre `let _insumos = [];` e o final de `confirmarAnalise()`** (inclusive essas funções).

**NÃO delete** as funções `_abrirOrcamentoComValor`, `abrirModalOrcamento`, `simularValores`, etc. - só a parte específica do modal de análise.

---

### **Passo 4: Remover CSS antigo (OPCIONAL)**

Se quiser limpar completamente, localize e delete o CSS do modal de análise antigo que está no `<style>` interno do detalhes.php:

Procure por classes como:
- `.analise-insumo-row`
- `.analise-insumo-top`
- `.analise-insumo-bottom`
- `.analise-cf-toggle`

Delete essas classes se existirem no `<style>` interno. Mas isso é **opcional** - não quebra nada se deixar.

---

## ✅ Resumo das Mudanças

| Arquivo | Ação |
|---------|-------|
| `detalhes.php` | Adicionar link CSS v3 |
| `detalhes.php` | Adicionar script JS v3 |
| `detalhes.php` | Remover JS antigo do modal análise |
| `analise_insumos.php` | ✅ Já atualizado |
| CSS v3 | ✅ Já criado |
| JS v3 | ✅ Já criado |

---

## 🧪 Teste Final

1. Salve `detalhes.php` com as alterações
2. Faça push para o repositório
3. Aguarde deploy (10-15 segundos)
4. Abra qualquer pedido no admin
5. Clique em **"Iniciar Análise"**
6. Deve aparecer:
   - Resumo dos serviços
   - Grid de botões de categorias
   - Ao clicar em uma categoria: lista de 10-15 insumos
   - Campo de busca
   - Botão "+" para adicionar
   - Lista de selecionados

---

## ⚠️ Se der erro

Se aparecer erro de JavaScript:

1. Verifique se o link CSS foi adicionado corretamente
2. Verifique se o script JS foi adicionado antes de `</body>`
3. Limpe o cache do navegador (Ctrl+Shift+R)
4. Abra o Console do navegador (F12) e veja o erro específico

---

## 🚀 Depois de Funcionar

**Você pode deletar:**
- `backend/admin/criar_tabela_servicos_insumos.php` (se já executou)
- `INSUMOS_README.md` (opcional - era sobre o sistema de vínculos)
- `ATUALIZAÇÃO_INSUMOS_V3.md` (este arquivo de docs)
- `INSTRUÇÕES_APLICAÇÃO_V3.md` (este arquivo)

---

## 👍 Nova Experiência

**Antes:** 71 insumos na tela → scroll infinito → desmarcar tudo manualmente

**Agora:**
1. Escolhe categoria (Cordas / Trastes / Eletrônica...)
2. Busca o insumo específico
3. Clica "+" para adicionar
4. Repete para outras categorias se necessário
5. Confirma

**Muito mais rápido e limpo! 🎉**
