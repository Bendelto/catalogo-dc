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

// 3. GESTIÓN DE DATOS
$fileTours = 'data.json';
$fileConfig = 'config.json';

// BACKUP
if (isset($_GET['backup'])) {
    if (file_exists($fileTours)) {
        $jsonData = file_get_contents($fileTours);
        $fecha = date('Y-m-d_H-i');
        header('Content-Description: File Transfer');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="backup_tours_'.$fecha.'.json"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($jsonData));
        echo $jsonData;
        exit;
    }
}

$tours = file_exists($fileTours) ? json_decode(file_get_contents($fileTours), true) : [];
$config = file_exists($fileConfig) ? json_decode(file_get_contents($fileConfig), true) : ['margen_usd' => 200, 'margen_brl' => 200];

uasort($tours, function($a, $b) { return strcasecmp($a['nombre'], $b['nombre']); });

// GUARDAR CONFIGURACIÓN
if (isset($_POST['save_config'])) {
    $config['margen_usd'] = floatval($_POST['margen_usd']);
    $config['margen_brl'] = floatval($_POST['margen_brl']);
    file_put_contents($fileConfig, json_encode($config));
    header("Location: admin.php");
    exit;
}

// ==========================================
//      LÓGICA DE GUARDADO (ADD/EDIT)
// ==========================================
if (isset($_POST['add'])) {
    // Recibir variables asegurando que no sean null
    $nombre = $_POST['nombre'] ?? 'Sin nombre';
    $precio = $_POST['precio'] ?? 0;
    $rango_adulto = $_POST['rango_adulto'] ?? ''; 
    $precio_nino = $_POST['precio_nino'] ?? 0;
    $rango_nino = $_POST['rango_nino'] ?? '';
    
    // TEXTOS LARGOS (Aquí estaba el posible error de guardado)
    $descripcion = isset($_POST['descripcion']) ? $_POST['descripcion'] : '';
    $incluye = isset($_POST['incluye']) ? $_POST['incluye'] : '';
    $no_incluye = isset($_POST['no_incluye']) ? $_POST['no_incluye'] : '';
    $horario = isset($_POST['horario']) ? $_POST['horario'] : '';
    $punto_encuentro = isset($_POST['punto_encuentro']) ? $_POST['punto_encuentro'] : '';
    
    // Slug
    $slugInput = !empty($_POST['slug']) ? $_POST['slug'] : $nombre;
    $cleanSlug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $slugInput)));
    $cleanSlug = trim($cleanSlug, '-');
    $originalSlug = $_POST['original_slug'] ?? '';
    
    // 1. IMAGEN PORTADA (Conservar anterior si no hay nueva)
    $imagenPath = ''; 
    if (!empty($originalSlug) && isset($tours[$originalSlug]['imagen'])) {
        $imagenPath = $tours[$originalSlug]['imagen'];
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

    // 2. GALERÍA (Sumar fotos)
    $galeriaPaths = [];
    if (!empty($originalSlug) && isset($tours[$originalSlug]['galeria'])) {
        $galeriaPaths = $tours[$originalSlug]['galeria'];
    }
    if (isset($_FILES['galeria'])) {
        $uploadDir = 'img/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $count = count($_FILES['galeria']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['galeria']['error'][$i] === 0) {
                $ext = pathinfo($_FILES['galeria']['name'][$i], PATHINFO_EXTENSION);
                $filename = $cleanSlug . '-galeria-' . time() . '-' . $i . '.' . $ext;
                if (move_uploaded_file($_FILES['galeria']['tmp_name'][$i], $uploadDir . $filename)) {
                    $galeriaPaths[] = $uploadDir . $filename;
                }
            }
        }
    }
    if (isset($_POST['borrar_galeria']) && $_POST['borrar_galeria'] == '1') {
        $galeriaPaths = [];
    }

    // 3. Limpiar slug viejo si cambió
    if (!empty($originalSlug) && $originalSlug != $cleanSlug) {
        if(isset($tours[$originalSlug])) unset($tours[$originalSlug]);
    }

    // 4. CONSTRUIR ARRAY
    $tours[$cleanSlug] = [
        'nombre' => $nombre, 
        'precio_cop' => $precio,
        'rango_adulto' => $rango_adulto,
        'precio_nino' => $precio_nino,
        'rango_nino' => $rango_nino,
        'descripcion' => $descripcion, 
        'incluye' => $incluye,
        'no_incluye' => $no_incluye,
        'horario' => $horario,             
        'punto_encuentro' => $punto_encuentro, 
        'imagen' => $imagenPath,       
        'galeria' => $galeriaPaths     
    ];
    
    // 5. GUARDAR (Con soporte UNICODE para tildes/ñ)
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

// SALIR
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
            <a href="?backup=1" class="btn btn-success btn-sm fw-bold align-self-center">⬇ Backup</a>
            <a href="index.php" target="_blank" class="btn btn-outline-primary btn-sm fw-bold align-self-center">Ver Web ↗</a>
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
            <span class="fw-bold"><?= $tourToEdit ? '✏️ Editando: '.htmlspecialchars($tourToEdit['nombre']) : '➕ Nuevo Tour' ?></span>
            <?php if($tourToEdit): ?><a href="admin.php" class="btn btn-sm btn-light text-primary py-0">Cancelar</a><?php endif; ?>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3" enctype="multipart/form-data">
                <input type="hidden" name="original_slug" value="<?= $editingSlug ?>">

                <div class="col-md-6">
                    <label class="form-label small fw-bold">Nombre</label>
                    <input type="text" name="nombre" id="inputNombre" class="form-control" required value="<?= $tourToEdit ? htmlspecialchars($tourToEdit['nombre']) : '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">URL (Slug)</label>
                    <input type="text" name="slug" id="inputSlug" class="form-control bg-light text-muted" value="<?= $editingSlug ?>">
                </div>

                <div class="col-md-6 border-end">
                    <label class="form-label small fw-bold">Foto Portada</label>
                    <input type="file" name="imagen" class="form-control" accept="image/*">
                    <?php if($tourToEdit && !empty($tourToEdit['imagen'])): ?>
                        <div class="mt-2 p-2 bg-light rounded border">
                            <img src="<?= $tourToEdit['imagen'] ?>" class="img-preview-mini"> <small class="text-muted">Guardada</small>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-primary">📸 Galería (Sumar)</label>
                    <input type="file" name="galeria[]" class="form-control" accept="image/*" multiple>
                    
                    <?php if($tourToEdit && !empty($tourToEdit['galeria'])): ?>
                        <div class="mt-2 p-2 bg-light rounded border">
                            <div class="d-flex flex-wrap gap-1 mb-1">
                                <?php foreach($tourToEdit['galeria'] as $galImg): ?>
                                    <img src="<?= $galImg ?>" class="gallery-thumb">
                                <?php endforeach; ?>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="borrar_galeria" value="1" id="delGal">
                                <label class="form-check-label small text-danger fw-bold" for="delGal">Borrar galería</label>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-12 mt-3"><h6 class="text-primary border-bottom pb-1 small text-uppercase fw-bold">Detalles (Fijos)</h6></div>

                <div class="col-12">
                    <label class="form-label small fw-bold">Descripción General</label>
                    <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($tourToEdit['descripcion'] ?? '') ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-bold text-success">✅ Incluye (1 por línea)</label>
                    <textarea name="incluye" class="form-control bg-success bg-opacity-10" rows="5"><?= htmlspecialchars($tourToEdit['incluye'] ?? '') ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-bold text-danger">❌ No Incluye (1 por línea)</label>
                    <textarea name="no_incluye" class="form-control bg-danger bg-opacity-10" rows="5"><?= htmlspecialchars($tourToEdit['no_incluye'] ?? '') ?></textarea>
                </div>

                <div class="col-12 mt-3"><h6 class="text-primary border-bottom pb-1 small text-uppercase fw-bold">Logística (Acordeones)</h6></div>
                
                <div class="col-md-6">
                    <label class="form-label small fw-bold">🕒 Horarios / Itinerario</label>
                    <textarea name="horario" class="form-control" rows="3" placeholder="Ej: Salida 8:00 AM..."><?= htmlspecialchars($tourToEdit['horario'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">📍 Punto de Encuentro</label>
                    <textarea name="punto_encuentro" class="form-control" rows="3" placeholder="Ej: Muelle de la Bodeguita..."><?= htmlspecialchars($tourToEdit['punto_encuentro'] ?? '') ?></textarea>
                </div>
                
                <div class="col-12 mt-3"><h6 class="text-primary border-bottom pb-1 small text-uppercase fw-bold">Precios</h6></div>
                <div class="col-6 col-md-3"><label class="form-label small fw-bold">Precio Adulto</label><input type="number" name="precio" class="form-control" required value="<?= $tourToEdit['precio_cop'] ?? '' ?>"></div>
                <div class="col-6 col-md-3"><label class="form-label small fw-bold">Edad Adulto</label><input type="text" name="rango_adulto" class="form-control" value="<?= $tourToEdit['rango_adulto'] ?? '' ?>"></div>
                <div class="col-6 col-md-3"><label class="form-label small fw-bold">Precio Niño</label><input type="number" name="precio_nino" class="form-control" value="<?= $tourToEdit['precio_nino'] ?? '' ?>"></div>
                <div class="col-6 col-md-3"><label class="form-label small fw-bold">Edad Niño</label><input type="text" name="rango_nino" class="form-control" value="<?= $tourToEdit['rango_nino'] ?? '' ?>"></div>

                <div class="col-12 mt-4">
                    <button type="submit" name="add" class="btn btn-primary w-100 fw-bold py-2"><?= $tourToEdit ? '💾 Guardar Cambios' : '➕ Crear Tour' ?></button>
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
                        <?php if(!empty($tour['imagen'])): ?><img src="<?= $tour['imagen'] ?>" class="img-preview-mini"><?php else: ?><div class="img-preview-mini bg-light border d-flex align-items-center justify-content-center">📷</div><?php endif; ?>
                    </td>
                    <td>
                        <span class="fw-bold d-block"><?= htmlspecialchars($tour['nombre']) ?></span>
                        <small class="text-muted">$<?= number_format($tour['precio_cop']) ?></small>
                    </td>
                    <td class="text-end pe-3">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="?edit=<?= $slug ?>" class="btn btn-warning btn-sm text-dark">Editar</a>
                            <a href="?delete=<?= $slug ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Borrar?');">Borrar</a>
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