<?php
// 1. IDIOMA Y DICCIONARIO
$lang = $_GET['lang'] ?? 'es';
$dicArr = json_decode(file_get_contents('lang.json'), true);
$dic = $dicArr[$lang] ?? $dicArr['es'];

// 2. CONFIGURACIÓN Y MONEDA
$fileConfig = 'config.json';
$config = json_decode(file_get_contents($fileConfig), true);
$rates = json_decode(file_get_contents('tasa.json'), true);
$tasa_tuya_usd = (1 / $rates['rates']['USD']) - $config['margen_usd'];
$tasa_tuya_brl = (1 / $rates['rates']['BRL']) - $config['margen_brl'];
function pInt($v) { return (float)(ceil($v * 2) / 2); }

// 3. DATOS
$tours = json_decode(file_get_contents('data.json'), true);
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = dirname($_SERVER['SCRIPT_NAME']);
if($base_path == '/') $base_path = '';
$slug_solicitado = trim(str_replace($base_path, '', $request_uri), '/');

$singleTour = null;
if (!empty($slug_solicitado) && isset($tours[$slug_solicitado])) {
    if (empty($tours[$slug_solicitado]['oculto'])) $singleTour = $tours[$slug_solicitado];
}

if ($singleTour) {
    $nombre = $singleTour["nombre_$lang"] ?? ($singleTour['nombre_es'] ?? ($singleTour['nombre'] ?? ''));
    $desc = $singleTour["descripcion_$lang"] ?? ($singleTour['descripcion_es'] ?? ($singleTour['descripcion'] ?? ''));
    $inc = $singleTour["incluye_$lang"] ?? ($singleTour['incluye_es'] ?? ($singleTour['incluye'] ?? ''));
    $no_inc = $singleTour["no_incluye_$lang"] ?? ($singleTour['no_incluye_es'] ?? ($singleTour['no_incluye'] ?? ''));
    
    $precioBase = $singleTour['precio_cop'];
    $precioPromo = $singleTour['precio_promo'] ?? 0;
    $esOferta = ($precioPromo > 0 && $precioPromo < $precioBase);
    $precioFinalCalc = $esOferta ? $precioPromo : $precioBase;
    $waLink = "https://wa.me/573205899997?text=".urlencode("Hola Descubre Cartagena, me gustaría reservar: $nombre");
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $singleTour ? $nombre : 'Descubre Cartagena' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Poppins', sans-serif; color: #333; padding-bottom: 40px; }
        .site-header { background-color: #ffffff; box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 15px 0; text-align: center; margin-bottom: 30px; position: relative; }
        .main-logo { width: 180px; max-width: 70%; display: block; margin: 0 auto; }
        @media (min-width: 992px) { .site-header { padding: 31.5px 0; } .main-logo { width: 288px; } }
        
        .lang-switcher { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); display: flex; gap: 10px; }
        .lang-switcher a { text-decoration: none; opacity: 0.5; font-size: 1.2rem; transition: 0.3s; }
        .lang-switcher a.active { opacity: 1; transform: scale(1.1); }

        .gallery-reel-container { width: 100%; overflow-x: auto; display: flex; gap: 10px; padding-bottom: 10px; scroll-snap-type: x mandatory; margin-bottom: 15px; }
        .gallery-reel-item { height: 38vh; width: auto; border-radius: 12px; scroll-snap-align: center; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        @media (min-width: 768px) { .gallery-reel-item { height: 350px; } }

        .card-price { border: 0; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-decoration: none; color: inherit; display: block; background: white; transition: transform 0.2s; overflow: hidden; position: relative; height: 100%; }
        .card-price:hover { transform: translateY(-5px); }
        .tour-img-list { width: 100%; height: 200px; object-fit: cover; }
        .badge-oferta { position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; padding: 5px 12px; border-radius: 50px; font-weight: 800; font-size: 0.75rem; }
        .tour-title { font-weight: 700; color: #1a1a1a; letter-spacing: -0.5px; font-size: 1.1rem; }
        .price-cop-highlight { color: #1a1a1a; font-weight: 700; font-size: 1.2rem; line-height: 1.1; }
        .price-old { text-decoration: line-through; color: #999; font-size: 0.75rem; font-weight: 400; display: block; }
        
        .filter-btn-group { display: flex; gap: 8px; overflow-x: auto; padding: 5px 15px 15px 15px; justify-content: flex-start; scrollbar-width: none; }
        @media (min-width: 768px) { .filter-btn-group { justify-content: center; } }
        .btn-filter { background: white; border: 1px solid #dee2e6; color: #666; padding: 8px 16px; border-radius: 50px; font-size: 0.8rem; font-weight: 600; white-space: nowrap; }
        .btn-filter.active { background: #0d6efd; border-color: #0d6efd; color: white; }
    </style>
</head>
<body>

<header class="site-header">
    <div class="container">
        <a href="./?lang=<?= $lang ?>"><img src="logo.svg" alt="Descubre Cartagena" class="main-logo"></a>
        <div class="lang-switcher">
            <a href="?lang=es" class="<?= $lang=='es'?'active':'' ?>">🇪🇸</a>
            <a href="?lang=en" class="<?= $lang=='en'?'active':'' ?>">🇺🇸</a>
            <a href="?lang=pt" class="<?= $lang=='pt'?'active':'' ?>">🇧🇷</a>
        </div>
    </div>
</header>

<div class="container">
<?php if ($singleTour): ?>
    <div class="mx-auto" style="max-width: 600px;">
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="./?lang=<?= $lang ?>" class="btn btn-light rounded-circle"><i class="fa-solid fa-arrow-left"></i></a>
            <h4 class="mb-0 fw-bold"><?= htmlspecialchars($nombre) ?></h4>
        </div>

        <div class="gallery-reel-container">
            <?php 
            $imgs = array_merge([$singleTour['imagen']], $singleTour['galeria'] ?? []);
            foreach($imgs as $img): if(!$img) continue; ?>
                <img src="<?= $img ?>" class="gallery-reel-item" onclick="window.open(this.src)">
            <?php endforeach; ?>
        </div>

        <div class="card card-price p-3 mb-4 text-center">
            <div class="row g-0">
                <div class="col-6 border-end">
                    <span class="text-uppercase text-muted fw-bold" style="font-size:0.65rem;"><?= $dic['adulto'] ?></span>
                    <div class="my-2" style="min-height: 45px; display: flex; flex-direction: column; justify-content: center;">
                        <?php if($esOferta): ?><span class="price-old">$<?= number_format($precioBase) ?></span><?php endif; ?>
                        <span class="price-cop-highlight">$<?= number_format($precioFinalCalc) ?></span>
                    </div>
                </div>
                <div class="col-6">
                    <span class="text-uppercase text-muted fw-bold" style="font-size:0.65rem;"><?= $dic['nino'] ?></span>
                    <div class="my-2" style="min-height: 45px; display: flex; flex-direction: column; justify-content: center;">
                        <?php if(!empty($singleTour['precio_nino'])): ?>
                            <span class="price-cop-highlight">$<?= number_format($singleTour['precio_nino']) ?></span>
                        <?php else: ?><span class="text-muted small">-</span><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="border-top mt-3 pt-2">
                <div class="small text-muted" style="font-size:0.65rem;">
                    Tasas: USD: <strong>$<?= number_format($tasa_tuya_usd, 0) ?></strong> | BRL: <strong>$<?= number_format($tasa_tuya_brl, 0) ?></strong>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
            <h6 class="fw-bold mb-3"><?= $dic['incluye'] ?></h6>
            <div class="text-secondary small" style="white-space: pre-line; line-height: 1.6;"><?= $inc ?></div>
        </div>

        <a href="<?= $waLink ?>" target="_blank" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow mb-5"><i class="fa-brands fa-whatsapp"></i> <?= $dic['btn_reserva'] ?></a>
    </div>

<?php else: ?>
    <div class="mx-auto mb-4" style="max-width: 500px; position: relative;">
        <i class="fa-solid fa-magnifying-glass position-absolute" style="left:20px; top:50%; transform:translateY(-50%); color:#bbb;"></i>
        <input type="text" id="searchTour" class="form-control rounded-pill py-3 ps-5 border-0 shadow-sm" placeholder="<?= $dic['search_placeholder'] ?>">
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
            $tNom = $tour["nombre_$lang"] ?? ($tour['nombre_es'] ?? ($tour['nombre'] ?? ''));
            $pB = $tour['precio_cop']; $pP = $tour['precio_promo'] ?? 0;
            $isO = ($pP > 0 && $pP < $pB); $pF = $isO ? $pP : $pB;
        ?>
        <div class="col-12 col-md-6 col-lg-4 tour-card-col" data-nombre="<?= $tNom ?>" data-precio="<?= $pF ?>" data-oferta="<?= $isO?'1':'0' ?>" data-nino="<?= !empty($tour['precio_nino'])?'1':'0' ?>">
            <a href="./<?= $slug ?>?lang=<?= $lang ?>" class="card card-price">
                <?php if(!empty($tour['imagen'])): ?><img src="<?= $tour['imagen'] ?>" class="tour-img-list"><?php endif; ?>
                <?php if($isO): ?><span class="badge-oferta"><?= strtoupper($dic['filter_promo']) ?></span><?php endif; ?>
                <div class="p-4">
                    <h6 class="tour-title mb-3"><?= htmlspecialchars($tNom) ?></h6>
                    <div class="mb-3" style="min-height: 50px; display: flex; flex-direction: column; justify-content: center;">
                        <?php if($isO): ?><span class="price-old">$<?= number_format($pB) ?></span><?php endif; ?>
                        <span class="price-cop-highlight">$<?= number_format($pF) ?> <small class="text-muted fw-normal" style="font-size: 0.75rem;">COP</small></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <div class="small text-muted">
                            <div>USD $<?= pInt($pF / $tasa_tuya_usd) ?></div>
                            <div>BRL R$ <?= pInt($pF / $tasa_tuya_brl) ?></div>
                        </div>
                        <i class="fa-solid fa-circle-arrow-right text-primary fs-4"></i>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
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