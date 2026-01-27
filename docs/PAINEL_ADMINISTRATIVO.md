# Painel Administrativo - Sistema Adonis

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Instalação](#instalação)
3. [Acesso ao Sistema](#acesso-ao-sistema)
4. [Funcionalidades](#funcionalidades)
5. [Gestão de Pedidos](#gestão-de-pedidos)
6. [Segurança](#segurança)
7. [Próximos Recursos](#próximos-recursos)

---

## 🔍 Visão Geral

O Painel Administrativo do Sistema Adonis é uma interface web completa para gerenciamento de pedidos de orçamento (pré-OS), permitindo:

- **Dashboard com estatísticas em tempo real**
- **Listagem e filtragem de pedidos**
- **Visualização detalhada de cada solicitação**
- **Gerenciamento de status**
- **Controle de acesso por permissões**
- **Auditoria de ações**

---

## 🛠️ Instalação

### 1. Executar Script SQL

Acesse o MySQL/phpMyAdmin e execute o script:

```bash
mysql -u luizpi39_adns -p luizpi39_adns_app < backend/database/admin_tables.sql
```

Ou via phpMyAdmin:
1. Acesse o banco `luizpi39_adns_app`
2. Vá em **SQL**
3. Cole o conteúdo de `admin_tables.sql`
4. Clique em **Executar**

### 2. Verificar Estrutura

As seguintes tabelas devem ser criadas:

- ✅ `usuarios` - Usuários administrativos
- ✅ `logs_acesso` - Logs de login/logout
- ✅ `preos_servicos` - Relação pré-OS e serviços
- ✅ `fotos` - Upload de imagens
- ✅ `preos_historico` - Histórico de mudanças de status

### 3. Usuário Admin Padrão

**E-mail:** `admin@adonis.com`  
**Senha:** `admin123`

⚠️ **IMPORTANTE:** Altere a senha após primeiro login!

---

## 🔐 Acesso ao Sistema

### URL de Acesso

```
https://adns.luizpimentel.com/adonis-custom/backend/admin/login.php
```

### Tipos de Usuário

| Tipo | Permissões |
|------|-------------|
| **Admin** | Acesso total ao sistema |
| **Supervisor** | Visualização e análise de pedidos |

### Sessão e Segurança

- **Timeout:** 30 minutos de inatividade
- **Proteção:** Verificação de sessão em todas as páginas
- **Logs:** Todas as tentativas de login são registradas

---

## 📊 Funcionalidades

### 1. Dashboard

**URL:** `backend/admin/dashboard.php`

#### Cards de Estatísticas

- 📋 **Total de Pedidos** - Todos os pedidos cadastrados
- ⏳ **Pendentes** - Aguardando análise
- ✅ **Aprovados** - Pedidos aprovados pelo cliente
- ✔️ **Finalizados** - Trabalhos concluídos

#### Tabela de Pedidos

- Listagem dos 50 pedidos mais recentes
- Informações:
  - ID do pedido
  - Dados do cliente (nome, telefone)
  - Instrumento (tipo, marca, modelo)
  - Status atual
  - Data de criação
- Botão para visualizar detalhes

### 2. Detalhes do Pedido

**URL:** `backend/admin/detalhes.php?id={ID}`

#### Seções

**👤 Dados do Cliente**
- Nome completo
- Telefone (com link para WhatsApp)
- E-mail (com link para envio)
- Endereço completo

**🎸 Dados do Instrumento**
- Tipo (Guitarra, Baixo, Violão, etc.)
- Marca
- Modelo
- Referência
- Cor
- Número de série

**🔧 Serviços Solicitados**
- Tabela com todos os serviços
- Nome, descrição, valor base e prazo

**📷 Fotos do Instrumento**
- Galeria de fotos anexadas
- Clique para visualizar em tamanho real

**📝 Observações**
- Detalhes fornecidos pelo cliente

**🔑 Código de Acompanhamento**
- Token público para consulta externa

#### Botões de Ação

- ✅ **Aprovar** - Aprovar orçamento
- ❌ **Reprovar** - Reprovar solicitação
- ✏️ **Editar** - Modificar informações

---

## 📝 Gestão de Pedidos

### Status de Pedidos

| Status | Descrição | Badge |
|--------|-------------|-------|
| `criado` | Pedido recém criado | 🆕 Novo |
| `aguardando_analise` | Aguardando revisão do admin | ⏳ Aguardando |
| `em_analise` | Sendo avaliado | 🔍 Em Análise |
| `aprovado` | Cliente aprovou orçamento | ✅ Aprovado |
| `reprovado` | Cliente recusou | ❌ Reprovado |
| `finalizado` | Trabalho concluído | ✔️ Finalizado |

### Fluxo de Trabalho

```
Cliente Solicita Orçamento
        ↓
   [criado]
        ↓
Admin Analisa
        ↓
 [aguardando_analise]
        ↓
Admin Envia Orçamento
        ↓
   [aprovado/reprovado]
        ↓
    [finalizado]
```

### Histórico de Mudanças

Todas as mudanças de status são registradas automaticamente na tabela `preos_historico` via **trigger MySQL**.

---

## 🔒 Segurança

### Autenticação

- **Senha criptografada** com `password_hash()` (bcrypt)
- **Sessões PHP seguras** com timeout
- **Proteção contra brute force** via logs

### Permissões

```php
verificarPermissao('admin'); // Requer nível admin
```

### Logs de Auditoria

Todos os acessos são registrados:

- IP do usuário
- User-Agent (navegador)
- Tipo de ação (login, logout, falha)
- Timestamp

### Proteção de Rotas

Todas as páginas administrativas incluem:

```php
require_once 'auth.php'; // Verifica sessão
```

### Boas Práticas

✅ Sempre usar `htmlspecialchars()` para exibir dados do usuário  
✅ Prepared Statements em todas as queries  
✅ Validação de IDs numéricos  
✅ HTTPS obrigatório em produção  
✅ Não expor mensagens de erro de banco de dados  

---

## 🚀 Próximos Recursos

### Em Desenvolvimento

- [ ] **Aprovação/Reprovação de Pedidos**
  - Ações funcionais nos botões
  - Notificação automática ao cliente

- [ ] **Edição de Pedidos**
  - Formulário de edição inline
  - Ajuste de valores e prazos

- [ ] **Filtros e Busca**
  - Filtrar por status
  - Buscar por nome de cliente
  - Buscar por tipo de instrumento
  - Filtro por período

- [ ] **Exportação de Dados**
  - Exportar para PDF
  - Exportar para Excel/CSV

- [ ] **Notificações**
  - Integração com WhatsApp API
  - Envio automático de e-mails
  - Templates personalizados

- [ ] **Relatórios**
  - Gráficos de desempenho
  - Serviços mais solicitados
  - Tempos médios de atendimento

- [ ] **Gestão de Usuários**
  - Criar/editar/excluir usuários
  - Alterar permissões
  - Redefinir senhas

### Roadmap de Longo Prazo

- [ ] Dashboard mobile responsivo
- [ ] Notificações push (PWA)
- [ ] Sistema de mensagens internas
- [ ] Integração com IA para sugestão de orçamentos
- [ ] Agenda de atendimentos
- [ ] Controle de estoque de peças

---

## 📞 Suporte

**E-mail:** contato@luizpimentel.com  
**GitHub:** [luizpimenteldesign/adonis-custom](https://github.com/luizpimenteldesign/adonis-custom)

---

## 📝 Licença

© 2026 Adonis Custom Luthieria. Todos os direitos reservados.

---

**Última Atualização:** 26/01/2026  
**Versão:** 1.0