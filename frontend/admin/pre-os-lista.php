<?php $pageTitle = "Pré-OS Pendentes"; include '../includes/header.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Pré-OS Pendentes <span class="badge bg-primary" id="total-preos">0</span></h2>
        <button class="btn btn-outline-secondary" onclick="location.reload()">
            <span class="material-icons">refresh</span> Atualizar
        </button>
    </div>

    <div id="loading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="mt-2">Carregando Pré-OS...</p>
    </div>

    <div id="lista-preos" class="row g-3" style="display: none;"></div>
    
    <div id="erro" class="alert alert-danger" style="display: none;">
        <span class="material-icons">error</span>
        <span id="erro-msg"></span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="pre-os-lista.js?v=<?php echo time(); ?>"></script>

<?php include '../includes/footer.php'; ?>
