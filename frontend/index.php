<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Solicite orçamento para manutenção e customização de instrumentos musicais">
    <title>Solicitar Orçamento - Adonis Luthieria</title>

    <!-- FAVICON CORRIGIDO -->
    <link rel="icon" type="image/x-icon" href="public/assets/img/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="public/assets/img/favicon.ico">

    <!-- CSS -->
    <link rel="stylesheet" href="public/assets/css/style.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- HEADER -->
    <header class="header">
        <div class="header-container">
            <div class="header-logo">
                <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis3.png" alt="Adonis Custom">
            </div>
            <div class="header-title">
                Solicitar Orçamento
            </div>
        </div>
    </header>

    <!-- CONTEÚDO PRINCIPAL -->
    <main>
        <div class="main-container">

            <h1 class="page-title">Solicitar Orçamento</h1>
            <p class="page-subtitle">Preencha os dados abaixo para receber um orçamento personalizado</p>

            <!-- ALERTAS -->
            <div id="alert" class="hidden"></div>

            <!-- FORMULÁRIO -->
            <form id="formOrcamento" enctype="multipart/form-data">

                <!-- SEÇÃO 1: DADOS DO CLIENTE -->
                <div class="form-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                        Seus Dados
                    </h2>

                    <div class="form-group">
                        <label for="cliente_nome">Nome completo <span class="required">*</span></label>
                        <input type="text" name="cliente_nome" id="cliente_nome" required>
                    </div>

                    <div class="form-group">
                        <label for="cliente_telefone">Telefone/WhatsApp <span class="required">*</span></label>
                        <input type="tel" name="cliente_telefone" id="cliente_telefone" placeholder="(27) 99999-9999" required>
                    </div>

                    <div class="form-group">
                        <label for="cliente_email">E-mail</label>
                        <input type="email" name="cliente_email" id="cliente_email">
                    </div>

                    <div class="form-group">
                        <label for="cliente_endereco">Endereço completo <span class="required">*</span></label>
                        <textarea name="cliente_endereco" id="cliente_endereco" rows="3" placeholder="Rua, número, complemento, bairro, cidade - UF, CEP" required></textarea>
                    </div>
                </div>

                <!-- SEÇÃO 2: DADOS DO INSTRUMENTO -->
                <div class="form-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24">
                            <path d="M12 3v9.28c-.47-.17-.97-.28-1.5-.28C8.01 12 6 14.01 6 16.5S8.01 21 10.5 21c2.31 0 4.2-1.75 4.45-4H15V6h4V3h-7z"/>
                        </svg>
                        Dados do Instrumento
                    </h2>

                    <div class="form-group">
                        <label for="tipo">Tipo de Instrumento <span class="required">*</span></label>
                        <select name="tipo" id="tipo" required>
                            <option value="">Selecione...</option>
                            <option value="Guitarra">Guitarra</option>
                            <option value="Baixo">Baixo</option>
                            <option value="Violao">Violão</option>
                            <option value="Viola Caipira">Viola Caipira</option>
                            <option value="Cavaquinho">Cavaquinho</option>
                            <option value="Ukulele">Ukulele</option>
                            <option value="Bandolim">Bandolim</option>
                            <option value="Amplificador">Amplificador</option>
                            <option value="Pedal/Pedalboard">Pedal / Pedalboard</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>

                    <div id="campo_tipo_outro" class="form-group hidden">
                        <label for="tipo_outro">Especifique o tipo:</label>
                        <input type="text" name="tipo_outro" id="tipo_outro">
                    </div>

                    <div class="form-group">
                        <label for="marca">Marca <span class="required">*</span></label>
                        <select name="marca" id="marca" required>
                            <option value="">Selecione...</option>
                            <option value="Adonis Custom">Adonis Custom</option>
                            <option value="Condor">Condor</option>
                            <option value="Crafter">Crafter</option>
                            <option value="DiGiorgio">DiGiorgio</option>
                            <option value="Eagle">Eagle</option>
                            <option value="Epiphone">Epiphone</option>
                            <option value="Fender">Fender</option>
                            <option value="Giannini">Giannini</option>
                            <option value="Gibson">Gibson</option>
                            <option value="Godin">Godin</option>
                            <option value="Gretsch">Gretsch</option>
                            <option value="Hofner">Hofner</option>
                            <option value="Ibanez">Ibanez</option>
                            <option value="Jackson">Jackson</option>
                            <option value="Kashima">Kashima</option>
                            <option value="Luthier Artesanal">Luthier Artesanal</option>
                            <option value="Martin">Martin</option>
                            <option value="Music Man">Music Man</option>
                            <option value="PRS">PRS</option>
                            <option value="Rickenbacker">Rickenbacker</option>
                            <option value="Seizi">Seizi</option>
                            <option value="Shelter">Shelter</option>
                            <option value="Strinberg">Strinberg</option>
                            <option value="Tagima">Tagima</option>
                            <option value="Takamine">Takamine</option>
                            <option value="Taylor">Taylor</option>
                            <option value="Thomaz">Thomaz</option>
                            <option value="Washburn">Washburn</option>
                            <option value="Yamaha">Yamaha</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>

                    <div id="campo_marca_outro" class="form-group hidden">
                        <label for="marca_outro">Especifique a marca:</label>
                        <input type="text" name="marca_outro" id="marca_outro">
                    </div>

                    <div class="form-group">
                        <label for="modelo">Modelo <span class="required">*</span></label>
                        <select name="modelo" id="modelo" required disabled>
                            <option value="">Selecione o tipo primeiro...</option>
                        </select>
                    </div>

                    <div id="campo_modelo_outro" class="form-group hidden">
                        <label for="modelo_outro">Especifique o modelo:</label>
                        <input type="text" name="modelo_outro" id="modelo_outro">
                    </div>

                    <div class="form-group">
                        <label for="referencia">Referência do Modelo</label>
                        <input type="text" name="referencia" id="referencia" placeholder="Ex: Custom Shop '59, American Standard, Signature Series">
                    </div>

                    <div id="campo_cor" class="form-group">
                        <label for="cor">Cor <span class="required cor-required">*</span></label>
                        <select name="cor" id="cor">
                            <option value="">Selecione...</option>
                            <option value="Branco">Branco</option>
                            <option value="Preto">Preto</option>
                            <option value="Vermelho">Vermelho</option>
                            <option value="Azul">Azul</option>
                            <option value="Amarelo">Amarelo</option>
                            <option value="Verde">Verde</option>
                            <option value="Laranja">Laranja</option>
                            <option value="Rosa">Rosa</option>
                            <option value="Roxo">Roxo</option>
                            <option value="Natural">Natural</option>
                            <option value="Sunburst">Sunburst</option>
                            <option value="Cherry Burst">Cherry Burst</option>
                            <option value="Honey Burst">Honey Burst</option>
                            <option value="Tobacco Burst">Tobacco Burst</option>
                            <option value="Ocean Burst">Ocean Burst</option>
                            <option value="Metalizado">Metalizado</option>
                            <option value="Transparente">Transparente</option>
                            <option value="Envelhecido">Envelhecido</option>
                            <option value="Personalizado">Personalizado</option>
                            <option value="Outra">Outra</option>
                        </select>
                    </div>

                    <div id="campo_cor_outro" class="form-group hidden">
                        <label for="cor_outro">Especifique a cor:</label>
                        <input type="text" name="cor_outro" id="cor_outro">
                    </div>

                    <div class="form-group">
                        <label for="numero_serie">Número de Série</label>
                        <input type="text" name="numero_serie" id="numero_serie">
                    </div>
                </div>

                <!-- SEÇÃO 3: FOTOS -->
                <div class="form-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24">
                            <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                        </svg>
                        Fotos do Instrumento
                    </h2>

                    <label for="fotos" class="file-upload">
                        <svg class="file-upload-icon" viewBox="0 0 24 24">
                            <path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/>
                        </svg>
                        <p class="file-upload-text">Clique para selecionar fotos</p>
                        <p class="file-upload-hint">Máximo de 5 fotos (JPG, PNG, WEBP)</p>
                        <input type="file" name="fotos[]" id="fotos" accept="image/*" multiple>
                    </label>

                    <div id="preview" class="preview-container"></div>
                </div>

                <!-- SEÇÃO 4: SERVIÇOS -->
                <div class="form-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24">
                            <path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/>
                        </svg>
                        Serviços Desejados <span class="required">*</span>
                    </h2>
                    <p style="margin-bottom: 16px; color: #666; font-size: 14px;">Selecione os serviços necessários</p>

                    <div class="checkbox-group" id="servicos-container">
                        <p style="color: #999; text-align: center; padding: 20px;">Carregando serviços...</p>
                    </div>
                </div>

                <!-- SEÇÃO 5: OBSERVAÇÕES -->
                <div class="form-section">
                    <h2 class="section-title">
                        <svg class="section-icon" viewBox="0 0 24 24">
                            <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/>
                        </svg>
                        Observações
                    </h2>

                    <div class="form-group">
                        <label for="observacoes">Descreva o problema ou detalhes sobre o serviço:</label>
                        <textarea name="observacoes" id="observacoes" rows="4" placeholder="Ex: Cordas muito altas, ruído no potenciômetro, troca de cor desejada, etc."></textarea>
                    </div>
                </div>

                <!-- BOTÃO DE ENVIO -->
                <button type="submit" class="btn-primary">
                    <svg class="btn-icon" viewBox="0 0 24 24">
                        <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
                    </svg>
                    Enviar Pedido de Orçamento
                </button>
            </form>
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-container">
            <p class="footer-text">© 2026 Adonis Custom Luthieria. Todos os direitos reservados.</p>
            <div class="footer-links">
                <a href="#" class="footer-link">Política de Privacidade</a>
                <a href="#" class="footer-link">Termos de Uso</a>
                <a href="#" class="footer-link">Contato</a>
            </div>
        </div>
    </footer>

    <!-- JAVASCRIPT COM VERSÃO DINÂMICA -->
    <script src="public/assets/js/form-luthieria.js?v=<?php echo time(); ?>"></script>
</body>
</html>
