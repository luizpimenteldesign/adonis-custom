# Categorias de Insumos — Feature V2.0

## 🎯 Objetivo

Organizar insumos **por categoria** no modal de análise, facilitando:
- Visualização clara dos materiais necessários
- Seleção rápida por tipo
- Expansão/colapso de grupos
- Experiência similar ao Google Keep/Material Design

---

## 📦 Estrutura do Banco

### Nova Tabela: `categorias_insumo`

```sql
CREATE TABLE categorias_insumo (
  id int(11) PRIMARY KEY AUTO_INCREMENT,
  nome varchar(100) UNIQUE NOT NULL,
  icone varchar(50) DEFAULT 'inventory_2',
  ativo tinyint(1) DEFAULT 1,
  criado_em timestamp DEFAULT CURRENT_TIMESTAMP
);
```

### Categorias Padrão

| Categoria      | Ícone Material Symbol    | Uso                            |
|----------------|-------------------------|--------------------------------|
| **Trastes**    | `radio_button_unchecked`| Trastes de níquel, inox       |
| **Cordas**     | `cable`                 | Encordoamentos, cordas avulsas |
| **Eletrônica**| `electrical_services`   | Solda, potenciômetros, fios    |
| **Marcenaria** | `carpenter`             | Colas, madeiras, massa         |
| **Acabamento** | `format_paint`          | Verniz, lixas, tintas          |
| **Ferramentas**| `handyman`              | Brocas, parafusos, chaves      |
| **Hardware**   | `construction`          | Tarraxas, pontes, knobs        |
| **Outros**     | `category`              | Insumos sem categoria específica |

### Modificação na Tabela `insumos`

```sql
ALTER TABLE insumos 
  ADD COLUMN categoria varchar(100) DEFAULT NULL AFTER modelo,
  ADD KEY idx_categoria (categoria);
```

---

## 🖥️ Como Funciona

### No Dashboard — Modal de Análise

1. **Chips de Categoria**
   - Aparecem no topo do modal
   - Mostram nome + ícone + contador de insumos
   - Click ativa/desativa a categoria
   - Visual azul quando ativa

2. **Grupos Expansíveis**
   - Cada categoria tem cabeçalho com ícone + nome
   - Chevron `expand_more` para colapsar/expandir
   - Insumos listados dentro do grupo

3. **Interações**
   - Quantidade editável
   - Checkbox "Cliente fornece"
   - Cálculo automático do total
   - Marca "Sem estoque" quando necessário

### Fluxo de Uso

```
Pré-OS → Iniciar Análise → Modal Insumos
  |
  └─ Chips: [Trastes (2)] [Eletrônica (1)] [Cordas (1)]
  └─ Grupos:
       🔘 Trastes
         - Traste Inox .043" (metro) x 2m
         - Lixa 320 (unidade) x 1
       ⚡ Eletrônica
         - Solda 60/40 (metro) x 0.5m
       🧵 Cordas
         - Encordoamento .010 (jogo) x 1
  └─ Total: R$ 85,50

✓ Confirmar e Orçar
```

---

## ➕ Adicionar Nova Categoria

### Pelo SQL

```sql
INSERT INTO categorias_insumo (nome, icone, ativo) 
VALUES ('Capas', 'cases', 1);
```

### Associar Insumo à Categoria

```sql
UPDATE insumos 
SET categoria = 'Eletrônica' 
WHERE nome LIKE '%solda%' OR nome LIKE '%capacitor%';
```

### Criar Insumo Já Categorizado

```sql
INSERT INTO insumos (nome, marca, unidade, valor_unitario, categoria) 
VALUES ('Traste Inox .047', 'Jescar', 'metro', 12.50, 'Trastes');
```

---

## 🎨 Estilo Visual

### Chips (Topo)

```css
.analise-chip-cat {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 16px;
  background: var(--g-bg-hover);
  border: 1px solid var(--g-border);
  cursor: pointer;
  transition: 120ms;
}

.analise-chip-cat.ativo {
  background: var(--g-blue);
  color: white;
}
```

### Grupos

```css
.analise-categoria-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
}

.analise-categoria-header.fechado .analise-categoria-chevron {
  transform: rotate(-90deg);
}

.analise-categoria-header.fechado + .analise-categoria-content {
  display: none;
}
```

---

## 📡 API Backend

### GET `/backend/admin/analise_insumos.php?pre_os_id=X`

**Resposta:**

```json
{
  "sucesso": true,
  "pedido": { ... },
  "servicos": [{ "id": 7, "nome": "Ajuste de Nut" }],
  "categorias": ["Trastes", "Eletrônica", "Cordas"],
  "categorias_icones": {
    "Trastes": "radio_button_unchecked",
    "Eletrônica": "electrical_services",
    "Cordas": "cable"
  },
  "insumos_por_categoria": {
    "Trastes": [
      {
        "insumo_id": 4,
        "nome": "Traste Inox .043",
        "unidade": "metro",
        "valor_unitario": 8.50,
        "quantidade_estoque": 10.5,
        "quantidade": 2,
        "cliente_fornece": 0,
        "servicos_origem": "Troca de Trastes, Retifica de Trastes",
        "categoria": "Trastes"
      }
    ],
    "Eletrônica": [ ... ]
  }
}
```

### POST `/backend/admin/analise_insumos.php`

**Body:**

```json
{
  "pre_os_id": 31,
  "insumos": [
    {
      "insumo_id": 4,
      "quantidade": 2,
      "valor_unitario": 8.50,
      "cliente_fornece": 0
    }
  ]
}
```

**Resposta:**

```json
{
  "sucesso": true,
  "total_servicos": 100.00,
  "total_insumos": 17.00,
  "total_orcamento": 117.00
}
```

---

## ✅ Benefícios

1. **Organização clara** → insumos agrupados por finalidade
2. **Performance** → colapsar categorias não utilizadas
3. **UX moderna** → chips interativos + expand/collapse
4. **Escalabilidade** → fácil adicionar novas categorias
5. **Flexível** → insumos sem categoria vão para "Outros"

---

## 🛠️ Migração

Execute a migration SQL:

```bash
mysql -u user -p adonis_db < backend/migrations/003_categorias_insumos.sql
```

Ou copie/cole no phpMyAdmin.

---

## 👀 Demo Visual

### Antes (V1)

```
☐ Traste Inox .043 | Qtd: [2] | R$ 17,00 ☐ Cliente fornece
☐ Solda 60/40      | Qtd: [1] | R$  4,50 ☐ Cliente fornece
☐ Encordoamento .010| Qtd: [1] | R$ 25,00 ☐ Cliente fornece
```

### Depois (V2)

```
[Chips]
🔘 Trastes (1)   ⚡ Eletrônica (1)   🧵 Cordas (1)

🔘 Trastes (1) ▼
  ☐ Traste Inox .043 | Qtd: [2] | R$ 17,00 ☐ Cliente fornece
  
⚡ Eletrônica (1) ▼
  ☐ Solda 60/40      | Qtd: [1] | R$  4,50 ☐ Cliente fornece
  
🧵 Cordas (1) ▼
  ☐ Encordoamento .010| Qtd: [1] | R$ 25,00 ☐ Cliente fornece
```

---

## 📝 Notas

- Insumos sem categoria são atribuídos automaticamente a **"Outros"**
- Ícones usam **Material Symbols Outlined**
- Cor padrão dos chips: azul `#1976d2` (var(--g-blue))
- Estado de expansão pode ser persistido em `localStorage` (futuro)

---

🎉 **Feature pronta para uso!**

Para dúvidas: `contato@luizpimentel.com`
