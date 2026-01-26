<?php $pageTitle = "Gerar Ordem de Serviço"; include '../includes/header.php'; ?>

<div class="container mt-4">
    <div id="loading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="mt-2">Carregando dados da Pré-OS...</p>
    </div>

    <div id="conteudo" style="display: none;">
        <div class="d-flex align-items-center mb-3">
            <a href="pre-os-lista.php" class="btn btn-outline-secondary me-3">
                <span class="material-icons">arrow_back</span> Voltar
            </a>
            <h3 class="mb-0">Pré-OS #<span id="preos-id"></span></h3>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- DADOS DO CLIENTE -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <span class="material-icons">person</span> Dados do Cliente
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Nome:</strong> <span id="cliente-nome"></span></p>
                                <p><strong>Telefone:</strong> <span id="cliente-telefone"></span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Email:</strong> <span id="cliente-email"></span></p>
                                <p><strong>Data:</strong> <span id="preos-data"></span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DADOS DO INSTRUMENTO -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <span class="material-icons">music_note</span> Instrumento
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Tipo:</strong> <span id="inst-tipo"></span></p>
                                <p><strong>Marca:</strong> <span id="inst-marca"></span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Modelo:</strong> <span id="inst-modelo"></span></p>
                                <p><strong>Cor:</strong> <span id="inst-cor"></span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SERVIÇOS -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <span class="material-icons">build</span> Serviços Solicitados
                    </div>
                    <div class="card-body">
                        <div id="servicos-lista"></div>
                    </div>
                </div>

                <!-- OBSERVAÇÕES -->
                <div class="card mb-4">
                    <div class="card-header">
                        <span class="material-icons">comment</span> Observações do Cliente
                    </div>
                    <div class="card-body">
                        <p id="observacoes" class="mb-0"></p>
                    </div>
                </div>
            </div>

            <!-- RESUMO E AÇÕES -->
            <div class="col-md-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header bg-warning">
                        <span class="material-icons">calculate</span> Resumo Financeiro
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td>Subtotal:</td>
                                <td class="text-end"><strong>R$ <span id="subtotal">0,00</span></strong></td>
                            </tr>
                            <tr>
                                <td>
                                    <label for="desconto">Desconto (%):</label>
                                </td>
                                <td class="text-end">
                                    <input type="number" id="desconto" class="form-control form-control-sm text-end" value="0" min="0" max="50" style="width: 80px;">
                                </td>
                            </tr>
                            <tr>
                                <td>Valor desc.:</td>
                                <td class="text-end text-danger">-R$ <span id="valor-desconto">0,00</span></td>
                            </tr>
                            <tr class="table-success">
                                <td><strong>TOTAL:</strong></td>
                                <td class="text-end"><h5 class="mb-0">R$ <span id="total-final">0,00</span></h5></td>
                            </tr>
                        </table>

                        <hr>

                        <div class="mb-3">
                            <label for="prazo-dias">Prazo (dias):</label>
                            <input type="number" id="prazo-dias" class="form-control" value="7" min="1">
                        </div>

                        <div class="mb-3">
                            <label for="obs-interna">Obs. Interna:</label>
                            <textarea id="obs-interna" class="form-control" rows="3"></textarea>
                        </div>

                        <button id="btn-gerar-os" class="btn btn-success w-100 btn-lg">
                            <span class="material-icons">check_circle</span>
                            Gerar OS
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="erro" class="alert alert-danger" style="display: none;">
        <span class="material-icons">error</span>
        <span id="erro-msg"></span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="gerar-os.js"></script>

<?php include '../includes/footer.php'; ?>
