<?php
session_start();

// 1. CREDENCIALES
$fileCreds = 'credenciales.json';
$creds = json_decode(file_get_contents($fileCreds), true);

// 2. LOGIN
if (isset($_POST['login'])) {
    if ($_POST['user'] === $creds['usuario'] && $_POST['pass'] === $creds['password']) {
        $_SESSION['admin'] = true;
        header("Location: admin.php"); exit;
    }
}

if (!isset($_SESSION['admin'])) {
    ?>
    <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Login</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="d-flex justify-content-center align-items-center vh-100 bg-light"><form method="post" class="card p-4 shadow" style="width:350px;"><h3>🔐 Admin</h3><input type="text" name="user" class="form-control mb-3" placeholder="Usuario" required><input type="password" name="pass" class="form-control mb-3" placeholder="Contraseña" required><button name="login" class="btn btn-primary w-100">Entrar</button></form></body></html>
    <?php exit;
}

// 3. FUNCIÓN TRADUCCIÓN IA (OpenAI GPT-4o-mini)
function traducirIA($texto, $idioma) {
    $apiKey = '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';
    $target = ($idioma == 'en') ? 'Inglés Americano' : 'Portugués de Brasil';
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "model" => "gpt-4o-mini", // Aquí puedes cambiar el modelo si lo deseas
        "messages" => [
            ["role" => "system", "content" => "Eres un experto en turismo en Cartagena. Traduce al $target vendedoramente."],
            ["role" => "user", "content" => $texto]
        ],
        "temperature" => 0.3
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $res['choices'][0]['message']['content'] ?? $texto;
}

if (isset($_POST['action']) && $_POST['action'] == 'translate') {
    header('Content-Type: application/json');
    echo json_encode(['en' => traducirIA($_POST['texto'], 'en'), 'pt' => traducirIA($_POST['texto'], 'pt')]);
    exit;
}

// 4. DATOS
$fileTours = 'data.json';
$tours = json_decode(file_get_contents($fileTours), true) ?: [];

if (isset($_POST['add'])) {
    $nombre_es = $_POST['nombre_es'];
    $slugInput = !empty($_POST['slug']) ? $_POST['slug'] : $nombre_es;
    $cleanSlug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $slugInput)));
    $originalSlug = $_POST['original_slug'] ?? '';
    
    $datosAnteriores = ($originalSlug && isset($tours[$originalSlug])) ? $tours[$originalSlug] : [];
    $galeriaActual = $datosAnteriores['galeria'] ?? [];
    
    if (isset($_POST['delete_imgs'])) {
        $galeriaActual = array_values(array_diff($galeriaActual, $_POST['delete_imgs']));
    }

    $nuevosDatos = [
        'nombre_es' => $nombre_es,
        'nombre_en' => $_POST['nombre_en'],
        'nombre_pt' => $_POST['nombre_pt'],
        'precio_cop' => $_POST['precio'],
        'precio_promo' => $_POST['precio_promo'],
        'precio_nino' => $_POST['precio_nino'],
        'rango_adulto' => $_POST['rango_adulto'],
        'rango_nino' => $_POST['rango_nino'],
        'descripcion_es' => $_POST['descripcion_es'],
        'descripcion_en' => $_POST['descripcion_en'],
        'descripcion_pt' => $_POST['descripcion_pt'],
        'incluye_es' => $_POST['incluye_es'],
        'incluye_en' => $_POST['incluye_en'],
        'incluye_pt' => $_POST['incluye_pt'],
        'no_incluye_es' => $_POST['no_incluye_es'],
        'no_incluye_en' => $_POST['no_incluye_en'],
        'no_incluye_pt' => $_POST['no_incluye_pt'],
        'horario_es' => $_POST['horario_es'],
        'horario_en' => $_POST['horario_en'],
        'horario_pt' => $_POST['horario_pt'],
        'punto_encuentro_es' => $_POST['punto_encuentro_es'],
        'punto_encuentro_en' => $_POST['punto_encuentro_en'],
        'punto_encuentro_pt' => $_POST['punto_encuentro_pt'],
        'imagen' => $datosAnteriores['imagen'] ?? '',
        'galeria' => $galeriaActual,
        'oculto' => $datosAnteriores['oculto'] ?? false
    ];

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
        $filename = 'img/'.$cleanSlug.'-portada-'.time().'.jpg';
        move_uploaded_file($_FILES['imagen']['tmp_name'], $filename);
        $nuevosDatos['imagen'] = $filename;
    }

    if (isset($_FILES['galeria'])) {
        foreach ($_FILES['galeria']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['galeria']['error'][$key] === 0) {
                $filename = 'img/'.$cleanSlug.'-gal-'.time().'-'.$key.'.jpg';
                move_uploaded_file($tmp_name, $filename);
                $nuevosDatos['galeria'][] = $filename;
            }
        }
    }

    if ($originalSlug && $originalSlug != $cleanSlug) unset($tours[$originalSlug]);
    $tours[$cleanSlug] = $nuevosDatos;
    file_put_contents($fileTours, json_encode($tours, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    header("Location: admin.php"); exit;
}

$tourToEdit = (isset($_GET['edit'])) ? $tours[$_GET['edit']] : null;
$editingSlug = $_GET['edit'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin Multi-lenguaje IA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .gallery-thumb { width: 80px; height: 80px; object-fit: cover; margin: 5px; border-radius: 5px; }
        .ai-btn { cursor: pointer; color: #0d6efd; font-weight: bold; }
    </style>
</head>
<body class="container py-5">
    <h2>Gestión de Tours Multi-lenguaje (GPT-4o-mini)</h2>
    <form method="post" enctype="multipart/form-data" class="card p-4 shadow-sm mb-5">
        <input type="hidden" name="original_slug" value="<?= $editingSlug ?>">
        <div class="row g-3">
            <div class="col-md-6"><label>Slug URL</label><input type="text" name="slug" class="form-control" value="<?= $editingSlug ?>"></div>
            <div class="col-md-3"><label>Precio Adulto</label><input type="number" name="precio" class="form-control" value="<?= $tourToEdit['precio_cop'] ?? '' ?>"></div>
            <div class="col-md-3"><label>Precio Niño</label><input type="number" name="precio_nino" class="form-control" value="<?= $tourToEdit['precio_nino'] ?? '' ?>"></div>
            
            <div class="col-12">
                <ul class="nav nav-tabs" id="langTabs">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#es">Español 🇪🇸</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#en">Inglés 🇺🇸</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pt">Portugués 🇧🇷</a></li>
                </ul>
                <div class="tab-content border p-3 bg-white">
                    <div id="es" class="tab-pane fade show active">
                        <label>Nombre</label><input type="text" name="nombre_es" id="nombre_es" class="form-control mb-2" value="<?= $tourToEdit['nombre_es'] ?? ($tourToEdit['nombre'] ?? '') ?>">
                        <label>Descripción</label><textarea name="descripcion_es" id="descripcion_es" class="form-control mb-2" rows="3"><?= $tourToEdit['descripcion_es'] ?? ($tourToEdit['descripcion'] ?? '') ?></textarea>
                        <div class="row"><div class="col-6"><label>Incluye</label><textarea name="incluye_es" id="incluye_es" class="form-control" rows="4"><?= $tourToEdit['incluye_es'] ?? ($tourToEdit['incluye'] ?? '') ?></textarea></div><div class="col-6"><label>No Incluye</label><textarea name="no_incluye_es" id="no_incluye_es" class="form-control" rows="4"><?= $tourToEdit['no_incluye_es'] ?? ($tourToEdit['no_incluye'] ?? '') ?></textarea></div></div>
                        <div class="row mt-2"><div class="col-6"><label>Horario</label><input type="text" name="horario_es" class="form-control" value="<?= $tourToEdit['horario_es'] ?? ($tourToEdit['horario'] ?? '') ?>"></div><div class="col-6"><label>Punto Encuentro</label><input type="text" name="punto_encuentro_es" class="form-control" value="<?= $tourToEdit['punto_encuentro_es'] ?? ($tourToEdit['punto_encuentro'] ?? '') ?>"></div></div>
                    </div>
                    <div id="en" class="tab-pane fade">
                        <label>Nombre EN</label><input type="text" name="nombre_en" id="nombre_en" class="form-control mb-2" value="<?= $tourToEdit['nombre_en'] ?? '' ?>">
                        <label>Descripción EN</label><textarea name="descripcion_en" id="descripcion_en" class="form-control mb-2" rows="3"><?= $tourToEdit['descripcion_en'] ?? '' ?></textarea>
                        <div class="row"><div class="col-6"><label>Includes</label><textarea name="incluye_en" id="incluye_en" class="form-control" rows="4"><?= $tourToEdit['incluye_en'] ?? '' ?></textarea></div><div class="col-6"><label>Not Includes</label><textarea name="no_incluye_en" id="no_incluye_en" class="form-control" rows="4"><?= $tourToEdit['no_incluye_en'] ?? '' ?></textarea></div></div>
                    </div>
                    <div id="pt" class="tab-pane fade">
                        <label>Nombre PT</label><input type="text" name="nombre_pt" id="nombre_pt" class="form-control mb-2" value="<?= $tourToEdit['nombre_pt'] ?? '' ?>">
                        <label>Descrição PT</label><textarea name="descripcion_pt" id="descripcion_pt" class="form-control mb-2" rows="3"><?= $tourToEdit['descripcion_pt'] ?? '' ?></textarea>
                        <div class="row"><div class="col-6"><label>Inclui</label><textarea name="incluye_pt" id="incluye_pt" class="form-control" rows="4"><?= $tourToEdit['incluye_pt'] ?? '' ?></textarea></div><div class="col-6"><label>Não Inclui</label><textarea name="no_incluye_pt" id="no_incluye_pt" class="form-control" rows="4"><?= $tourToEdit['no_incluye_pt'] ?? '' ?></textarea></div></div>
                    </div>
                </div>
                <div class="mt-2"><span class="ai-btn" onclick="traducirTodo()">✨ Traducir campos vacíos con IA</span></div>
            </div>

            <div class="col-md-6"><label>Edades Adulto</label><input type="text" name="rango_adulto" class="form-control" value="<?= $tourToEdit['rango_adulto'] ?? '' ?>"></div>
            <div class="col-md-6"><label>Edades Niño</label><input type="text" name="rango_nino" class="form-control" value="<?= $tourToEdit['rango_nino'] ?? '' ?>"></div>

            <div class="col-md-6"><label>Portada</label><input type="file" name="imagen" class="form-control"></div>
            <div class="col-md-6"><label>Galería (Múltiple)</label><input type="file" name="galeria[]" class="form-control" multiple></div>
            
            <?php if (!empty($tourToEdit['galeria'])): ?>
            <div class="col-12">
                <h6>Galería Actual (Selecciona para borrar):</h6>
                <?php foreach ($tourToEdit['galeria'] as $img): ?>
                    <label><img src="<?= $img ?>" class="gallery-thumb"><input type="checkbox" name="delete_imgs[]" value="<?= $img ?>"></label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="col-12"><button type="submit" name="add" class="btn btn-success w-100">Guardar Tour</button></div>
        </div>
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        async function traducirTodo() {
            const campos = ['nombre', 'descripcion', 'incluye', 'no_incluye'];
            for (let c of campos) {
                let txt = document.getElementById(c+'_es').value;
                if (!txt) continue;
                let res = await fetch('admin.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=translate&texto=${encodeURIComponent(txt)}`
                });
                let data = await res.json();
                if (!document.getElementById(c+'_en').value) document.getElementById(c+'_en').value = data.en;
                if (!document.getElementById(c+'_pt').value) document.getElementById(c+'_pt').value = data.pt;
            }
        }
    </script>
</body>
</html>