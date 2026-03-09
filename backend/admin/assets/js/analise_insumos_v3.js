/**
 * ANÁLISE DE INSUMOS V4.3
 * - Log COMPLETO de resposta
 * - Verifica existência de elementos DOM antes de manipular
 */

let _dadosAnalise        = null;
let _insumosSelecionados = [];
let _categoriaAtual      = null;
let _timeoutBuscaAnalise = null;

function abrirModalAnalise() {
    document.getElementById('analise-corpo').innerHTML = '<div class="analise-loading">Carregando insumos...</div>';
    document.getElementById('analise-acoes').style.display = 'none';
    abrirModal('modal-analise');

    fetch('analise_insumos.php?pre_os_id=' + _pedidoId)
        .then(r => {
            console.log('[Análise] HTTP status:', r.status, r.statusText);
            return r.text().then(txt => {
                console.log('[Análise] Resposta RAW (primeiros 500 chars):', txt.substring(0, 500));
                if (!r.ok) throw new Error('HTTP ' + r.status + ': ' + txt.substring(0, 200));
                try { return JSON.parse(txt); }
                catch (e) { throw new Error('Resposta não é JSON válido'); }
            });
        })
        .then(data => {
            console.log('[Análise] Dados parseados:', data);
            if (!data.sucesso) { console.error('[Análise] Erro:', data.erro); _toast(data.erro || 'Erro ao carregar'); fecharModal('modal-analise'); return; }
            _dadosAnalise = data;
            _insumosSelecionados = (data.insumos_selecionados || []).map(ins => ({
                insumo_id:      parseInt(ins.insumo_id),
                nome:           ins.nome,
                unidade:        ins.unidade,
                valorunitario:  parseFloat(ins.valorunitario || 0),
                quantidade:     parseFloat(ins.quantidade || 1),
                cliente_fornece:parseInt(ins.cliente_fornece || 0),
                estoque:        parseFloat(ins.estoque || 0),
                tipo_insumo:    ins.tipo_insumo || 'variavel',
                categoria:      ins.categoria || ''
            }));
            _renderizarInterface();
        })
        .catch(err => { console.error('[Análise] Exceção:', err); _toast('Erro: ' + err.message); fecharModal('modal-analise'); });
}

function _renderizarInterface() {
    const cats = _dadosAnalise.categorias || [];
    let html = '';

    html += '<div class="analise-resumo"><div class="analise-resumo-title">Serviços do pedido</div>';
    html += '<div class="analise-resumo-tags">';
    (_dadosAnalise.servicos || []).forEach(s => { html += '<span class="analise-tag">' + _esc(s.nome) + '</span>'; });
    if (!(_dadosAnalise.servicos || []).length) html += '<span style="font-size:13px;color:var(--g-text-3)">Nenhum serviço</span>';
    html += '</div></div><hr class="analise-sep">';

    html += '<div class="analise-cats-titulo">Adicionar insumos variáveis por categoria:</div>';
    html += '<div class="analise-cats-grid" id="cats-grid">';
    cats.forEach(cat => {
        const nome  = (cat && typeof cat === 'object') ? cat.nome  : String(cat);
        const icone = (cat && typeof cat === 'object' && cat.icone) ? cat.icone : 'category';
        html += '<button class="cat-btn" onclick="_selecionarCategoria(\'' + _esc(nome) + '\')">'
             + '<span class="material-symbols-outlined">' + _esc(icone) + '</span>'
             + '<span>' + _esc(nome) + '</span></button>';
    });
    if (!cats.length) {
        html += '<button class="cat-btn" onclick="_selecionarCategoria(\'Todos\')">';
        html += '<span class="material-symbols-outlined">grid_view</span><span>Todos</span></button>';
    }
    html += '</div>';

    html += '<div id="area-insumos-cat" style="display:none">';
    html += '<div class="analise-cat-atual" id="cat-atual-label"></div>';
    html += '<input type="text" class="analise-busca" id="busca-insumo" placeholder="Buscar insumo..." oninput="_buscarInsumosCategoria()">';
    html += '<div class="analise-insumos-disponiveis" id="lista-insumos-disponiveis"></div>';
    html += '</div><hr class="analise-sep">';

    html += '<div class="analise-selecionados-titulo" id="sel-titulo">Insumos selecionados (' + _insumosSelecionados.length + ')</div>';
    html += '<div class="analise-selecionados-lista" id="lista-selecionados"></div>';
    html += '<div class="analise-footer"><div class="analise-total-bloco">Total insumos: <strong id="analise-total-ins">—</strong></div></div>';

    document.getElementById('analise-corpo').innerHTML = html;
    document.getElementById('analise-acoes').style.display = 'flex';
    _renderizarInsumosSelecionados();
}

function _selecionarCategoria(categoria) {
    _categoriaAtual = categoria;
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('ativo'));
    if (event && event.target) event.target.closest('.cat-btn').classList.add('ativo');
    document.getElementById('cat-atual-label').textContent = 'Categoria: ' + categoria;
    document.getElementById('area-insumos-cat').style.display = 'block';
    document.getElementById('busca-insumo').value = '';
    document.getElementById('busca-insumo').focus();
    _buscarInsumosCategoria();
}

function _buscarInsumosCategoria() {
    clearTimeout(_timeoutBuscaAnalise);
    _timeoutBuscaAnalise = setTimeout(() => {
        const busca  = document.getElementById('busca-insumo').value.trim();
        const params = new URLSearchParams({ pre_os_id: _pedidoId, categoria: _categoriaAtual });
        if (busca) params.append('q', busca);
        document.getElementById('lista-insumos-disponiveis').innerHTML =
            '<div style="padding:12px;color:var(--g-text-3);font-size:13px">Buscando...</div>';
        fetch('analise_insumos.php?' + params)
            .then(r => r.text().then(txt => {
                if (!r.ok) { console.error('[Busca] HTTP', r.status, txt.substring(0,300)); throw new Error('Erro HTTP ' + r.status); }
                try { return JSON.parse(txt); } catch(e) { console.error('[Busca] JSON inválido:', txt.substring(0,300)); throw new Error('Resposta inválida'); }
            }))
            .then(data => {
                if (!data.sucesso) {
                    document.getElementById('lista-insumos-disponiveis').innerHTML =
                        '<div style="padding:12px;color:var(--g-red);font-size:12px">' + _esc(data.erro || 'Erro ao buscar') + '</div>';
                    return;
                }
                _renderizarInsumosDisponiveis(data.insumos || []);
            })
            .catch(err => {
                document.getElementById('lista-insumos-disponiveis').innerHTML =
                    '<div style="padding:12px;color:var(--g-red);font-size:12px">Erro: ' + _esc(err.message) + '</div>';
            });
    }, 260);
}

function _renderizarInsumosDisponiveis(insumos) {
    const container = document.getElementById('lista-insumos-disponiveis');
    if (!insumos.length) {
        container.innerHTML = '<div style="padding:12px;color:var(--g-text-3);font-size:13px">Nenhum insumo encontrado</div>';
        return;
    }
    let html = '';
    insumos.forEach(ins => {
        const jaAdd  = _insumosSelecionados.some(s => s.insumo_id == ins.id);
        const est    = parseFloat(ins.estoque || 0);
        const estCls = est <= 0 ? 'estoque-zero' : (est < 5 ? 'estoque-baixo' : '');
        const tipoCls = ins.tipo_insumo === 'fixo' ? 'tipo-fixo' : 'tipo-variavel';
        const tipoTxt = ins.tipo_insumo === 'fixo' ? 'Fixo' : 'Variável';
        html += '<div class="insumo-disp-row ' + (jaAdd ? 'ja-adicionado' : '') + '">';
        html += '<div class="insumo-disp-info">';
        html += '<div class="insumo-disp-nome">' + _esc(ins.nome)
             + ' <span style="font-size:10px;font-weight:600;padding:1px 5px;border-radius:6px" class="' + tipoCls + '">' + tipoTxt + '</span></div>';
        html += '<div class="insumo-disp-meta">' + _esc(ins.unidade)
             + ' · R$ ' + parseFloat(ins.valorunitario || 0).toFixed(2)
             + ' · <span class="' + estCls + '">Estoque: ' + est.toFixed(3) + '</span>';
        if (ins.categoria) html += ' · ' + _esc(ins.categoria);
        html += '</div></div>';
        if (jaAdd) {
            html += '<span class="insumo-disp-adicionado">✓ Adicionado</span>';
        } else {
            html += '<button class="insumo-disp-add" title="Adicionar"'
                 + ' onclick="_adicionarInsumo(' + ins.id + ',\'' + _esc(ins.nome) + '\',\'' + _esc(ins.unidade) + '\',' + parseFloat(ins.valorunitario||0) + ',' + est + ',\'' + _esc(ins.tipo_insumo||'variavel') + '\',\'' + _esc(ins.categoria||'') + '\')">'
                 + '<span class="material-symbols-outlined">add</span></button>';
        }
        html += '</div>';
    });
    container.innerHTML = html;
}

function _adicionarInsumo(id, nome, unidade, valorUnit, estoque, tipoInsumo, categoria) {
    if (_insumosSelecionados.some(s => s.insumo_id == id)) { _toast('Insumo já adicionado'); return; }
    _insumosSelecionados.push({
        insumo_id:       id,
        nome:            nome,
        unidade:         unidade,
        valorunitario:   parseFloat(valorUnit),
        quantidade:      1,
        cliente_fornece: estoque <= 0 ? 1 : 0,
        estoque:         parseFloat(estoque),
        tipo_insumo:     tipoInsumo,
        categoria:       categoria
    });
    _renderizarInsumosSelecionados();
    _buscarInsumosCategoria();
    _toast('Insumo adicionado');
}

function _removerInsumoSel(idx) {
    _insumosSelecionados.splice(idx, 1);
    _renderizarInsumosSelecionados();
    if (_categoriaAtual) _buscarInsumosCategoria();
}

function _alterarQuantidadeSel(idx, val) {
    _insumosSelecionados[idx].quantidade = Math.max(0.001, parseFloat(val) || 1);
    const cf    = _insumosSelecionados[idx].cliente_fornece == 1;
    const total = _insumosSelecionados[idx].valorunitario * _insumosSelecionados[idx].quantidade;
    const el    = document.getElementById('sel-val-' + idx);
    if (el) el.textContent = fmt(cf ? 0 : total);
    _recalcularTotal();
}

function _toggleCFSel(idx, checked) {
    _insumosSelecionados[idx].cliente_fornece = checked ? 1 : 0;
    const el    = document.getElementById('sel-val-' + idx);
    const total = _insumosSelecionados[idx].valorunitario * _insumosSelecionados[idx].quantidade;
    if (el) { el.textContent = fmt(checked ? 0 : total); el.classList.toggle('riscado', checked); }
    _recalcularTotal();
}

function _recalcularTotal() {
    let total = 0;
    _insumosSelecionados.forEach(i => { if (!i.cliente_fornece) total += i.valorunitario * i.quantidade; });
    const el = document.getElementById('analise-total-ins');
    if (el) el.textContent = fmt(total);
}

function _renderizarInsumosSelecionados() {
    const container = document.getElementById('lista-selecionados');
    const titulo    = document.getElementById('sel-titulo');
    if (titulo) titulo.textContent = 'Insumos selecionados (' + _insumosSelecionados.length + ')';
    if (!_insumosSelecionados.length) {
        if (container) container.innerHTML = '<div class="analise-vazio">Nenhum insumo selecionado. Insumos fixos dos serviços aparecem automaticamente — adicione variáveis pelas categorias acima.</div>';
        const tot = document.getElementById('analise-total-ins');
        if (tot) tot.textContent = fmt(0);
        return;
    }
    let html = '';
    let totalCobrar = 0;
    _insumosSelecionados.forEach((ins, idx) => {
        const cf  = ins.cliente_fornece == 1;
        const tot = ins.valorunitario * ins.quantidade;
        if (!cf) totalCobrar += tot;
        const tipoCls = ins.tipo_insumo === 'fixo' ? 'tipo-fixo' : 'tipo-variavel';
        const tipoTxt = ins.tipo_insumo === 'fixo' ? 'Fixo' : 'Variável';
        html += '<div class="insumo-sel-row" id="sel-row-' + idx + '">';
        html += '<div class="insumo-sel-main"><div class="insumo-sel-info">';
        html += '<div class="insumo-sel-nome">' + _esc(ins.nome)
             + ' <span style="font-size:10px;font-weight:600;padding:1px 5px;border-radius:6px" class="' + tipoCls + '">' + tipoTxt + '</span></div>';
        html += '<div class="insumo-sel-meta">' + _esc(ins.unidade) + ' · R$ ' + ins.valorunitario.toFixed(2) + ' cada';
        if (ins.categoria) html += ' · ' + _esc(ins.categoria);
        html += '</div></div>';
        html += '<input type="number" class="insumo-sel-qtd" value="' + ins.quantidade + '" min="0.001" step="0.001" onchange="_alterarQuantidadeSel(' + idx + ',this.value)">';
        html += '<div class="insumo-sel-valor ' + (cf ? 'riscado' : '') + '" id="sel-val-' + idx + '">' + fmt(cf ? 0 : tot) + '</div>';
        html += '<button class="insumo-sel-remove" onclick="_removerInsumoSel(' + idx + ')" title="Remover"><span class="material-symbols-outlined">close</span></button>';
        html += '</div><div class="insumo-sel-footer">';
        html += '<label class="insumo-sel-cf"><input type="checkbox" ' + (cf?'checked':'') + ' onchange="_toggleCFSel(' + idx + ',this.checked)"> Cliente fornece</label>';
        html += '</div></div>';
    });
    if (container) container.innerHTML = html;
    const totEl = document.getElementById('analise-total-ins');
    if (totEl) totEl.textContent = fmt(totalCobrar);
}

function confirmarAnalise() {
    const btn = document.getElementById('btn-confirmar-analise');
    btn.disabled = true; btn.textContent = 'Salvando...';
    fetch('analise_insumos.php', {
        method:  'POST',
        headers: {'Content-Type':'application/json'},
        body:    JSON.stringify({ pre_os_id: _pedidoId, insumos: _insumosSelecionados })
    })
    .then(r => r.text().then(txt => {
        if (!r.ok) { console.error('[Confirmar] HTTP', r.status, txt.substring(0,300)); throw new Error('HTTP ' + r.status); }
        try { return JSON.parse(txt); } catch(e) { console.error('[Confirmar] JSON inválido:', txt.substring(0,300)); throw new Error('Resposta inválida'); }
    }))
    .then(data => {
        if (!data.sucesso) {
            console.error('[Confirmar] Erro:', data.erro);
            _toast(data.erro || 'Erro ao salvar');
            btn.disabled = false; btn.textContent = 'Confirmar e Orçar →';
            return;
        }
        fecharModal('modal-analise');

        // Atualiza badge de status na página (tenta vários IDs possíveis)
        const statusEl = document.getElementById('status-label')
                      || document.getElementById('pedido-status')
                      || document.getElementById('status-badge')
                      || document.querySelector('[data-status]')
                      || document.querySelector('.status-badge');
        if (statusEl) {
            statusEl.textContent = 'Em Análise';
            if (statusEl.dataset) statusEl.dataset.status = 'Em analise';
        }

        _abrirOrcamentoComValor(data.total_orcamento, data.total_servicos, data.total_insumos);
    })
    .catch(err => {
        console.error('[Confirmar] Exceção:', err);
        _toast('Erro: ' + err.message);
        btn.disabled = false; btn.textContent = 'Confirmar e Orçar →';
    });
}

function _esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/'/g,'&#39;').replace(/"/g,'&quot;');
}

console.log('[V4.3] analise_insumos_v3.js carregado.');
