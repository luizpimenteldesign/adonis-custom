<?php
require_once __DIR__ . '/../models/PreOS.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/Servico.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/helpers.php';

class PreOSController {
    private $preOSModel;
    private $clienteModel;
    private $servicoModel;

    public function __construct() {
        $this->preOSModel = new PreOS();
        $this->clienteModel = new Cliente();
        $this->servicoModel = new Servico();
    }

    public function criar() {
        $dados = json_decode(file_get_contents("php://input"), true);

        // Validações
        if (!isset($dados['nome']) || !isset($dados['telefone'])) {
            errorResponse('Nome e telefone são obrigatórios', 400);
        }

        if (!isset($dados['servicos']) || !is_array($dados['servicos']) || empty($dados['servicos'])) {
            errorResponse('Selecione pelo menos um serviço', 400);
        }

        // Validar telefone
        if (!validarTelefone($dados['telefone'])) {
            errorResponse('Telefone inválido', 400);
        }

        // Validar email se fornecido
        if (isset($dados['email']) && !empty($dados['email']) && !validarEmail($dados['email'])) {
            errorResponse('Email inválido', 400);
        }

        try {
            // Buscar ou criar cliente
            $cliente = $this->clienteModel->buscarPorTelefone($dados['telefone']);

            if (!$cliente) {
                $cliente_id = $this->clienteModel->criar([
                    'nome' => sanitizeInput($dados['nome']),
                    'telefone' => sanitizeInput($dados['telefone']),
                    'email' => isset($dados['email']) ? sanitizeInput($dados['email']) : null,
                    'observacoes' => isset($dados['observacoes']) ? sanitizeInput($dados['observacoes']) : null
                ]);
            } else {
                $cliente_id = $cliente['id'];
            }

            // Criar Pré-OS
            $pre_os = $this->preOSModel->criar(
                $cliente_id, 
                isset($dados['observacoes']) ? sanitizeInput($dados['observacoes']) : null
            );

            if (!$pre_os) {
                errorResponse('Erro ao criar Pré-OS', 500);
            }

            // Adicionar serviços
            $valor_total = 0;
            foreach ($dados['servicos'] as $servico_id) {
                $servico = $this->servicoModel->buscarPorId($servico_id);

                if ($servico) {
                    $quantidade = 1; // Padrão
                    $this->preOSModel->adicionarServico(
                        $pre_os['id'],
                        $servico_id,
                        $quantidade,
                        $servico['valor_base']
                    );
                    $valor_total += $servico['valor_base'];
                }
            }

            // TODO: Enviar notificação para Admin via WhatsApp

            successResponse('Pré-OS criada com sucesso', [
                'numero_pre_os' => $pre_os['numero_pre_os'],
                'public_token' => $pre_os['public_token'],
                'valor_estimado' => $valor_total
            ]);

        } catch (Exception $e) {
            errorResponse('Erro ao processar solicitação: ' . $e->getMessage(), 500);
        }
    }

    public function listar() {
        // TODO: Validar token de Admin

        try {
            $pre_os_list = $this->preOSModel->listar();
            successResponse('Pré-OS listadas com sucesso', $pre_os_list);
        } catch (Exception $e) {
            errorResponse('Erro ao listar Pré-OS: ' . $e->getMessage(), 500);
        }
    }

    public function buscarPorToken($token) {
        try {
            $pre_os = $this->preOSModel->buscarPorToken($token);

            if ($pre_os) {
                successResponse('Pré-OS encontrada', $pre_os);
            } else {
                notFoundResponse('Pré-OS não encontrada ou token inválido');
            }
        } catch (Exception $e) {
            errorResponse('Erro ao buscar Pré-OS: ' . $e->getMessage(), 500);
        }
    }
}
?>
