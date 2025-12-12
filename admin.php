<?php
session_start();

// ==========================================
// 1. GESTIÓN DE CREDENCIALES
// ==========================================
$fileCreds = 'credenciales.json';
if (!file_exists($fileCreds)) {
    $defaultCreds = ['usuario' => 'admin', 'password' => 'Dc@6691400'];
    file_put_contents($fileCreds, json_encode($defaultCreds));
}
$creds = json_decode(file_get_contents($fileCreds), true);

// ==========================================
// 2. LOGIN
// ==========================================
$errorMsg = '';
if (isset($_POST['login'])) {
    $userInput = $_POST['user'] ?? '';
    $passInput = $_POST['pass'] ?? '';
    if ($userInput === $creds['usuario'] && $passInput === $creds['password']) {
        $_SESSION['admin'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $errorMsg = 'Incorrecto';
    }
}

if (!isset($_SESSION['admin'])) {
    // (Formulario de login abreviado para no repetir código innecesario, es el mismo de antes)
    ?>
    <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Login</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="d-flex justify-content-center align-items-center vh-100 px-3 bg-light"><form method="post" class="card p-4 shadow" style="max-width:400px;width:100%"><h3 class="text-center mb-3">🔐 Acceso</h3><?php if($errorMsg): ?><div class="alert alert-danger py-1"><?= $errorMsg ?></div><?php endif; ?><input type="text" name="user" class="form-control mb-3" placeholder="Usuario" required autofocus><input type="password" name="pass" class="form-control mb-3" placeholder="Contraseña" required><button name="login" class="btn btn-primary w-100">Entrar</button></form></body></html>
    <?php exit;
}

// ==========================================
//      PANEL DE ADMINISTRACIÓN
// ==========================================

$fileTours = 'data.json';
$fileConfig = 'config.json';
$tours = file_exists($fileTours) ? json_decode(file_get_contents($fileTours), true) : [];
$config = file_exists($fileConfig) ? json_decode(file_get_contents($fileConfig), true) : ['margen_usd' => 200, 'margen_brl' => 200];

uasort($tours, function($a, $b) { return strcasecmp($a['nombre'], $b['nombre']); });

if (isset($_POST['save_config'])) {
    $config['margen_usd'] = floatval($_POST['margen_usd']);
    $config['margen_brl'] = floatval($_POST['margen_brl']);
    file_put_contents($fileConfig, json_encode($config));
    header("Location: admin.php");
    exit;
}

// --- GUARDAR / EDITAR TOUR ---
if (isset($_POST['add'])) {
    $nombre = $_POST['nombre'];
    $precio = $_POST['precio'];
    $rango_adulto = $_POST['rango_adulto'] ?? ''; 
    $precio_nino = $_POST['precio_nino'] ?? 0;
    $rango_nino = $_POST['rango_nino'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    
    $slugInput = !empty($_POST['slug']) ? $_POST['slug'] : $nombre;
    $cleanSlug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $slugInput)));
    $cleanSlug = trim($cleanSlug, '-');
    
    // 1. IMAGEN DE PORTADA (Principal)
    $imagenPath = '';
    if (!empty($_POST['original_slug']) && isset($tours[$_POST['original_slug']]['imagen'])) {
        $imagenPath = $tours[$_POST['original_slug']]['imagen'];
    }
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
        $uploadDir = 'img/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $filename = $cleanSlug . '-portada-' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $filename)) {
            $imagenPath = $uploadDir . $filename;
        }
    }

    // 2. GALERÍA (Múltiples fotos)
    $galeriaPaths = [];
    // Recuperar galería existente si estamos editando
    if (!empty($_POST['original_slug']) && isset($tours[$_POST['original_slug']]['galeria'])) {
        $galeriaPaths = $tours[$_POST['original_slug']]['galeria'];
    }
    // Procesar nuevas fotos de galería
    if (isset($_FILES['galeria'])) {
        $uploadDir = 'img/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $count = count($_FILES['galeria']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['galeria']['error'][$i] === 0) {
                $ext = pathinfo($_FILES['galeria']['name'][$i], PATHINFO_EXTENSION);
                // Nombre único para cada foto de la galería
                $filename = $cleanSlug . '-galeria-' . time() . '-' . $i . '.' . $ext;
                if (move_uploaded_file($_FILES['galeria']['tmp_name'][$i], $uploadDir . $filename)) {
                    $galeriaPaths[] = $uploadDir . $filename;
                }
            }
        }
    }
    // Opción para borrar galería (Checkbox simple)
    if (isset($_POST['borrar_galeria']) && $_POST['borrar_galeria'] == '1') {
        // Aquí podrías agregar unlink para borrar archivos físicos si quisieras
        $galeriaPaths = []; 
    }

    // Limpieza de slug viejo
    if (!empty($_POST['original_slug']) && $_POST['original_slug'] != $cleanSlug) {
        if(isset($tours[$_POST['original_slug']])) unset($tours[$_POST['original_slug']]);
    }

    $tours[$cleanSlug] = [
        'nombre' => $nombre, 
        'precio_cop' => $precio,
        'rango_adulto' => $rango_adulto,
        'precio_nino' => $precio_nino,
        'rango_nino' => $rango_nino,
        'descripcion' => $descripcion,
        'imagen' => $imagenPath,
        'galeria' => $galeriaPaths // Guardamos el array de fotos
    ];
    
    file_put_contents($fileTours, json_encode($tours));
    header("Location: admin.php");
    exit;
}

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
if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; }

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
        .table-responsive { border-radius: 12px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .img-preview-mini { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; }
        .gallery-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; margin-right: 2px; }
    </style>
</head>
<body class="container py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h2 class="fw-bold mb-0">Panel de Control</h2><small class="text-muted">Hola, <?= htmlspecialchars($creds['usuario']) ?></small></div>
        <div class="d-flex gap-2">
            <a href="index.php" target="_blank" class="btn btn-success btn-sm fw-bold align-self-center">Ver Web ↗</a>
            <a href="?logout=1" class="btn btn-outline-secondary btn-sm align-self-center">Salir</a>
        </div>
    </div>

    <div class="card mb-4 border-warning shadow-sm">
        <div class="card-header bg-warning text-dark fw-bold">📉 Tasa de Cambio</div>
        <div class="card-body py-2">
            <form method="post" class="row g-2 align-items-end">
                <div class="col-5"><label class="small fw-bold">Resta Dólar</label><input type="number" name="margen_usd" class="form-control form-control-sm" value="<?= $config['margen_usd'] ?>"></div>
                <div class="col-5"><label class="small fw-bold">Resta Real</label><input type="number" name="margen_brl" class="form-control form-control-sm" value="<?= $config['margen_brl'] ?>"></div>
                <div class="col-2"><button type="submit" name="save_config" class="btn btn-dark btn-sm w-100">OK</button></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span class="fw-bold"><?= $tourToEdit ? '✏️ Editando' : '➕ Nuevo Tour' ?></span>
            <?php if($tourToEdit): ?><a href="admin.php" class="btn btn-sm btn-light text-primary py-0">Cancelar</a><?php endif; ?>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3" enctype="multipart/form-data">
                <input type="hidden" name="original_slug" value="<?= $editingSlug ?>">

                <div class="col-md-6">
                    <label class="form-label small fw-bold">Nombre</label>
                    <input type="text" name="nombre" class="form-control" required value="<?= $tourToEdit ? htmlspecialchars($tourToEdit['nombre']) : '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">URL (Slug)</label>
                    <input type="text" name="slug" class="form-control bg-light text-muted" value="<?= $editingSlug ?>">
                </div>

                <div class="col-md-6 border-end">
                    <label class="form-label small fw-bold">Foto Principal (Portada)</label>
                    <input type="file" name="imagen" class="form-control" accept="image/*">
                    <?php if($tourToEdit && !empty($tourToEdit['imagen'])): ?>
                        <div class="mt-1"><img src="<?= $tourToEdit['imagen'] ?>" class="img-preview-mini"> <small class="text-muted">Actual</small></div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6 bg-light p-2 rounded">
                    <label class="form-label small fw-bold text-primary">📸 Galería (Múltiples fotos)</label>
                    <input type="file" name="galeria[]" class="form-control" accept="image/*" multiple>
                    <small class="text-muted d-block" style="font-size:0.75rem">* Selecciona varias fotos a la vez para añadir.</small>
                    
                    <?php if($tourToEdit && !empty($tourToEdit['galeria'])): ?>
                        <div class="mt-2">
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach($tourToEdit['galeria'] as $galImg): ?>
                                    <img src="<?= $galImg ?>" class="gallery-thumb">
                                <?php endforeach; ?>
                            </div>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="borrar_galeria" value="1" id="delGal">
                                <label class="form-check-label small text-danger" for="delGal">Borrar galería actual</label>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-bold">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3"><?= $tourToEdit['descripcion'] ?? '' ?></textarea>
                </div>
                
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold">Precio Adulto</label>
                    <input type="number" name="precio" class="form-control" required value="<?= $tourToEdit['precio_cop'] ?? '' ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold">Edad Adulto</label>
                    <input type="text" name="rango_adulto" class="form-control" placeholder="10+" value="<?= $tourToEdit['rango_adulto'] ?? '' ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold">Precio Niño</label>
                    <input type="number" name="precio_nino" class="form-control" value="<?= $tourToEdit['precio_nino'] ?? '' ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold">Edad Niño</label>
                    <input type="text" name="rango_nino" class="form-control" placeholder="4-9" value="<?= $tourToEdit['rango_nino'] ?? '' ?>">
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" name="add" class="btn btn-primary w-100 fw-bold"><?= $tourToEdit ? 'Actualizar' : 'Guardar' ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Img</th><th>Tour</th><th class="text-end pe-3">Acción</th></tr></thead>
            <tbody>
                <?php foreach ($tours as $slug => $tour): ?>
                <tr class="<?= $slug == $editingSlug ? 'table-warning' : '' ?>">
                    <td class="ps-3">
                        <?php if(!empty($tour['imagen'])): ?>
                            <img src="<?= $tour['imagen'] ?>" class="img-preview-mini">
                        <?php else: ?>
                            <div class="img-preview-mini bg-light d-flex align-items-center justify-content-center text-muted border">📷</div>
                        <?php endif; ?>
                        <?php if(!empty($tour['galeria'])): ?>
                            <div class="badge bg-dark rounded-pill mt-1" style="font-size:0.6rem">+<?= count($tour['galeria']) ?> fotos</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="fw-bold d-block"><?= htmlspecialchars($tour['nombre']) ?></span>
                        <small class="text-muted">$<?= number_format($tour['precio_cop']) ?></small>
                    </td>
                    <td class="text-end pe-3">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="?edit=<?= $slug ?>" class="btn btn-warning btn-sm">Editar</a>
                            <a href="?delete=<?= $slug ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Borrar?');">Borrar</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>