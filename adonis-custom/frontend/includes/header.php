<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Adonis Luthieria'; ?> - Adonis</title>
    <link rel="icon" type="image/x-icon" href="/adonis-custom/frontend/public/assets/img/favicon.ico">
    <link rel="stylesheet" href="/adonis-custom/frontend/public/assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .card-preos { cursor: pointer; transition: all 0.3s; }
        .card-preos:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .material-icons { vertical-align: middle; }
        body { background: #f4f4f4; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-container">
            <div class="header-logo">
                <img src="/adonis-custom/frontend/public/assets/img/Logo-Adonis3.png" alt="Adonis Luthieria">
            </div>
            <div class="header-title">
                <?php echo $pageTitle ?? 'Área Restrita'; ?>
            </div>
            <button class="btn btn-sm" style="background: #e74c3c; color: white;" onclick="logout()">
                <span class="material-icons" style="font-size: 16px;">logout</span> Sair
            </button>
        </div>
    </header>
    <script>
        function logout() {
            if (confirm('Deseja sair?')) {
                window.location.href = '/adonis-custom/frontend/login.html';
            }
        }
    </script>
