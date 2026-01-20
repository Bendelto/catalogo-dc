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

// 3. FUNCIÓN TRADUCCIÓN CON IA (CHATGPT)
function traducirIA($texto, $idioma) {
    $apiKey = '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'; // REEMPLAZA CON TU KEY
    if($apiKey == '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') return "Error: Configura tu API Key";
    
    $target = ($idioma == 'en') ? 'Inglés Americano' : 'Portugués de Brasil';
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    $postData = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "Eres un experto en turismo en Cartagena. Traduce el texto al $target con un tono vendedor y amable. Mantén el formato de saltos de línea."],
            ["role" => "user", "content" => $texto]
        ],
        "temperature" => 0.3
    ];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);
    
    return $result['choices'][0]['message']['content'] ?? $texto;
}

// PROCESAR PETICIÓN AJAX DE TRADUCCIÓN
if (isset($_POST['action']) && $_POST['action'] == 'translate') {
    header('Content-Type: application/json');
    $texto = $_POST['texto'];
    echo json_encode([
        'en' => traducirIA($texto, 'en'),
        'pt' => traducirIA($texto, 'pt')
    ]);
    exit;
}

// 4. DATOS
$fileTours = 'data.json';
$fileConfig = 'config.json';
$tours = file_exists($fileTours) ? json_decode(file_get_contents($fileTours), true) : [];
$config = file_exists($fileConfig) ? json_decode(file_get_contents($fileConfig), true) : ['margen_usd' => 200, 'margen_brl' => 200];

uasort($tours, function($a, $b) { 
    $nomA = $a['nombre_es'] ?? ($a['nombre'] ?? '');
    $nomB = $b['nombre_es'] ?? ($b['nombre'] ?? '');
    return strcasecmp($nomA, $nomB); 
});

if (isset($_POST['save_config'])) {
    $config['margen_usd'] = floatval($_POST['margen_usd']);
    $config['margen_brl'] = floatval($_POST['margen_brl']);
    file_put_contents($fileConfig, json_encode($config));
    header("Location: admin.php");
    exit;
}

if (isset($_POST['add'])) {
    $nombre_es = $_POST['nombre_es'] ?? 'Sin nombre';
    $slugInput = !empty($_POST['slug']) ? $_POST['slug'] : $nombre_es;
    $cleanSlug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $slugInput)));
    $cleanSlug = trim($cleanSlug, '-');
    $originalSlug = $_POST['original_slug'] ?? '';

    $datosAnteriores = [];
    if (!empty($originalSlug) && isset($tours[$originalSlug])) {
        $datosAnteriores = $tours[$originalSlug];
    }

    $galeriaActual = $datosAnteriores['galeria'] ?? [];
    if (isset($_POST['delete_imgs']) && is_array($_POST['delete_imgs'])) {
        $galeriaActual = array_values(array_diff($galeriaActual, $_POST['delete_imgs']));
    }

    $nuevosDatos = [
        'nombre_es' => $nombre_es,
        'nombre_en' => $_POST['nombre_en'] ?? $nombre_es,
        'nombre_pt' => $_POST['nombre_pt'] ?? $nombre_es,
        'precio_cop' => $_POST['precio'] ?? 0, 
        'precio_promo' => $_POST['precio_promo'] ?? 0, 
        'rango_adulto' => $_POST['rango_adulto'] ?? '',
        'precio_nino' => $_POST['precio_nino'] ?? 0,
        'rango_nino' => $_POST['rango_nino'] ?? '',
        'descripcion_es' => $_POST['descripcion_es'] ?? '',
        'descripcion_en' => $_POST['descripcion_en'] ?? '',
        'descripcion_pt' => $_POST['descripcion_pt'] ?? '',
        'incluye_es' => $_POST['incluye_es'] ?? '',
        'incluye_en' => $_POST['incluye_en'] ?? '',
        'incluye_pt' => $_POST['incluye_pt'] ?? '',
        'no_incluye_es' => $_POST['no_incluye_es'] ?? '',
        'no_incluye_en' => $_POST['no_incluye_en'] ?? '',
        'no_incluye_pt' => $_POST['no_incluye_pt'] ?? '',
        'horario_es' => $_POST['horario_es'] ?? '',
        'horario_en' => $_POST['horario_en'] ?? '',
        'horario_pt' => $_POST['horario_pt'] ?? '',
        'punto_encuentro_es' => $_POST['punto_encuentro_es'] ?? '',
        'punto_encuentro_en' => $_POST['punto_encuentro_en'] ?? '',
        'punto_encuentro_pt' => $_POST['punto_encuentro_pt'] ?? '',
        'imagen' => $datosAnteriores['imagen'] ?? '', 
        'galeria' => $galeriaActual,
        'oculto' => $datosAnteriores['oculto'] ?? false
    ];

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
        $uploadDir = 'img/';
        $filename = $cleanSlug . '-portada-' . time() . '.' . pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $filename)) $nuevosDatos['imagen'] = $uploadDir . $filename;
    }

    if (isset($_FILES['galeria'])) {
        $uploadDir = 'img/';
        for ($i = 0; $i < count($_FILES['galeria']['name']); $i++) {
            if ($_FILES['galeria']['error'][$i] === 0) {
                $filename = $cleanSlug . '-galeria-' . time() . '-' . $i . '.' . pathinfo($_FILES['galeria']['name'][$i], PATHINFO_EXTENSION);
                if (move_uploaded_file($_FILES['galeria']['tmp_name'][$i], $uploadDir . $filename)) $nuevosDatos['galeria'][] = $uploadDir . $filename;
            }
        }
    }

    if (!empty($originalSlug) && $originalSlug != $cleanSlug) unset($tours[$originalSlug]);
    $tours[$cleanSlug] = $nuevosDatos;
    
    file_put_contents($fileTours, json_encode($tours, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    header("Location: admin.php");
    exit;
}

if (isset($_GET['delete'])) {
    unset($tours[$_GET['delete']]);
    file_put_contents($fileTours, json_encode($tours, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    header("Location: admin.php");
    exit;
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin Multilenguaje</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding-bottom: 50px; }
        .nav-tabs .nav-link { font-weight: bold; color: #666; }
        .nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 3px solid #0d6efd; }
        .translate-btn { cursor: pointer; color: #0d6efd; font-size: 0.8rem; text-decoration: underline; }
        .img-preview-mini { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; }
        .gallery-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; margin: 2px; }
        .ai-loading { display: none; margin-left: 10px; font-style: italic; color: #d63384; font-size: 0.8rem; }
    </style>
</head>
<body class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Panel de Control (ML)</h2>
        <a href="?logout=1" class="btn btn-outline-secondary btn-sm">Salir</a>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span class="fw-bold"><?= $tourToEdit ? '✏️ Editando Tour' : '➕ Nuevo Tour' ?></span>
            <?php if($tourToEdit): ?><a href="admin.php" class="btn btn-sm btn-light">Cancelar</a><?php endif; ?>
        </div>
        <div class="card-body">
            <form method="post" id="tourForm" enctype="multipart/form-data">
                <input type="hidden" name="original_slug" value="<?= $editingSlug ?>">
                
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Slug (URL)</label>
                        <input type="text" name="slug" id="inputSlug" class="form-control" value="<?= $editingSlug ?>">
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold">Precio Adulto</label>
                        <input type="number" name="precio" class="form-control" required value="<?= $tourToEdit['precio_cop'] ?? '' ?>">
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold">Precio Niño</label>
                        <input type="number" name="precio_nino" class="form-control" value="<?= $tourToEdit['precio_nino'] ?? '' ?>">
                    </div>
                </div>

                <ul class="nav nav-tabs mb-3" id="langTabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-es" type="button">Español 🇪🇸</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-en" type="button">English 🇺🇸</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pt" type="button">Português 🇧🇷</button></li>
                    <li class="ms-auto"><span class="translate-btn" onclick="autoTranslateAll()"><i class="fa-solid fa-wand-sparkles"></i> Traducir todo con IA</span><span class="ai-loading" id="ai-loading">Traduciendo...</span></li>
                </ul>

                <div class="tab-content" id="langTabsContent">
                    <div class="tab-pane fade show active" id="tab-es">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label small fw-bold">Nombre</label><input type="text" name="nombre_es" id="nombre_es" class="form-control" value="<?= htmlspecialchars($tourToEdit['nombre_es'] ?? ($tourToEdit['nombre'] ?? '')) ?>"></div>
                            <div class="col-12"><label class="form-label small fw-bold">Descripción</label><textarea name="descripcion_es" id="descripcion_es" class="form-control" rows="3"><?= htmlspecialchars($tourToEdit['descripcion_es'] ?? ($tourToEdit['descripcion'] ?? '')) ?></textarea></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">Incluye</label><textarea name="incluye_es" id="incluye_es" class="form-control" rows="3"><?= htmlspecialchars($tourToEdit['incluye_es'] ?? ($tourToEdit['incluye'] ?? '')) ?></textarea></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">No Incluye</label><textarea name="no_incluye_es" id="no_incluye_es" class="form-control" rows="3"><?= htmlspecialchars($tourToEdit['no_incluye_es'] ?? ($tourToEdit['no_incluye'] ?? '')) ?></textarea></div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-en">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label small fw-bold">Nombre (EN)</label><input type="text" name="nombre_en" id="nombre_en" class="form-control" value="<?= htmlspecialchars($tourToEdit['nombre_en'] ?? '') ?>"></div>
                            <div class="col-12"><label class="form-label small fw-bold">Descripción (EN)</label><textarea name="descripcion_en" id="descripcion_en" class="form-control" rows="3"><?= htmlspecialchars($tourToEdit['descripcion_en'] ?? '') ?></textarea></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">Incluye (EN)</label><textarea name="incluye_en" id="incluye_en" class="form-control" rows="3"><?= htmlspecialchars($tourToEdit['incluye_en'] ?? '') ?></textarea></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">No Incluye (EN)</label><textarea name="no_incluye_en" id="no_incluye_en" class="form-control" rows="3"><?= htmlspecialchars($tourToEdit['no_incluye_en'] ?? '') ?></textarea></div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-pt">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label small fw-bold">Nombre (PT)</label><input type="text" name="nombre_pt" id="nombre_pt" class="form-control" value="<?= htmlspecialchars($tourToEdit['nombre_pt'] ?? '') ?>"></div>
                            <div class="col-12"><label class="form-label small fw-bold">Descripción (PT)</label><textarea name="descripcion_pt" id="descripcion_pt" class="form-control" rows="3"><?= htmlspecialchars($tourToEdit['descripcion_pt'] ?? '') ?></textarea></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">Incluye (PT)</label><textarea name="incluye_pt" id="incluye_pt" class="form-control" rows="3"><?= htmlspecialchars($tourToEdit['incluye_pt'] ?? '') ?></textarea></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">No Incluye (PT)</label><textarea name="no_incluye_pt" id="no_incluye_pt" class="form-control" rows="3"><?= htmlspecialchars($tourToEdit['no_incluye_pt'] ?? '') ?></textarea></div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6"><label class="form-label small fw-bold">Portada</label><input type="file" name="imagen" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Galería</label><input type="file" name="galeria[]" class="form-control" multiple></div>
                </div>

                <div class="mt-4"><button type="submit" name="add" class="btn btn-success w-100 fw-bold">GUARDAR TOUR</button></div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-white shadow-sm rounded">
            <thead><tr><th>Tour</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
                <?php foreach($tours as $slug => $tour): ?>
                <tr>
                    <td><?= htmlspecialchars($tour['nombre_es'] ?? ($tour['nombre'] ?? '')) ?></td>
                    <td class="text-end">
                        <a href="?edit=<?= $slug ?>" class="btn btn-sm btn-warning">Editar</a>
                        <a href="?delete=<?= $slug ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Borrar?')">X</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // TRADUCCIÓN AUTOMÁTICA AJAX
        async function autoTranslateAll() {
            const fields = ['nombre', 'descripcion', 'incluye', 'no_incluye'];
            const esValues = {};
            fields.forEach(f => esValues[f] = document.getElementById(f + '_es').value);
            
            if(!esValues['nombre']) { alert("Escribe al menos el nombre en español"); return; }
            
            document.getElementById('ai-loading').style.display = 'inline';
            
            for(const field of fields) {
                const val = esValues[field];
                if(!val) continue;
                
                const formData = new FormData();
                formData.append('action', 'translate');
                formData.append('texto', val);
                
                try {
                    const response = await fetch('admin.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    document.getElementById(field + '_en').value = data.en;
                    document.getElementById(field + '_pt').value = data.pt;
                } catch(e) { console.error(e); }
            }
            document.getElementById('ai-loading').style.display = 'none';
        }
    </script>
</body>
</html>