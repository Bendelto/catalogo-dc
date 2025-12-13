<?php
session_start();

// 1. CREDENCIALES
$fileCreds = 'credenciales.json';
if (!file_exists($fileCreds)) {
    $defaultCreds = ['usuario' => 'admin', 'password' => 'Dc@6691400'];
    file_put_contents($fileCreds, json_encode($defaultCreds));
}
$creds = json_decode(file_get_contents($fileCreds), true);

// 2. LOGIN
$errorMsg = '';
if (isset($_POST['login'])) {
    $userInput = $_POST['user'] ?? '';
    $passInput = $_POST['pass'] ?? '';
    if ($userInput === $creds['usuario'] && $passInput === $creds['password']) {
        $_SESSION['admin'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $errorMsg = 'Datos incorrectos';
    }
}

if (!isset($_SESSION['admin'])) {
    ?>
    <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Login</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="d-flex justify-content-center align-items-center vh-100 px-3 bg-light"><form method="post" class="card p-4 shadow" style="max-width:400px;width:100%"><h3 class="text-center mb-3">🔐 Admin</h3><?php if($errorMsg): ?><div class="alert alert-danger py-1"><?= $errorMsg ?></div><?php endif; ?><input type="text" name="user" class="form-control mb-3" placeholder="Usuario" required autofocus><input type="password" name="pass" class="form-control mb-3" placeholder="Contraseña" required><button name="login" class="btn btn-primary w-100">Entrar</button></form></body></html>
    <?php exit;
}

// 3. DATOS Y BACKUP
$fileTours = 'data.json';
$fileConfig = 'config.json';

// BACKUP
if (isset($_GET['backup'])) {
    if (file_exists($fileTours)) {
        $jsonData = file_get_contents($fileTours);
        $fecha = date('Y-m-d_H-i');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="backup_'.$fecha.'.json"');
        echo $jsonData;
        exit;
    }
}

$tours = file_exists($fileTours) ? json_decode(file_get_contents($fileTours), true) : [];
$config = file_exists($fileConfig) ? json_decode(file_get_contents($fileConfig), true) : ['margen_usd' => 200, 'margen_brl' => 200];

uasort($tours, function($a, $b) { return strcasecmp($a['nombre'], $b['nombre']); });

// GUARDAR CONFIG
if (isset($_POST['save_config'])) {
    $config['margen_usd'] = floatval($_POST['margen_usd']);
    $config['margen_brl'] = floatval($_POST['margen_brl']);
    file_put_contents($fileConfig, json_encode($config));
    header("Location: admin.php");
    exit;
}

// OCULTAR / MOSTRAR
if (isset($_GET['toggle_hide'])) {
    $slugTarget = $_GET['toggle_hide'];
    if (isset($tours[$slugTarget])) {
        $estadoActual = $tours[$slugTarget]['oculto'] ?? false;
        $tours[$slugTarget]['oculto'] = !$estadoActual;
        file_put_contents($fileTours, json_encode($tours, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    header("Location: admin.php");
    exit;
}

// ==========================================
//      LÓGICA DE GUARDADO (ADD/EDIT)
// ==========================================
if (isset($_POST['add'])) {
    $nombre = $_POST['nombre'] ?? 'Sin nombre';
    $slugInput = !empty($_POST['slug']) ? $_POST['slug'] : $nombre;
    $cleanSlug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $slugInput)));
    $cleanSlug = trim($cleanSlug, '-');
    $originalSlug = $_POST['original_slug'] ?? '';

    // RECUPERAR DATOS ANTERIORES
    $datosAnteriores = [];
    if (!empty($originalSlug) && isset($tours[$originalSlug])) {
        $datosAnteriores = $tours[$originalSlug];
    } elseif (isset($tours[$cleanSlug])) {
        $datosAnteriores = $tours[$cleanSlug];
    }

    // GESTIÓN DE GALERÍA (Borrado Individual)
    $galeriaActual = $datosAnteriores['galeria'] ?? [];
    
    // Si se marcaron fotos para borrar, las quitamos del array
    if (isset($_POST['delete_imgs']) && is_array($_POST['delete_imgs'])) {
        $galeriaActual = array_diff($galeriaActual, $_POST['delete_imgs']);
        $galeriaActual = array_values($galeriaActual); // Reindexar para evitar huecos en el JSON
    }

    // PREPARAR DATOS NUEVOS
    $nuevosDatos = [
        'nombre' => $nombre,
        'precio_cop' => $_POST['precio'] ?? 0,
        'precio_antes' => $_POST['precio_antes'] ?? 0, // NUEVO: PRECIO OFERTA
        'rango_adulto' => $_POST['rango_adulto'] ?? '',
        'precio_nino' => $_POST['precio_nino'] ?? 0,
        'rango_nino' => $_POST['rango_nino'] ?? '',
        'descripcion' => $_POST['descripcion'] ?? '',
        'incluye' => $_POST['incluye'] ?? '',
        'no_incluye' => $_POST['no_incluye'] ?? '',
        'horario' => $_POST['horario'] ?? '',
        'punto_encuentro' => $_POST['punto_encuentro'] ?? '',
        'imagen' => $datosAnteriores['imagen'] ?? '', 
        'galeria' => $galeriaActual, // Usamos la galería filtrada
        'oculto' => $datosAnteriores['oculto'] ?? false
    ];

    // PROCESAR PORTADA
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
        $uploadDir = 'img/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $filename = $cleanSlug . '-portada-' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $filename)) {
            $nuevosDatos['imagen'] = $uploadDir . $filename;
        }
    }

    // PROCESAR GALERÍA (NUEVAS FOTOS)
    if (isset($_FILES['galeria'])) {
        $uploadDir = 'img/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $count = count($_FILES['galeria']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['galeria']['error'][$i] === 0) {
                $ext = pathinfo($_FILES['galeria']['name'][$i], PATHINFO_EXTENSION);
                $filename = $cleanSlug . '-galeria-' . time() . '-' . $i . '.' . $ext;
                if (move_uploaded_file($_FILES['galeria']['tmp_name'][$i], $uploadDir . $filename)) {
                    $nuevosDatos['galeria'][] = $uploadDir . $filename;
                }
            }
        }
    }

    // Eliminar entrada vieja si cambió el slug
    if (!empty($originalSlug) && $originalSlug != $cleanSlug) {
        if(isset($tours[$originalSlug])) unset($tours[$originalSlug]);
    }
    
    // FUSIÓN Y GUARDADO
    $tours[$cleanSlug] = array_merge($datosAnteriores, $nuevosDatos);
    
    file_put_contents($fileTours, json_encode($tours, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    header("Location: admin.php");
    exit;
}

// BORRAR
if (isset($_GET['delete'])) {
    $slugToDelete = $_GET['delete'];
    if(isset($tours[$slugToDelete])) {
        unset($tours[$slugToDelete]);
        file_put_contents($fileTours, json_encode($tours, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    header("Location: admin.php");
    exit;
}

if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; }

// CARGAR PARA EDITAR
$tourToEdit = null;
$editingSlug = '';
if (isset($_GET['edit']) && isset($tours[$_GET['edit']])) {
    $d = $tours[$_GET['edit']];
    $editingSlug = $_GET['edit'];
    
    $tourToEdit = [
        'nombre' => $d['nombre'] ?? '',
        'precio_cop' => $d['precio_cop'] ?? '',
        'precio_antes' => $d['precio_antes'] ?? '', // Cargar precio oferta
        'rango_adulto' => $d['rango_adulto'] ?? '',
        'precio_nino' => $d['precio_nino'] ?? '',
        'rango_nino' => $d['rango_nino'] ?? '',
        'descripcion' => $d['descripcion'] ?? ($d['description'] ?? ''),
        'incluye' => $d['incluye'] ?? ($d['include'] ?? ''),
        'no_incluye' => $d['no_incluye'] ?? ($d['not_include'] ?? ''),
        'horario' => $d['horario'] ?? ($d['schedule'] ?? ''),
        'punto_encuentro' => $d['punto_encuentro'] ?? ($d['meeting_point'] ?? ''),
        'imagen' => $d['imagen'] ?? '',
        'galeria' => $d['galeria'] ?? []
    ];
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
        .img-preview-mini { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; }
        .gallery-thumb-container { display: inline-block; margin: 5px; text-align: center; background: white; padding: 5px; border-radius: 6px; border: 1px solid #ddd; }
        .gallery-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; display: block; margin-bottom: 3px; }
        .btn-group-action { display: flex; gap: 5px; justify-content: flex-end; }
        @media (max-width: 576px) { .btn-group-action { flex-direction: column; } .btn-group-action .btn { width: 100%; } }
        .row-hidden { background-color: #e9ecef; opacity: 0.75; }
        .row-hidden td { color: #6c757d; }
    </style>
</head>
<body class="container py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h2 class="fw-bold mb-0">Panel de Control</h2></div>
        <div class="d-flex gap-2">
            <a href="?backup=1" class="btn btn-success btn-sm fw-bold">Backup</a>
            <a href="index.php" target="_blank" class="btn btn-outline-primary btn-sm fw-bold">Web</a>
            <a href="?logout=1" class="btn btn-outline-secondary btn-sm">Salir</a>
        </div>
    </div>

    <div class="card mb-4 border-warning shadow-sm">
        <div class="card-header bg-warning text-dark fw-bold">Tasa</div>
        <div class="card-body py-2">
            <form method="post" class="row g-2 align-items-end">
                <div class="col-5"><label class="small fw-bold">-$ Dólar</label><input type="number" name="margen_usd" class="form-control form-control-sm" value="<?= $config['margen_usd'] ?>"></div>
                <div class="col-5"><label class="small fw-bold">-$ Real</label><input type="number" name="margen_brl" class="form-control form-control-sm" value="<?= $config['margen_brl'] ?>"></div>
                <div class="col-2"><button type="submit" name="save_config" class="btn btn-dark btn-sm w-100">OK</button></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white">
            <span class="fw-bold"><?= $tourToEdit ? '✏️ Editando' : '➕ Nuevo' ?></span>
            <?php if($tourToEdit): ?><a href="admin.php" class="btn btn-sm btn-light float-end py-0">Cancelar</a><?php endif; ?>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3" enctype="multipart/form-data">
                <input type="hidden" name="original_slug" value="<?= $editingSlug ?>">

                <div class="col-md-6">
                    <label class="form-label small fw-bold">Nombre</label>
                    <input type="text" name="nombre" id="inputNombre" class="form-control" required value="<?= htmlspecialchars($tourToEdit['nombre'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Slug</label>
                    <input type="text" name="slug" id="inputSlug" class="form-control bg-light text-muted" value="<?= $editingSlug ?>">
                </div>

                <div class="col-md-6 border-end">
                    <label class="form-label small fw-bold">Portada</label>
                    <input type="file" name="imagen" class="form-control" accept="image/*">
                    <?php if(!empty($tourToEdit['imagen'])): ?>
                        <div class="mt-1"><img src="<?= $tourToEdit['imagen'] ?>" class="img-preview-mini"> <small class="text-success">Guardada</small></div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-primary">Galería</label>
                    <input type="file" name="galeria[]" class="form-control" accept="image/*" multiple>
                    <div class="mt-2">
                        <?php if(!empty($tourToEdit['galeria'])): ?>
                            <small class="d-block text-muted mb-1">Selecciona para borrar:</small>
                            <?php foreach($tourToEdit['galeria'] as $g): ?>
                                <div class="gallery-thumb-container">
                                    <img src="<?= $g ?>" class="gallery-thumb">
                                    <input type="checkbox" name="delete_imgs[]" value="<?= $g ?>" title="Borrar esta foto"> 🗑️
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12 mt-3"><h6 class="text-primary border-bottom pb-1 small fw-bold">Información</h6></div>
                
                <div class="col-12">
                    <label class="small fw-bold">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($tourToEdit['descripcion'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="small fw-bold text-success">Incluye</label>
                    <textarea name="incluye" class="form-control bg-success bg-opacity-10" rows="4"><?= htmlspecialchars($tourToEdit['incluye'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="small fw-bold text-danger">No Incluye</label>
                    <textarea name="no_incluye" class="form-control bg-danger bg-opacity-10" rows="4"><?= htmlspecialchars($tourToEdit['no_incluye'] ?? '') ?></textarea>
                </div>
                
                <div class="col-md-6">
                    <label class="small fw-bold">Horario</label>
                    <textarea name="horario" class="form-control" rows="2"><?= htmlspecialchars($tourToEdit['horario'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="small fw-bold">Punto Encuentro</label>
                    <textarea name="punto_encuentro" class="form-control" rows="2"><?= htmlspecialchars($tourToEdit['punto_encuentro'] ?? '') ?></textarea>
                </div>

                <div class="col-12 mt-3"><h6 class="text-primary border-bottom pb-1 small fw-bold">Precios</h6></div>
                
                <div class="col-6 col-md-3">
                    <label class="small fw-bold text-success">Precio Actual</label>
                    <input type="number" name="precio" class="form-control border-success" required value="<?= $tourToEdit['precio_cop'] ?? '' ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="small fw-bold text-muted">Precio Anterior (Opcional)</label>
                    <input type="number" name="precio_antes" class="form-control bg-light" placeholder="Ej: 250000" value="<?= $tourToEdit['precio_antes'] ?? '' ?>">
                    <small style="font-size:0.65rem" class="text-muted">Si llenas esto, sale tachado.</small>
                </div>
                <div class="col-6 col-md-3"><label class="small fw-bold">Edad Ad.</label><input type="text" name="rango_adulto" class="form-control" value="<?= htmlspecialchars($tourToEdit['rango_adulto'] ?? '') ?>"></div>
                
                <div class="col-12 d-block d-md-none mb-2"></div> <div class="col-6 col-md-3"><label class="small fw-bold">COP Niño</label><input type="number" name="precio_nino" class="form-control" value="<?= $tourToEdit['precio_nino'] ?? '' ?>"></div>
                <div class="col-6 col-md-3"><label class="small fw-bold">Edad Ni.</label><input type="text" name="rango_nino" class="form-control" value="<?= htmlspecialchars($tourToEdit['rango_nino'] ?? '') ?>"></div>

                <div class="col-12 mt-4"><button type="submit" name="add" class="btn btn-primary w-100 fw-bold">Guardar Cambios</button></div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Tour</th><th class="text-end pe-3">Acción</th></tr></thead>
            <tbody>
                <?php foreach ($tours as $slug => $tour): 
                    $estaOculto = isset($tour['oculto']) && $tour['oculto'] == true;
                ?>
                <tr class="<?= $slug == $editingSlug ? 'table-warning' : '' ?> <?= $estaOculto ? 'row-hidden' : '' ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center">
                            <?php if($estaOculto): ?><span class="badge bg-secondary me-2">Oculto</span><?php endif; ?>
                            <span class="fw-bold d-block text-truncate" style="max-width: 200px;"><?= htmlspecialchars($tour['nombre']) ?></span>
                        </div>
                        <small class="text-muted">$<?= number_format($tour['precio_cop']) ?></small>
                        <?php if(!empty($tour['precio_antes']) && $tour['precio_antes'] > $tour['precio_cop']): ?>
                            <span class="badge bg-danger" style="font-size:0.6rem">OFERTA</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-3">
                        <div class="btn-group-action">
                            <?php if($estaOculto): ?>
                                <a href="?toggle_hide=<?= $slug ?>" class="btn btn-success btn-sm text-white">Mostrar</a>
                            <?php else: ?>
                                <a href="?toggle_hide=<?= $slug ?>" class="btn btn-secondary btn-sm text-white">Ocultar</a>
                            <?php endif; ?>
                            <a href="?edit=<?= $slug ?>" class="btn btn-warning btn-sm text-dark">Editar</a>
                            <a href="?delete=<?= $slug ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Borrar este tour permanentemente?');">Borrar</a>
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

        if (inputNombre && inputSlug) {
            let slugManual = false;
            if (inputSlug.value.trim() !== '') { slugManual = true; }

            inputNombre.addEventListener('input', function () {
                if (!slugManual || inputSlug.value === '') {
                    let texto = this.value.trim();
                    let slug = texto.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').replace(/-+/g, '-');
                    inputSlug.value = slug;
                }
            });
            inputSlug.addEventListener('input', function() { slugManual = true; });
        }
    </script>
</body>
</html>