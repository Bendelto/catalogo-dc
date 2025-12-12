<?php
session_start();

// ==========================================
// 1. GESTIÓN DE CREDENCIALES (JSON)
// ==========================================
$fileCreds = 'credenciales.json';

// Si el archivo no existe, lo creamos con tus datos por defecto
if (!file_exists($fileCreds)) {
    $defaultCreds = [
        'usuario' => 'admin',       // Usuario por defecto
        'password' => 'Dc@6691400'  // Tu contraseña actual
    ];
    file_put_contents($fileCreds, json_encode($defaultCreds));
}

// Cargar credenciales actuales
$creds = json_decode(file_get_contents($fileCreds), true);

// ==========================================
// 2. LÓGICA DE LOGIN
// ==========================================
$errorMsg = '';

if (isset($_POST['login'])) {
    $userInput = $_POST['user'] ?? '';
    $passInput = $_POST['pass'] ?? '';

    // Validar contra el archivo JSON
    if ($userInput === $creds['usuario'] && $passInput === $creds['password']) {
        $_SESSION['admin'] = true;
        // Redirigir para limpiar el POST
        header("Location: admin.php");
        exit;
    } else {
        $errorMsg = 'Usuario o contraseña incorrectos';
    }
}

// Si no está logueado, mostrar formulario
if (!isset($_SESSION['admin'])) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>Login Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #f0f2f5; }
            .login-card { width: 100%; max-width: 400px; border: 0; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .form-control-lg { font-size: 1.1rem; }
            .btn-lg { font-weight: 600; }
        </style>
    </head>
    <body class="d-flex justify-content-center align-items-center vh-100 px-3">
        <form method="post" class="card p-4 login-card">
            <div class="text-center mb-4">
                <h3 class="fw-bold text-dark">🔐 Acceso</h3>
                <small class="text-muted">Descubre Cartagena</small>
            </div>
            
            <?php if($errorMsg): ?>
                <div class="alert alert-danger text-center p-2 mb-3 small"><?= $errorMsg ?></div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label small text-muted fw-bold">Usuario</label>
                <input type="text" name="user" class="form-control form-control-lg" placeholder="Ej: admin" required autofocus>
            </div>
            
            <div class="mb-4">
                <label class="form-label small text-muted fw-bold">Contraseña</label>
                <input type="password" name="pass" class="form-control form-control-lg" placeholder="••••••••" required>
            </div>
            
            <button name="login" type="submit" class="btn btn-primary w-100 btn-lg">Ingresar</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// ==========================================
//      CÓDIGO DEL PANEL DE ADMINISTRACIÓN
// ==========================================

$fileTours = 'data.json';
$fileConfig = 'config.json';

$tours = file_exists($fileTours) ? json_decode(file_get_contents($fileTours), true) : [];
$config = file_exists($fileConfig) ? json_decode(file_get_contents($fileConfig), true) : ['margen_usd' => 200, 'margen_brl' => 200];

// ORDENAR ALFABÉTICAMENTE
uasort($tours, function($a, $b) {
    return strcasecmp($a['nombre'], $b['nombre']);
});

// GUARDAR CONFIGURACIÓN TASAS
if (isset($_POST['save_config'])) {
    $config['margen_usd'] = floatval($_POST['margen_usd']);
    $config['margen_brl'] = floatval($_POST['margen_brl']);
    file_put_contents($fileConfig, json_encode($config));
    header("Location: admin.php");
    exit;
}

// GUARDAR / EDITAR TOUR
if (isset($_POST['add'])) {
    $nombre = $_POST['nombre'];
    $precio = $_POST['precio'];
    $rango_adulto = !empty($_POST['rango_adulto']) ? $_POST['rango_adulto'] : ''; 
    $precio_nino = !empty($_POST['precio_nino']) ? $_POST['precio_nino'] : 0;
    $rango_nino = !empty($_POST['rango_nino']) ? $_POST['rango_nino'] : '';
    
    $slugInput = !empty($_POST['slug']) ? $_POST['slug'] : $nombre;
    $cleanSlug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $slugInput)));
    $cleanSlug = trim($cleanSlug, '-');
    
    if (!empty($_POST['original_slug']) && $_POST['original_slug'] != $cleanSlug) {
        if(isset($tours[$_POST['original_slug']])) {
            unset($tours[$_POST['original_slug']]);
        }
    }

    $tours[$cleanSlug] = [
        'nombre' => $nombre, 
        'precio_cop' => $precio,
        'rango_adulto' => $rango_adulto,
        'precio_nino' => $precio_nino,
        'rango_nino' => $rango_nino
    ];
    
    file_put_contents($fileTours, json_encode($tours));
    header("Location: admin.php");
    exit;
}

// BORRAR TOUR
if (isset($_GET['delete'])) {
    $slugToDelete = $_GET['delete'];
    if(isset($tours[$slugToDelete])) {
        unset($tours[$slugToDelete]);
        file_put_contents($fileTours, json_encode($tours));
    }
    header("Location: admin.php");
    exit;
}

// CERRAR SESIÓN
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// CARGAR DATOS PARA EDITAR
$tourToEdit = null;
$editingSlug = '';
if (isset($_GET['edit']) && isset($tours[$_GET['edit']])) {
    $tourToEdit = $tours[$_GET['edit']];
    $editingSlug = $_GET['edit'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-bottom: 50px; background-color: #f8f9fa; }
        .table-responsive { 
            border-radius: 12px; 
            background: white; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
            overflow: hidden;
        }
        .table th { background-color: #f1f3f5; border-bottom: 2px solid #dee2e6; }
        
        .btn-action-group { display: flex; gap: 5px; justify-content: flex-end; }
        @media (max-width: 576px) {
            .btn-action-group { flex-direction: column; }
            .btn-action-group .btn { width: 100%; }
        }
    </style>
</head>
<body class="container py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Panel de Control</h2>
            <small class="text-muted">Hola, <?= htmlspecialchars($creds['usuario']) ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" target="_blank" class="btn btn-success btn-sm fw-bold align-self-center">Ver Web ↗</a>
            <a href="?logout=1" class="btn btn-outline-secondary btn-sm align-self-center">Salir</a>
        </div>
    </div>

    <div class="card mb-4 border-warning shadow-sm">
        <div class="card-header bg-warning text-dark fw-bold">📉 Tasa de Cambio</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-6 col-md-5">
                    <label class="small text-muted fw-bold">Resta Dólar</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">-$</span>
                        <input type="number" name="margen_usd" class="form-control" value="<?= $config['margen_usd'] ?>" required>
                    </div>
                </div>
                <div class="col-6 col-md-5">
                    <label class="small text-muted fw-bold">Resta Real</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">-$</span>
                        <input type="number" name="margen_brl" class="form-control" value="<?= $config['margen_brl'] ?>" required>
                    </div>
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end">
                    <button type="submit" name="save_config" class="btn btn-dark btn-sm w-100">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span class="fw-bold"><?= $tourToEdit ? '✏️ Editando Tour' : '➕ Nuevo Tour' ?></span>
            <?php if($tourToEdit): ?>
                <a href="admin.php" class="btn btn-sm btn-light text-primary py-0">Cancelar</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3" id="tourForm">
                <input type="hidden" name="original_slug" value="<?= $editingSlug ?>">

                <div class="col-12"><h6 class="text-primary border-bottom pb-1 small text-uppercase fw-bold">Información Básica</h6></div>
                
                <div class="col-md-6">
                    <label class="form-label small">Nombre del Tour</label>
                    <input type="text" name="nombre" id="inputNombre" class="form-control" required value="<?= $tourToEdit ? htmlspecialchars($tourToEdit['nombre']) : '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">URL (Slug)</label>
                    <input type="text" name="slug" id="inputSlug" class="form-control bg-light text-muted" placeholder="Auto-generado" value="<?= $editingSlug ?>">
                </div>
                
                <div class="col-6">
                    <label class="form-label small">Precio Adulto</label>
                    <input type="number" name="precio" class="form-control" required value="<?= $tourToEdit ? $tourToEdit['precio_cop'] : '' ?>">
                </div>
                <div class="col-6">
                    <label class="form-label small">Edad Adulto</label>
                    <input type="text" name="rango_adulto" class="form-control" placeholder="Ej: 10+" value="<?= $tourToEdit && isset($tourToEdit['rango_adulto']) ? htmlspecialchars($tourToEdit['rango_adulto']) : '' ?>">
                </div>

                <div class="col-12 mt-3"><h6 class="text-primary border-bottom pb-1 small text-uppercase fw-bold">Información Niños</h6></div>
                
                <div class="col-6">
                    <label class="form-label small">Precio Niño</label>
                    <input type="number" name="precio_nino" class="form-control" placeholder="0" value="<?= $tourToEdit && !empty($tourToEdit['precio_nino']) ? $tourToEdit['precio_nino'] : '' ?>">
                </div>
                <div class="col-6">
                    <label class="form-label small">Edad Niño</label>
                    <input type="text" name="rango_nino" class="form-control" placeholder="Ej: 4-9" value="<?= $tourToEdit && isset($tourToEdit['rango_nino']) ? htmlspecialchars($tourToEdit['rango_nino']) : '' ?>">
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" name="add" class="btn btn-primary w-100 fw-bold py-2"><?= $tourToEdit ? 'Actualizar Tour' : 'Guardar Tour' ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Tour</th>
                    <th>Precios</th>
                    <th class="text-end pe-3">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tours as $slug => $tour): ?>
                <tr class="<?= $slug == $editingSlug ? 'table-warning' : '' ?>">
                    <td class="ps-3">
                        <span class="fw-bold d-block"><?= htmlspecialchars($tour['nombre']) ?></span>
                        <small class="text-muted" style="font-size: 0.75rem;">/<?= $slug ?></small>
                    </td>
                    <td>
                        <small class="d-block text-nowrap">Ad: $<?= number_format($tour['precio_cop']) ?></small>
                        <?php if(!empty($tour['precio_nino'])): ?>
                            <small class="d-block text-muted text-nowrap">Ni: $<?= number_format($tour['precio_nino']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-3">
                        <div class="btn-action-group">
                            <a href="?edit=<?= $slug ?>" class="btn btn-warning btn-sm text-dark">Editar</a>
                            <a href="?delete=<?= $slug ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Borrar este tour?');">Borrar</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        const inputNombre = document.getElementById('inputNombre');
        const inputSlug = document.getElementById('inputSlug');
        
        inputNombre.addEventListener('input', function() {
            let text = this.value;
            let slug = text.toLowerCase()
                .normalize("NFD").replace(/[\u0300-\u036f]/g, "") 
                .replace(/[^a-z0-9]+/g, '-') 
                .replace(/^-+|-+$/g, ''); 
            inputSlug.value = slug;
        });
    </script>

</body>
</html>