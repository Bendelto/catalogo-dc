<?php
// 1. DETECTAR IDIOMA
$lang = $_GET['lang'] ?? 'es';
$dicFile = 'lang.json';
$dicArr = json_decode(file_get_contents($dicFile), true);
$dic = $dicArr[$lang] ?? $dicArr['es'];

// CONFIGURACIÓN Y MONEDA
$fileConfig = 'config.json';
$config = file_exists($fileConfig) ? json_decode(file_get_contents($fileConfig), true) : ['margen_usd' => 200, 'margen_brl' => 200];
$margen_usd = $config['margen_usd']; $margen_brl = $config['margen_brl'];

$cacheFile = 'tasa.json';
if (!file_exists($cacheFile) || (time() - filemtime($cacheFile)) > 43200) {
    $response = @file_get_contents("https://open.er-api.com/v6/latest/COP");
    if($response) file_put_contents($cacheFile, $response);
}
$rates = json_decode(file_get_contents($cacheFile), true);
$tasa_tuya_usd = (1 / $rates['rates']['USD']) - $margen_usd;
$tasa_tuya_brl = (1 / $rates['rates']['BRL']) - $margen_brl;

function precio_inteligente($valor) { return (float)(ceil($valor * 2) / 2); }

// 3. DATOS
$tours = file_exists('data.json') ? json_decode(file_get_contents('data.json'), true) : [];

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = dirname($_SERVER['SCRIPT_NAME']);
if($base_path == '/') $base_path = '';
$slug_solicitado = trim(str_replace($base_path, '', $request_uri), '/');

$singleTour = null;
if (!empty($slug_solicitado) && isset($tours[$slug_solicitado])) {
    if (empty($tours[$slug_solicitado]['oculto']) || $tours[$slug_solicitado]['oculto'] == false) {
        $singleTour = $tours[$slug_solicitado];
    }
}

// 4. VARIABLES DE VISTA
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

if ($singleTour) {
    $nombre = $singleTour["nombre_$lang"] ?? ($singleTour['nombre_es'] ?? ($singleTour['nombre'] ?? ''));
    $desc = $singleTour["descripcion_$lang"] ?? ($singleTour['descripcion_es'] ?? ($singleTour['descripcion'] ?? ''));
    $inc = $singleTour["incluye_$lang"] ?? ($singleTour['incluye_es'] ?? ($singleTour['incluye'] ?? ''));
    $no_inc = $singleTour["no_incluye_$lang"] ?? ($singleTour['no_incluye_es'] ?? ($singleTour['no_incluye'] ?? ''));
    $horario = $singleTour["horario_$lang"] ?? ($singleTour['horario_es'] ?? ($singleTour['horario'] ?? ''));
    $punto = $singleTour["punto_encuentro_$lang"] ?? ($singleTour['punto_encuentro_es'] ?? ($singleTour['punto_encuentro'] ?? ''));

    $precioBase = $singleTour['precio_cop'];
    $precioPromo = $singleTour['precio_promo'] ?? 0;
    $usarPromo = ($precioPromo > 0 && $precioPromo < $precioBase);
    $precioFinalCalc = $usarPromo ? $precioPromo : $precioBase;

    $metaTitle = $nombre;
    $metaDesc = !empty($desc) ? substr(strip_tags($desc), 0, 150) . "..." : "Reserva este tour en Cartagena.";
    if(!empty($singleTour['imagen'])) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $metaImage = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/" . $singleTour['imagen'];
    }

    $mensaje  = "Hola Descubre Cartagena, me gustaría reservar: \n\n";
    $mensaje .= "📍 *" . $nombre . "*\n";
    $mensaje .= "🔗 " . $currentUrl;
    $waLink = "https://wa.me/573205899997?text=" . urlencode($mensaje);
} else {
    $metaTitle = "Descubre Cartagena";
    $metaDesc = "Los mejores tours y experiencias en Cartagena de Indias.";
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($metaTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Poppins', sans-serif; color: #333; padding-bottom: 40px; }
        .site-header { background-color: #ffffff; box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 15px 0; text-align: center; margin-bottom: 30px; position: relative; }
        .main-logo { width: 180px; max-width: 70%; height: auto; display: block; margin: 0 auto; }
        @media (min-width: 992px) { .site-header { padding: 31.5px 0; } .main-logo { width: 288px; } }
        
        .lang-switcher { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); display: flex; gap: 10px; }
        .lang-switcher a { text-decoration: none; opacity: 0.5; font-size: 1.2rem; transition: 0.3s; }
        .lang-switcher a.active { opacity: 1; transform: scale(1.1); }

        .card-price { border: 0; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-decoration: none; color: inherit; display: block; background: white; transition: transform 0.2s; overflow: hidden; height: 100%; position: relative; }
        .card-price:hover { transform: translateY(-5px); }
        .tour-img-list { width: 100%; height: 200px; object-fit: cover; border-bottom: 1px solid #f0f0f0; }
        .badge-oferta { position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; padding: 5px 12px; border-radius: 50px; font-weight: 800; font-size: 0.75rem; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .price-cop-highlight { color: #1a1a1a; font-weight: 700; font-size: 1.25rem; line-height: 1.1; }
        .price-old { text-decoration: line-through; color: #999; font-size: 0.8rem; font-weight: 400; margin-bottom: 2px; }
        .flag-icon { width: 20px; vertical-align: middle; margin-right: 5px; }
        .calc-box { background-color: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .btn-whatsapp-desktop { background-color: #25D366; color: white; font-weight: 700; border-radius: 50px; padding: 14px; text-decoration: none; display: block; text-align: center; }
        .btn-whatsapp-mobile { position: fixed; bottom: 25px; left: 50%; transform: translateX(-50%); z-index: 1050; background-color: #25D366; color: white; padding: 14px 30px; border-radius: 50px; box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4); font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 10px; white-space: nowrap; }
        .filter-btn-group { display: flex; gap: 8px; overflow-x: auto; padding: 5px 15px 15px 15px; justify-content: flex-start; scrollbar-width: none; }
        @media (min-width: 768px) { .filter-btn-group { justify-content: center; } }
        .btn-filter { background: white; border: 1px solid #dee2e6; color: #666; padding: 8px 16px; border-radius: 50px; font-size: 0.8rem; font-weight: 600; white-space: nowrap; flex-shrink: 0; }
        .btn-filter.active { background: #0d6efd; border-color: #0d6efd; color: white; }
    </style>
</head>
<body>

<div class="site-header">
    <div class="container">
        <a href="./?lang=<?= $lang ?>"><img src="logo.svg" alt="Descubre Cartagena" class="main-logo"></a>
        <div class="lang-switcher">
            <a href="?lang=es" class="<?= $lang=='es'?'active':'' ?>">🇪🇸</a>
            <a href="?lang=en" class="<?= $lang=='en'?'active':'' ?>">🇺🇸</a>
            <a href="?lang=pt" class="<?= $lang=='pt'?'active':'' ?>">🇧🇷</a>
        </div>
    </div>
</div>

<div class="container main-container">
<?php if ($singleTour): ?>
    <div class="calc-container">
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="./?lang=<?= $lang ?>" class="btn btn-light rounded-circle"><i class="fa-solid fa-arrow-left"></i></a>
            <h4 class="mb-0"><?= htmlspecialchars($nombre) ?></h4>
        </div>

        <div class="card card-price p-3 mb-4 text-center">
            <div class="row">
                <div class="col-6 border-end">
                    <span class="text-uppercase text-muted small fw-bold"><?= $dic['adulto'] ?> (<?= $singleTour['rango_adulto'] ?>)</span>
                    <div class="my-2">
                        <?php if($usarPromo): ?><div class="price-old">$<?= number_format($precioBase) ?></div><?php endif; ?>
                        <div class="price-cop-highlight">$<?= number_format($precioFinalCalc) ?></div>
                    </div>
                    <div class="small text-muted">
                        <div><img src="https://flagcdn.com/w40/us.png" class="flag-icon"> USD $<?= precio_inteligente($precioFinalCalc / $tasa_tuya_usd) ?></div>
                        <div><img src="https://flagcdn.com/w40/br.png" class="flag-icon"> BRL R$<?= precio_inteligente($precioFinalCalc / $tasa_tuya_brl) ?></div>
                    </div>
                </div>
                <div class="col-6">
                    <span class="text-uppercase text-muted small fw-bold"><?= $dic['nino'] ?> (<?= $singleTour['rango_nino'] ?>)</span>
                    <?php if(!empty($singleTour['precio_nino'])): ?>
                        <div class="my-2"><div class="price-cop-highlight">$<?= number_format($singleTour['precio_nino']) ?></div></div>
                        <div class="small text-muted">
                            <div><img src="https://flagcdn.com/w40/us.png" class="flag-icon"> USD $<?= precio_inteligente($singleTour['precio_nino'] / $tasa_tuya_usd) ?></div>
                            <div><img src="https://flagcdn.com/w40/br.png" class="flag-icon"> BRL R$<?= precio_inteligente($singleTour['precio_nino'] / $tasa_tuya_brl) ?></div>
                        </div>
                    <?php else: ?><div class="mt-3 text-muted">-</div><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="calc-box mb-5">
            <h6 class="text-center fw-bold mb-4"><?= $dic['calcular_total'] ?></h6>
            <div class="row g-2 justify-content-center">
                <div class="col-5 text-center"><label class="small fw-bold">ADULTOS</label><input type="number" id="qtyAdult" class="form-control text-center" value="1" min="1"></div>
                <div class="col-5 text-center"><label class="small fw-bold">NIÑOS</label><input type="number" id="qtyKid" class="form-control text-center" value="0" min="0"></div>
            </div>
            <div class="bg-primary bg-opacity-10 rounded p-3 mt-4 text-center">
                <div class="small text-primary fw-bold"><?= $dic['total_pagar'] ?></div>
                <div class="fs-2 fw-bold" id="totalCOP">$0</div>
                <div class="d-flex justify-content-center gap-4 mt-2">
                    <div id="totalUSD" class="fw-bold text-success"></div>
                    <div id="totalBRL" class="fw-bold text-primary"></div>
                </div>
            </div>
            <a href="<?= $waLink ?>" target="_blank" class="btn-whatsapp-desktop mt-4 shadow"><i class="fa-brands fa-whatsapp"></i> <?= $dic['btn_reserva'] ?></a>
        </div>
        <a href="./?lang=<?= $lang ?>" class="btn btn-outline-secondary w-100 rounded-pill mb-5"><?= $dic['ver_tours'] ?></a>
    </div>

    <script>
        const pA = <?= $precioFinalCalc ?>; const pK = <?= $singleTour['precio_nino'] ?: 0 ?>;
        const rU = <?= $tasa_tuya_usd ?>; const rB = <?= $tasa_tuya_brl ?>;
        function calc() {
            const t = (document.getElementById('qtyAdult').value * pA) + (document.getElementById('qtyKid').value * pK);
            document.getElementById('totalCOP').innerText = '$' + new Intl.NumberFormat('es-CO').format(t);
            document.getElementById('totalUSD').innerText = 'USD $' + Math.ceil(t/rU);
            document.getElementById('totalBRL').innerText = 'BRL R$' + Math.ceil(t/rB);
        }
        document.getElementById('qtyAdult').oninput = calc; document.getElementById('qtyKid').oninput = calc; calc();
    </script>

<?php else: ?>
    <div class="search-container mb-4 position-relative">
        <i class="fa-solid fa-magnifying-glass position-absolute" style="left:20px; top:50%; transform:translateY(-50%); color:#bbb;"></i>
        <input type="text" id="searchTour" class="form-control rounded-pill py-3 ps-5" placeholder="<?= $dic['search_placeholder'] ?>">
    </div>

    <div class="filter-btn-group mb-4">
        <button class="btn-filter active" onclick="sortTours('nombre', this)"><?= $dic['filter_all'] ?></button>
        <button class="btn-filter" onclick="sortTours('precio_min', this)"><?= $dic['filter_price'] ?></button>
        <button class="btn-filter" onclick="sortTours('ofertas', this)"><?= $dic['filter_promo'] ?></button>
        <button class="btn-filter" onclick="sortTours('ninos', this)"><?= $dic['filter_kids'] ?></button>
    </div>

    <div class="row g-4" id="toursGrid">
        <?php foreach ($tours as $slug => $tour): 
            if(!empty($tour['oculto'])) continue;
            $tNombre = $tour["nombre_$lang"] ?? ($tour['nombre_es'] ?? ($tour['nombre'] ?? ''));
            $pB = $tour['precio_cop']; $pP = $tour['precio_promo'] ?? 0;
            $esO = ($pP > 0 && $pP < $pB); $pF = $esO ? $pP : $pB;
        ?>
        <div class="col-12 col-md-6 col-lg-4 tour-card-col" data-nombre="<?= $tNombre ?>" data-precio="<?= $pF ?>" data-oferta="<?= $esO?'1':'0' ?>" data-nino="<?= !empty($tour['precio_nino'])?'1':'0' ?>">
            <a href="./<?= $slug ?>?lang=<?= $lang ?>" class="card card-price">
                <?php if(!empty($tour['imagen'])): ?><img src="<?= $tour['imagen'] ?>" class="tour-img-list"><?php endif; ?>
                <?php if($esO): ?><span class="badge-oferta"><?= strtoupper($dic['filter_promo']) ?></span><?php endif; ?>
                <div class="p-4">
                    <h6 class="tour-title mb-3"><?= htmlspecialchars($tNombre) ?></h6>
                    <div style="min-height:50px;">
                        <?php if($esO): ?><div class="price-old">$<?= number_format($pB) ?></div><?php endif; ?>
                        <div class="price-cop-highlight">$<?= number_format($pF) ?> <small class="text-muted fw-normal">COP</small></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-end mt-3 pt-3 border-top">
                        <div class="small text-muted">
                            <div><img src="https://flagcdn.com/w40/us.png" class="flag-icon"> USD $<?= precio_inteligente($pF / $tasa_tuya_usd) ?></div>
                            <div><img src="https://flagcdn.com/w40/br.png" class="flag-icon"> BRL R$<?= precio_inteligente($pF / $tasa_tuya_brl) ?></div>
                        </div>
                        <i class="fa-solid fa-circle-arrow-right text-primary fs-4"></i>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        // BUSCADOR CON NORMALIZACIÓN
        document.getElementById('searchTour').onkeyup = function() {
            let f = this.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            document.querySelectorAll('.tour-card-col').forEach(c => {
                let t = c.dataset.nombre.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                c.style.display = t.includes(f) ? '' : 'none';
            });
        };

        function sortTours(criteria, btn) {
            document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const grid = document.getElementById('toursGrid');
            const cards = Array.from(grid.getElementsByClassName('tour-card-col'));
            
            if(criteria === 'ofertas') cards.forEach(c => c.style.display = c.dataset.oferta === '1' ? '' : 'none');
            else cards.forEach(c => c.style.display = '');

            cards.sort((a, b) => {
                if(criteria === 'precio_min') return a.dataset.precio - b.dataset.precio;
                if(criteria === 'ninos') return b.dataset.nino - a.dataset.nino;
                return a.dataset.nombre.localeCompare(b.dataset.nombre);
            });
            cards.forEach(c => grid.appendChild(c));
        }
    </script>
<?php endif; ?>
</div>
</body>
</html>