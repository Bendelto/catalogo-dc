<?php
// 1. IDIOMA Y DICCIONARIO
$lang = $_GET['lang'] ?? 'es';
$dicArr = json_decode(file_get_contents('lang.json'), true);
$dic = $dicArr[$lang] ?? $dicArr['es'];

// 2. CONFIGURACIÓN Y MONEDA
$fileConfig = 'config.json';
$config = json_decode(file_get_contents($fileConfig), true);
$cacheFile = 'tasa.json';
$rates = json_decode(file_get_contents($cacheFile), true);
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
    $horario = $singleTour["horario_$lang"] ?? ($singleTour['horario_es'] ?? ($singleTour['horario'] ?? ''));
    $punto = $singleTour["punto_encuentro_$lang"] ?? ($singleTour['punto_encuentro_es'] ?? ($singleTour['punto_encuentro'] ?? ''));
    
    $precioBase = $singleTour['precio_cop'];
    $precioPromo = $singleTour['precio_promo'] ?? 0;
    $esOferta = ($precioPromo > 0 && $precioPromo < $precioBase);
    $precioFinalCalc = $esOferta ? $precioPromo : $precioBase;
    $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $waLink = "https://wa.me/573205899997?text=".urlencode("Hola Descubre Cartagena, me gustaría reservar: $nombre\n🔗 $currentUrl");
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
        .lang-switcher a { text-decoration: none; opacity: 0.5; font-size: 1.2rem; }
        .lang-switcher a.active { opacity: 1; transform: scale(1.1); }
        .gallery-reel-container { width: 100%; overflow-x: auto; display: flex; gap: 10px; padding-bottom: 10px; scroll-snap-type: x mandatory; margin-bottom: 15px; }
        .gallery-reel-item { height: 38vh; width: auto; border-radius: 12px; scroll-snap-align: center; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); cursor: zoom-in; }
        @media (min-width: 768px) { .gallery-reel-item { height: 350px; } }
        #lightbox { display: none; position: fixed; z-index: 9999; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); align-items: center; justify-content: center; }
        #lightbox img { max-width: 95%; max-height: 90vh; object-fit: contain; }
        .card-price { border: 0; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-decoration: none; color: inherit; display: block; background: white; transition: transform 0.2s; overflow: hidden; position: relative; }
        .badge-oferta { position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; padding: 5px 12px; border-radius: 50px; font-weight: 800; font-size: 0.75rem; }
        .tour-title { font-weight: 700; color: #1a1a1a; font-size: 1.1rem; }
        .price-cop-highlight { color: #1a1a1a; font-weight: 700; font-size: 1.2rem; }
        .price-old { text-decoration: line-through; color: #999; font-size: 0.75rem; display: block; }
        .filter-btn-group { display: flex; gap: 8px; overflow-x: auto; padding: 5px 15px 15px 15px; scrollbar-width: none; }
        @media (min-width: 768px) { .filter-btn-group { justify-content: center; } }
        .btn-filter { background: white; border: 1px solid #dee2e6; color: #666; padding: 8px 16px; border-radius: 50px; font-size: 0.8rem; font-weight: 600; white-space: nowrap; }
        .btn-filter.active { background: #0d6efd; border-color: #0d6efd; color: white; }
        .accordion-item { border: 0; border-radius: 12px !important; overflow: hidden; margin-bottom: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .total-display { background-color: #e7f1ff; color: #0d6efd; border-radius: 12px; padding: 20px; }
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

<div id="lightbox" onclick="this.style.display='none'"><img id="lb-img" src=""></div>

<div class="container">
<?php if ($singleTour): ?>
    <div class="mx-auto" style="max-width: 600px;">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="d-flex align-items-center gap-3">
                <a href="./?lang=<?= $lang ?>" class="btn btn-light rounded-circle"><i class="fa-solid fa-arrow-left"></i></a>
                <h4 class="mb-0 fw-bold"><?= htmlspecialchars($nombre) ?></h4>
            </div>
            <button class="btn btn-light rounded-circle" onclick="share()"><i class="fa-solid fa-share-nodes"></i></button>
        </div>

        <div class="gallery-reel-container">
            <?php $imgs = array_merge([$singleTour['imagen']], $singleTour['galeria'] ?? []);
            foreach($imgs as $img): if(!$img) continue; ?>
                <img src="<?= $img ?>" class="gallery-reel-item" onclick="openLB(this.src)">
            <?php endforeach; ?>
        </div>
        <div class="text-center text-muted small mb-4" style="font-size:0.7rem;"><?= $dic['desliza'] ?></div>

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
                        <?php if(!empty($singleTour['precio_nino'])): ?><span class="price-cop-highlight">$<?= number_format($singleTour['precio_nino']) ?></span><?php else: ?><span class="text-muted small">-</span><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="border-top mt-3 pt-2 small text-muted">Tasas hoy: USD: <strong>$<?= number_format($tasa_tuya_usd, 0) ?></strong> | BRL: <strong>$<?= number_format($tasa_tuya_brl, 0) ?></strong></div>
        </div>

        <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
            <?php if($desc): ?><p class="text-secondary small mb-4"><?= htmlspecialchars($desc) ?></p><hr><?php endif; ?>
            <div class="row"><div class="col-6"><h6><?= $dic['incluye'] ?></h6><div class="small text-secondary"><?= nl2br(htmlspecialchars($inc)) ?></div></div><div class="col-6"><h6><?= $dic['no_incluye'] ?></h6><div class="small text-secondary"><?= nl2br(htmlspecialchars($no_inc)) ?></div></div></div>
        </div>

        <?php if($horario || $punto): ?>
        <div class="accordion mb-4" id="ext">
            <?php if($horario): ?><div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed py-2 small" data-bs-toggle="collapse" data-bs-target="#h"><?= $dic['horarios'] ?></button></h2><div id="h" class="accordion-collapse collapse p-3 small"><?= htmlspecialchars($horario) ?></div></div><?php endif; ?>
            <?php if($punto): ?><div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed py-2 small" data-bs-toggle="collapse" data-bs-target="#p"><?= $dic['punto_encuentro'] ?></button></h2><div id="p" class="accordion-collapse collapse p-3 small"><?= htmlspecialchars($punto) ?></div></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="card p-4 shadow-sm border-0 mb-4">
            <h6 class="text-center fw-bold mb-3"><?= $dic['calcular_total'] ?></h6>
            <div class="row g-2 justify-content-center">
                <div class="col-5"><label class="small fw-bold">ADULTOS</label><input type="number" id="qA" class="form-control text-center" value="1" min="1"></div>
                <div class="col-5"><label class="small fw-bold">NIÑOS</label><input type="number" id="qK" class="form-control text-center" value="0" min="0"></div>
            </div>
            <div class="total-display mt-4 text-center">
                <div class="small fw-bold mb-1"><?= $dic['total_pagar'] ?></div>
                <div class="fs-2 fw-bold" id="tCOP">$0</div>
                <div class="d-flex justify-content-center gap-3 mt-1 small">
                    <div id="tUSD" class="text-success fw-bold"></div><div id="tBRL" class="text-primary fw-bold"></div>
                </div>
            </div>
            <a href="<?= $waLink ?>" target="_blank" class="btn btn-success w-100 rounded-pill py-3 fw-bold mt-4 shadow"><i class="fa-brands fa-whatsapp"></i> <?= $dic['btn_reserva'] ?></a>
        </div>
        <a href="./?lang=<?= $lang ?>" class="btn btn-outline-secondary w-100 rounded-pill mb-5"><?= $dic['ver_tours'] ?></a>
    </div>
    <script>
        const pA=<?= $precioFinalCalc ?>, pK=<?= $singleTour['precio_nino'] ?: 0 ?>, rU=<?= $tasa_tuya_usd ?>, rB=<?= $tasa_tuya_brl ?>;
        function calc(){ const t=(qA.value*pA)+(qK.value*pK); tCOP.innerText='$'+new Intl.NumberFormat('es-CO').format(t); tUSD.innerText='USD $'+Math.ceil(t/rU); tBRL.innerText='BRL R$'+Math.ceil(t/rB); }
        qA.oninput=calc; qK.oninput=calc; calc();
        function openLB(s){ document.getElementById('lb-img').src=s; document.getElementById('lightbox').style.display='flex'; }
        function share(){ navigator.share({ title: '<?= $nombre ?>', url: window.location.href }); }
    </script>

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
        <?php foreach ($tours as $slug => $tour): if(!empty($tour['oculto'])) continue;
            $tNom = $tour["nombre_$lang"] ?? ($tour['nombre_es'] ?? ($tour['nombre'] ?? ''));
            $pB = $tour['precio_cop']; $pP = $tour['precio_promo'] ?? 0;
            $isO = ($pP > 0 && $pP < $pB); $pF = $isO ? $pP : $pB;
        ?>
        <div class="col-12 col-md-6 col-lg-4 tour-card-col" data-nombre="<?= $tNom ?>" data-precio="<?= $pF ?>" data-oferta="<?= $isO?'1':'0' ?>" data-nino="<?= !empty($tour['precio_nino'])?'1':'0' ?>">
            <a href="./<?= $slug ?>?lang=<?= $lang ?>" class="card card-price">
                <img src="<?= $tour['imagen'] ?>" class="tour-img-list" style="height:200px; object-fit:cover;">
                <?php if($isO): ?><span class="badge-oferta">OFERTA</span><?php endif; ?>
                <div class="p-4">
                    <h6 class="tour-title mb-3"><?= htmlspecialchars($tNom) ?></h6>
                    <div class="mb-3" style="min-height:50px;">
                        <?php if($isO): ?><span class="price-old">$<?= number_format($pB) ?></span><?php endif; ?>
                        <span class="price-cop-highlight">$<?= number_format($pF) ?> <small class="text-muted fw-normal" style="font-size:0.75rem;">COP</small></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top small text-muted">
                        <div>USD $<?= pInt($pF / $tasa_tuya_usd) ?> | BRL R$ <?= pInt($pF / $tasa_tuya_brl) ?></div><i class="fa-solid fa-circle-arrow-right text-primary fs-4"></i>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <script>
        searchTour.onkeyup=function(){
            let f=this.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            document.querySelectorAll('.tour-card-col').forEach(c=>{
                let t=c.dataset.nombre.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                c.style.display=t.includes(f)?'':'none';
            });
        };
        function sortTours(crit, btn){
            document.querySelectorAll('.btn-filter').forEach(b=>b.classList.remove('active')); btn.classList.add('active');
            const grid=toursGrid, cards=Array.from(grid.getElementsByClassName('tour-card-col'));
            if(crit==='ofertas') cards.forEach(c=>c.style.display=c.dataset.oferta==='1'?'':'none');
            else cards.forEach(c=>c.style.display='');
            cards.sort((a,b)=>{
                if(crit==='precio_min') return a.dataset.precio-b.dataset.precio;
                if(crit==='ninos') return b.dataset.nino-a.dataset.nino;
                return a.dataset.nombre.localeCompare(b.dataset.nombre);
            });
            cards.forEach(c=>grid.appendChild(c));
        }
    </script>
<?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>