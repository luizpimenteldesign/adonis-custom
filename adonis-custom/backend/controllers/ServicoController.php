<?php
require_once __DIR__ . '/../models/Servico.php';
require_once __DIR__ . '/../utils/response.php';

class ServicoController {
    private $servicoModel;

    public function __construct() {
        $this->servicoModel = new Servico();
    }

    public function listar() {
        try {
            $servicos = $this->servicoModel->listar();
            successResponse('Serviços listados com sucesso', $servicos);
        } catch (Exception $e) {
            errorResponse('Erro ao listar serviços: ' . $e->getMessage(), 500);
        }
    }

    public function buscarPorId($id) {
        try {
            $servico = $this->servicoModel->buscarPorId($id);

            if ($servico) {
                successResponse('Serviço encontrado', $servico);
            } else {
                notFoundResponse('Serviço não encontrado');
            }
        } catch (Exception $e) {
            errorResponse('Erro ao buscar serviço: ' . $e->getMessage(), 500);
        }
    }
}
?>
