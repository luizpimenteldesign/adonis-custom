<?php

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function successResponse($message, $data = null) {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ], 200);
}

function errorResponse($message, $statusCode = 400) {
    jsonResponse([
        'success' => false,
        'message' => $message
    ], $statusCode);
}

function unauthorizedResponse($message = 'Não autorizado') {
    errorResponse($message, 401);
}

function notFoundResponse($message = 'Recurso não encontrado') {
    errorResponse($message, 404);
}

?>
