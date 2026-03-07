/**
 * ANÁLISE DE INSUMOS V3.1 - SELEÇÃO POR CATEGORIAS
 * Versão: 3.1.0 - Cache-bust forçado
 * Interface otimizada: Admin escolhe categoria > busca insumo > adiciona apenas o que precisa
 */

let _dadosAnalise = null;
let _insumosSelecionados = [];
let _categoriaAtual = null;

function abrirModalAnalise() {
    console.log('[V3.1] Abrindo modal de análise...');
    document.getElementById('analise-corpo').innerHTML = '<div class="analise-loading">Carregando...</div>';
    document.getElementById('analise-acoes').style.display = 'none';
    abrirModal('modal-analise');

    fetch('analise_insumos.php?pre_os_id=' + _pedidoId)
        .then(r => r.json())
        .then(data => {
            console.log('[V3.1] Dados recebidos:', data);
            if (!data.sucesso) {
                _toast(data.erro || 'Erro ao carregar');
                fecharModal('modal-analise');
                return;
            }
            _dadosAnalise = data;
            _insumosSelecionados = (data.insumos_selecionados || []).map(ins => ({
                insumo_id: ins.insumo_id,
                nome: ins.nome,
                unidade: ins.unidade,
                valor_unitario: parseFloat(ins.valor_unitario),
                quantidade: parseFloat(ins.quantidade),
                cliente_fornece: parseInt(ins.cliente_fornece),
                quantidade_estoque: parseFloat(ins.quantidade_estoque)
            }));
            console.log('[V3.1] Categorias:', data.categorias);
            _renderizarInterfaceCategorias();
        })
        .catch(() => {
            _toast('Erro de conexão');
            fecharModal('modal-analise');
        });
}

function _renderizarInterfaceCategorias() {
    console.log('[V3.1] Renderizando interface...');
    let html = '';

    // Resumo dos serviços
    html += '<div class="analise-resumo">';
    html += '<div class="analise-resumo-title">Serviços do pedido</div>';
    html += '<div class="analise-resumo-tags">';
    if (_dadosAnalise.servicos && _dadosAnalise.servicos.length) {
        _dadosAnalise.servicos.forEach(s => {
            html += '<span class="analise-tag">' + _esc(s.nome) + '</span>';
        });
    } else {
        html += '<span style="font-size:13px;color:var(--g-text-3)">Nenhum serviço</span>';
    }
    html += '</div></div>';
    html += '<hr class="analise-sep">';

    // Botões de categorias
    html += '<div class="analise-cats-titulo">Escolha a categoria para adicionar insumos:</div>';
    html += '<div class="analise-cats-grid" id="cats-grid">';
    if (_dadosAnalise.categorias && _dadosAnalise.categorias.length) {
        _dadosAnalise.categorias.forEach(cat => {
            console.log('[V3.1] Processando categoria:', cat, 'Tipo:', typeof cat);
            // Verificação correta: objeto válido (não null, não array) com propriedade 'nome'
            const ehObjeto = cat && typeof cat === 'object' && !Array.isArray(cat) && cat.nome;
            const nomeCat = ehObjeto ? cat.nome : String(cat);
            const iconeCat = ehObjeto ? (cat.icone || 'category') : 'category';
            
            console.log('[V3.1]   -> Nome:', nomeCat, ', Ícone:', iconeCat);
            
            html += '<button class="cat-btn" onclick="_selecionarCategoria(\'' + _esc(nomeCat) + '\')">'
                 + '<span class="material-symbols-outlined">' + _esc(iconeCat) + '</span>'
                 + '<span>' + _esc(nomeCat) + '</span></button>';
        });
    } else {
        console.log('[V3.1] FALLBACK: Sem categorias, usando botão Todos');
        // Fallback: sem categorias, mostra botão "Todos"
        html += '<button class="cat-btn" onclick="_selecionarCategoria(\'Todos\')">' +
                '<span class="material-symbols-outlined">grid_view</span>' +
                '<span>Todos os Insumos</span></button>';
    }
    html += '</div>';

    // Área de busca e lista de insumos da categoria (inicialmente oculto)
    html += '<div id="area-insumos-cat" style="display:none">';
    html += '<div class="analise-cat-atual" id="cat-atual-label"></div>';
    html += '<input type="text" class="analise-busca" id="busca-insumo" placeholder="Buscar insumo..." oninput="_buscarInsumosCategoria()">';
    html += '<div class="analise-insumos-disponiveis" id="lista-insumos-disponiveis"></div>';
    html += '</div>';

    html += '<hr class="analise-sep">';

    // Lista de insumos selecionados
    html += '<div class="analise-selecionados-titulo" id="sel-titulo">Insumos selecionados (' + _insumosSelecionados.length + ')</div>';
    html += '<div class="analise-selecionados-lista" id="lista-selecionados"></div>';

    // Footer com total
    html += '<div class="analise-footer">';
    html += '<div class="analise-total-bloco">Total insumos: <strong id="analise-total-ins">—</strong></div>';
    html += '</div>';

    document.getElementById('analise-corpo').innerHTML = html;
    document.getElementById('analise-acoes').style.display = 'flex';
    _renderizarInsumosSelecionados();
}

function _selecionarCategoria(categoria) {
    _categoriaAtual = categoria;
    document.querySelectorAll('.cat-btn').forEach(btn => btn.classList.remove('ativo'));
    event.target.closest('.cat-btn').classList.add('ativo');
    
    document.getElementById('cat-atual-label').textContent = 'Insumos da categoria: ' + categoria;
    document.getElementById('area-insumos-cat').style.display = 'block';
    document.getElementById('busca-insumo').value = '';
    document.getElementById('busca-insumo').focus();
    
    _buscarInsumosCategoria();
}

function _buscarInsumosCategoria() {
    const busca = document.getElementById('busca-insumo').value.trim();
    const params = new URLSearchParams({ categoria: _categoriaAtual });
    if (busca) params.append('q', busca);

    document.getElementById('lista-insumos-disponiveis').innerHTML = '<div style="padding:12px;color:var(--g-text-3);font-size:13px">Buscando...</div>';

    fetch('analise_insumos.php?' + params)
        .then(r => r.json())
        .then(data => {
            if (!data.sucesso) {
                document.getElementById('lista-insumos-disponiveis').innerHTML = '<div style="padding:12px;color:var(--g-red);font-size:13px">Erro ao buscar insumos</div>';
                return;
            }
            _renderizarInsumosDisponiveis(data.insumos || []);
        })
        .catch(() => {
            document.getElementById('lista-insumos-disponiveis').innerHTML = '<div style="padding:12px;color:var(--g-red);font-size:13px">Erro de conexão</div>';
        });
}

function _renderizarInsumosDisponiveis(insumos) {
    const container = document.getElementById('lista-insumos-disponiveis');
    
    if (!insumos.length) {
        container.innerHTML = '<div style="padding:12px;color:var(--g-text-3);font-size:13px">Nenhum insumo encontrado</div>';
        return;
    }

    let html = '';
    insumos.forEach(ins => {
        const jaAdicionado = _insumosSelecionados.some(s => s.insumo_id == ins.id);
        const estoque = parseFloat(ins.quantidade_estoque || 0);
        const estoqueClass = estoque <= 0 ? 'estoque-zero' : (estoque < 5 ? 'estoque-baixo' : '');
        
        html += '<div class="insumo-disp-row ' + (jaAdicionado ? 'ja-adicionado' : '') + '">';
        html += '<div class="insumo-disp-info">';
        html += '<div class="insumo-disp-nome">' + _esc(ins.nome) + '</div>';
        html += '<div class="insumo-disp-meta">';
        html += _esc(ins.unidade) + ' • R$ ' + parseFloat(ins.valor_unitario).toFixed(2);
        html += ' • <span class="' + estoqueClass + '">Estoque: ' + estoque.toFixed(3) + '</span>';
        html += '</div></div>';
        
        if (jaAdicionado) {
            html += '<span class="insumo-disp-adicionado">Adicionado</span>';
        } else {
            html += '<button class="insumo-disp-add" onclick="_adicionarInsumo(' + ins.id + ', \'' + _esc(ins.nome) + '\', \'' + _esc(ins.unidade) + '\', ' + ins.valor_unitario + ', ' + estoque + ')" title="Adicionar">';
            html += '<span class="material-symbols-outlined">add</span></button>';
        }
        html += '</div>';
    });

    container.innerHTML = html;
}

function _adicionarInsumo(id, nome, unidade, valorUnit, estoque) {
    // Verifica se já foi adicionado
    if (_insumosSelecionados.some(s => s.insumo_id == id)) {
        _toast('Insumo já adicionado');
        return;
    }

    const semEstoque = parseFloat(estoque) <= 0;
    
    _insumosSelecionados.push({
        insumo_id: id,
        nome: nome,
        unidade: unidade,
        valor_unitario: parseFloat(valorUnit),
        quantidade: 1,
        cliente_fornece: semEstoque ? 1 : 0,
        quantidade_estoque: parseFloat(estoque)
    });

    _renderizarInsumosSelecionados();
    _buscarInsumosCategoria(); // Atualiza lista para marcar como adicionado
    _toast('Insumo adicionado');
}

function _renderizarInsumosSelecionados() {
    const container = document.getElementById('lista-selecionados');
    document.getElementById('sel-titulo').textContent = 'Insumos selecionados (' + _insumosSelecionados.length + ')';

    if (!_insumosSelecionados.length) {
        container.innerHTML = '<div class="analise-vazio">Nenhum insumo selecionado. Escolha uma categoria acima e adicione os insumos necessários.</div>';
        document.getElementById('analise-total-ins').textContent = fmt(0);
        return;
    }

    let html = '';
    let totalCobrar = 0;

    _insumosSelecionados.forEach((ins, idx) => {
        const cf = ins.cliente_fornece == 1;
        const valorTotal = ins.valor_unitario * ins.quantidade;
        if (!cf) totalCobrar += valorTotal;

        html += '<div class="insumo-sel-row" id="sel-row-' + idx + '">';
        
        // Linha principal
        html += '<div class="insumo-sel-main">';
        html += '<div class="insumo-sel-info">';
        html += '<div class="insumo-sel-nome">' + _esc(ins.nome) + '</div>';
        html += '<div class="insumo-sel-meta">' + _esc(ins.unidade) + ' • R$ ' + ins.valor_unitario.toFixed(2) + ' cada</div>';
        html += '</div>';
        
        html += '<input type="number" class="insumo-sel-qtd" value="' + ins.quantidade + '" min="0.001" step="0.001" '
             + 'onchange="_alterarQuantidadeSel(' + idx + ', this.value)" title="Quantidade">';
        
        html += '<div class="insumo-sel-valor ' + (cf ? 'riscado' : '') + '" id="sel-val-' + idx + '">' + fmt(cf ? 0 : valorTotal) + '</div>';
        
        html += '<button class="insumo-sel-remove" onclick="_removerInsumoSel(' + idx + ')" title="Remover">';
        html += '<span class="material-symbols-outlined">close</span></button>';
        html += '</div>'; // main
        
        // Linha checkbox cliente fornece
        html += '<div class="insumo-sel-footer">';
        html += '<label class="insumo-sel-cf"><input type="checkbox" ' + (cf ? 'checked' : '') + ' '
             + 'onchange="_toggleCFSel(' + idx + ', this.checked)"> Cliente fornece</label>';
        html += '</div>'; // footer
        
        html += '</div>'; // row
    });

    container.innerHTML = html;
    document.getElementById('analise-total-ins').textContent = fmt(totalCobrar);
}

function _alterarQuantidadeSel(idx, val) {
    const qtd = Math.max(0.001, parseFloat(val) || 1);
    _insumosSelecionados[idx].quantidade = qtd;
    
    const cf = _insumosSelecionados[idx].cliente_fornece == 1;
    const total = _insumosSelecionados[idx].valor_unitario * qtd;
    
    const valEl = document.getElementById('sel-val-' + idx);
    if (valEl) valEl.textContent = fmt(cf ? 0 : total);
    
    _recalcularTotal();
}

function _toggleCFSel(idx, checked) {
    _insumosSelecionados[idx].cliente_fornece = checked ? 1 : 0;
    
    const valEl = document.getElementById('sel-val-' + idx);
    if (valEl) {
        const total = _insumosSelecionados[idx].valor_unitario * _insumosSelecionados[idx].quantidade;
        valEl.textContent = fmt(checked ? 0 : total);
        valEl.classList.toggle('riscado', checked);
    }
    
    _recalcularTotal();
}

function _removerInsumoSel(idx) {
    _insumosSelecionados.splice(idx, 1);
    _renderizarInsumosSelecionados();
    if (_categoriaAtual) _buscarInsumosCategoria(); // Atualiza lista disponíveis
}

function _recalcularTotal() {
    let total = 0;
    _insumosSelecionados.forEach(ins => {
        if (!ins.cliente_fornece) total += ins.valor_unitario * ins.quantidade;
    });
    document.getElementById('analise-total-ins').textContent = fmt(total);
}

function confirmarAnalise() {
    const btn = document.getElementById('btn-confirmar-analise');
    btn.disabled = true;
    btn.textContent = 'Salvando...';

    fetch('analise_insumos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            pre_os_id: _pedidoId,
            insumos: _insumosSelecionados
        })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.sucesso) {
            _toast(data.erro || 'Erro ao salvar');
            btn.disabled = false;
            btn.textContent = 'Confirmar e Orçar →';
            return;
        }
        
        fecharModal('modal-analise');
        document.getElementById('status-label').textContent = 'Em Análise';
        
        // Abre modal orçamento com valor calculado
        _abrirOrcamentoComValor(
            data.total_orcamento,
            data.total_servicos,
            data.total_insumos
        );
    })
    .catch(() => {
        _toast('Erro de conexão');
        btn.disabled = false;
        btn.textContent = 'Confirmar e Orçar →';
    });
}

function _esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/'/g, '&#39;').replace(/\"/g, '&quot;');
}

console.log('[V3.1] Script analise_insumos_v3.js carregado com sucesso!');
