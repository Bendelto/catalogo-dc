<?php
// CONFIG BASICA
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

$tours = file_exists('data.json') ? json_decode(file_get_contents('data.json'), true) : [];
uasort($tours, function($a, $b) { return strcasecmp($a['nombre'], $b['nombre']); });

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

// --- PREPARAR DATOS Y ENLACE WHATSAPP ---
$waLink = "";
if ($singleTour) {
    $desc = $singleTour['descripcion'] ?? $singleTour['description'] ?? '';
    $inc = $singleTour['incluye'] ?? $singleTour['include'] ?? '';
    $no_inc = $singleTour['no_incluye'] ?? $singleTour['not_include'] ?? '';
    $horario = $singleTour['horario'] ?? $singleTour['schedule'] ?? '';
    $punto = $singleTour['punto_encuentro'] ?? $singleTour['meeting_point'] ?? '';

    // Construcción del mensaje de WhatsApp
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $currentUrl = $protocol . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    
    $mensaje  = "Hola Descubre Cartagena, me gustaría reservar: \n\n";
    $mensaje .= "📍 *" . $singleTour['nombre'] . "*\n";
    $mensaje .= "🔗 " . $currentUrl;
    
    $waLink = "https://wa.me/573205899997?text=" . urlencode($mensaje);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $singleTour ? $singleTour['nombre'] : 'Lista de Precios' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #333; }
        .main-container { max-width: 1200px; margin: 0 auto; }
        .calc-container { max-width: 600px; margin: 0 auto; }
        .main-logo { width: 300px; max-width: 85%; height: auto; display: block; margin: 0 auto; }
        
        .card-price { border: 0; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-decoration: none; color: inherit; display: block; background: white; transition: transform 0.2s; overflow: hidden; height: 100%; }
        .card-price:hover { transform: translateY(-5px); }
        .tour-img-list { width: 100%; height: 200px; object-fit: cover; border-bottom: 1px solid #f0f0f0; }

        .gallery-reel-container { width: 100%; overflow-x: auto; display: flex; gap: 10px; padding-bottom: 10px; scroll-snap-type: x mandatory; margin-bottom: 15px; }
        .gallery-reel-item { height: 38vh; width: auto; max-width: none; border-radius: 12px; scroll-snap-align: center; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); cursor: zoom-in; background: #fff; }
        @media (min-width: 768px) { .gallery-reel-item { height: 350px; } }
        
        #lightbox { display: none; position: fixed; z-index: 9999; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); align-items: center; justify-content: center; flex-direction: column; }
        #lightbox img { max-width: 100%; max-height: 90vh; object-fit: contain; }
        .lightbox-close { position: absolute; top: 20px; right: 20px; color: white; font-size: 2rem; cursor: pointer; }

        .info-box { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .list-check li { list-style: none; padding-left: 0; margin-bottom: 6px; font-size: 0.95rem; }
        
        .accordion-button:not(.collapsed) { color: #495057; background-color: #f8f9fa; font-weight: bold; }
        .accordion-item { border: 0; border-radius: 12px !important; overflow: hidden; margin-bottom: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }

        h4, h6 { font-weight: 700; color: #2c3e50; }
        .price-usd { color: #198754; font-weight: 700; }
        .price-brl { color: #0d6efd; font-weight: 700; }
        .price-cop-highlight { color: #212529; font-weight: 800; font-size: 1.4rem; }
        .flag-icon { width: 20px; vertical-align: text-bottom; margin-right: 5px; }
        .badge-tasa { font-size: 0.8rem; background: #fff; border: 1px solid #dee2e6; color: #6c757d; padding: 6px 12px; border-radius: 50px; display: inline-flex; align-items: center; }
        
        .calc-box { background-color: #fff; border-radius: 12px; padding: 20px; border: 1px solid #edf2f7; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .form-control-qty { text-align: center; font-weight: bold; background: #f8f9fa; height: 50px; font-size: 1.3rem; }
        .total-display { background-color: #e7f1ff; color: #0d6efd; border: 1px solid #cce5ff; border-radius: 12px; padding: 20px; margin-top: 20px; }
        
        .btn-solid-blue { background-color: #0d6efd; color: white; border: none; border-radius: 50px; padding: 12px 20px; font-weight: 600; width: 100%; display: block; text-align: center; text-decoration: none; }
        .btn-back { background-color: #e9ecef; color: #212529; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-weight: bold; }
        .currency-tag { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; opacity: 0.8; }

        /* --- BOTONES WHATSAPP --- */
        .btn-whatsapp-desktop {
            background-color: #25D366; color: white; font-weight: bold; border: none;
            border-radius: 50px; padding: 12px; text-decoration: none; display: block; text-align: center;
            transition: background 0.3s;
        }
        .btn-whatsapp-desktop:hover { background-color: #1ebc57; color: white; }

        .btn-whatsapp-mobile {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            z-index: 1050; background-color: #25D366; color: white;
            padding: 12px 25px; border-radius: 50px;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
            font-weight: bold; text-decoration: none; display: flex; align-items: center; gap: 8px;
            white-space: nowrap;
        }
        .btn-whatsapp-mobile:hover { color: white; }
    </style>
</head>
<body class="py-4">

<div id="lightbox" onclick="closeLightbox()"><div class="lightbox-close">&times;</div><img id="lightbox-img" src=""></div>

<div class="container main-container">
<?php if ($singleTour): ?>
    <div class="calc-container" style="padding-bottom: 80px;"> <div class="d-flex align-items-center gap-3 mb-4">
            <a href="./" class="btn-back"><i class="fa-solid fa-arrow-left"></i></a>
            <h4 class="mb-0 lh-sm"><?= htmlspecialchars($singleTour['nombre']) ?></h4>
        </div>

        <?php 
            $imagenesParaMostrar = [];
            if(!empty($singleTour['imagen'])) $imagenesParaMostrar[] = $singleTour['imagen'];
            if(!empty($singleTour['galeria'])) foreach($singleTour['galeria'] as $gImg) $imagenesParaMostrar[] = $gImg;
        ?>
        <?php if(count($imagenesParaMostrar) > 0): ?>
            <div class="gallery-reel-container">
                <?php foreach($imagenesParaMostrar as $imgSrc): ?>
                    <img src="<?= $imgSrc ?>" class="gallery-reel-item" onclick="openLightbox('<?= $imgSrc ?>')" alt="Foto">
                <?php endforeach; ?>
            </div>
            <div class="text-center text-muted small mb-4" style="font-size:0.75rem;"><i class="fa-solid fa-hand-pointer"></i> Desliza o toca para ampliar</div>
        <?php endif; ?>

        <div class="card card-price p-3 mb-4">
            <div class="row g-0 text-center">
                <div class="col-6 border-end pe-2">
                    <span class="text-uppercase text-muted fw-bold" style="font-size:0.7rem;">Adulto <small class="fw-normal">(<?= $singleTour['rango_adulto'] ?? '' ?>)</small></span>
                    <div class="price-cop-highlight my-1">$<?= number_format($singleTour['precio_cop']) ?></div>
                    <div class="d-flex flex-column gap-1">
                        <span class="price-usd small"><img src="https://flagcdn.com/w40/us.png" class="flag-icon"> USD $<?= precio_inteligente($singleTour['precio_cop'] / $tasa_tuya_usd) ?></span>
                        <span class="price-brl small"><img src="https://flagcdn.com/w40/br.png" class="flag-icon"> BRL R$<?= precio_inteligente($singleTour['precio_cop'] / $tasa_tuya_brl) ?></span>
                    </div>
                </div>
                <div class="col-6 ps-2">
                    <span class="text-uppercase text-muted fw-bold" style="font-size:0.7rem;">Niño <small class="fw-normal">(<?= $singleTour['rango_nino'] ?? '' ?>)</small></span>
                    <?php if(!empty($singleTour['precio_nino'])): ?>
                        <div class="price-cop-highlight my-1">$<?= number_format($singleTour['precio_nino']) ?></div>
                        <div class="d-flex flex-column gap-1">
                            <span class="price-usd small"><img src="https://flagcdn.com/w40/us.png" class="flag-icon"> USD $<?= precio_inteligente($singleTour['precio_nino'] / $tasa_tuya_usd) ?></span>
                            <span class="price-brl small"><img src="https://flagcdn.com/w40/br.png" class="flag-icon"> BRL R$<?= precio_inteligente($singleTour['precio_nino'] / $tasa_tuya_brl) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="text-muted mt-3 small">- No aplica -</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="info-box">
            <?php if(!empty($desc)): ?>
                <div class="text-secondary mb-4" style="white-space: pre-line; line-height: 1.6;">
                    <?= htmlspecialchars($desc) ?>
                </div>
                <hr class="opacity-25 my-4">
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-12 col-md-6 border-bottom border-md-0 pb-3 pb-md-0">
                    <h6 class="text-dark mb-3"><i class="fa-solid fa-circle-check text-success"></i> Incluye</h6>
                    <ul class="list-check ps-0 m-0 text-secondary">
                        <?php foreach(explode("\n", $inc) as $item): if(trim($item)=='')continue; ?>
                            <li><i class="fa-solid fa-check text-success"></i> <?= htmlspecialchars($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-12 col-md-6">
                    <h6 class="text-dark mb-3"><i class="fa-solid fa-circle-xmark text-danger"></i> No incluye</h6>
                    <ul class="list-check ps-0 m-0 text-secondary">
                        <?php foreach(explode("\n", $no_inc) as $item): if(trim($item)=='')continue; ?>
                            <li><i class="fa-solid fa-xmark text-danger"></i> <?= htmlspecialchars($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <?php if(!empty($horario) || !empty($punto)): ?>
        <div class="accordion accordion-flush mb-4" id="accordionExtras">
            
            <?php if(!empty($horario)): ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHorario">
                        <i class="fa-regular fa-clock me-2"></i> Horarios
                    </button>
                </h2>
                <div id="collapseHorario" class="accordion-collapse collapse" data-bs-parent="#accordionExtras">
                    <div class="accordion-body text-secondary">
                        <ul class="list-unstyled m-0">
                            <?php foreach(explode("\n", $horario) as $line): if(trim($line)=='')continue; ?>
                                <li class="mb-2 d-flex align-items-start">
                                    <i class="fa-regular fa-clock text-primary mt-1 me-2"></i>
                                    <span><?= htmlspecialchars($line) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if(!empty($punto)): ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePunto">
                        <i class="fa-solid fa-map-location-dot me-2"></i> Punto de Encuentro
                    </button>
                </h2>
                <div id="collapsePunto" class="accordion-collapse collapse" data-bs-parent="#accordionExtras">
                    <div class="accordion-body text-secondary">
                        <ul class="list-unstyled m-0">
                            <?php foreach(explode("\n", $punto) as $line): if(trim($line)=='')continue; ?>
                                <li class="mb-2 d-flex align-items-start">
                                    <i class="fa-solid fa-map-pin text-danger mt-1 me-2"></i>
                                    <span><?= htmlspecialchars($line) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="calc-box mb-4">
            <h6 class="fw-bold mb-4 text-center text-secondary"><i class="fa-solid fa-calculator me-2"></i>Calcular Total</h6>
            <div class="row g-3 justify-content-center">
                <div class="col-5"><label class="small text-muted mb-2 d-block text-center fw-bold">ADULTOS</label><input type="number" id="qtyAdult" class="form-control form-control-qty shadow-sm" value="1" min="1"></div>
                <div class="col-5"><label class="small text-muted mb-2 d-block text-center fw-bold">NIÑOS</label><input type="number" id="qtyKid" class="form-control form-control-qty shadow-sm" value="0" min="0" <?= empty($singleTour['precio_nino']) ? 'disabled' : '' ?>></div>
            </div>
            <div class="total-display text-center">
                <div class="small text-uppercase text-secondary mb-1 fw-bold">Total a Pagar</div>
                <div class="fw-bold text-dark fs-1 lh-1 mb-3" id="totalCOP">$<?= number_format($singleTour['precio_cop']) ?></div>
                <div class="row pt-3 border-top border-primary-subtle">
                    <div class="col-6 border-end border-primary-subtle"><div class="currency-tag text-success mb-1"><img src="https://flagcdn.com/w40/us.png" class="flag-icon"> Dollars</div><div class="fw-bold text-success fs-4" id="totalUSD">$0</div></div>
                    <div class="col-6"><div class="currency-tag text-primary mb-1"><img src="https://flagcdn.com/w40/br.png" class="flag-icon"> Reais</div><div class="fw-bold text-primary fs-4" id="totalBRL">R$ 0</div></div>
                </div>
            </div>
            
            <div class="d-none d-md-block mt-4">
                <a href="<?= $waLink ?>" target="_blank" class="btn-whatsapp-desktop shadow">
                    <i class="fa-brands fa-whatsapp fa-lg me-2"></i> Reservar por WhatsApp
                </a>
            </div>
        </div>

        <a href="./" class="btn-solid-blue shadow mb-5">Ver todos los tours</a>
        
        <a href="<?= $waLink ?>" target="_blank" class="btn-whatsapp-mobile d-md-none">
            <i class="fa-brands fa-whatsapp fa-lg"></i> Reservar por WhatsApp
        </a>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const priceAdult = <?= $singleTour['precio_cop'] ?>;
        const priceKid = <?= $singleTour['precio_nino'] ?: 0 ?>;
        const rateUsd = <?= $tasa_tuya_usd ?>; const rateBrl = <?= $tasa_tuya_brl ?>;
        const inputAdult = document.getElementById('qtyAdult');
        const inputKid = document.getElementById('qtyKid');
        const dCOP = document.getElementById('totalCOP');
        const dUSD = document.getElementById('totalUSD');
        const dBRL = document.getElementById('totalBRL');
        function fmt(n){ return '$' + new Intl.NumberFormat('es-CO').format(n); }
        function pInt(v){ return Math.ceil(v * 2) / 2; }
        function calc() {
            let t = (parseInt(inputAdult.value)||0)*priceAdult + (parseInt(inputKid.value)||0)*priceKid;
            dCOP.innerText = fmt(t);
            dUSD.innerText = '$' + pInt(t/rateUsd);
            dBRL.innerText = 'R$ ' + pInt(t/rateBrl);
        }
        inputAdult.addEventListener('input', calc); inputKid.addEventListener('input', calc);
        calc();

        const lightbox = document.getElementById('lightbox');
        const lightboxImg = document.getElementById('lightbox-img');
        function openLightbox(src) { lightboxImg.src = src; lightbox.style.display = 'flex'; }
        function closeLightbox() { lightbox.style.display = 'none'; }
    </script>

<?php else: ?>
    <div class="text-center mb-5 pt-3">
        <img src="logo.svg" alt="Descubre Cartagena" class="main-logo mb-3">
        <div class="d-flex justify-content-center gap-3 mt-3 flex-wrap">
            <span class="badge-tasa"><img src="https://flagcdn.com/w40/us.png" class="flag-icon"><span class="fw-bold text-success">USD</span> $<?= number_format($tasa_tuya_usd, 0) ?></span>
            <span class="badge-tasa"><img src="https://flagcdn.com/w40/br.png" class="flag-icon"><span class="fw-bold text-primary">BRL</span> $<?= number_format($tasa_tuya_brl, 0) ?></span>
        </div>
        <small class="text-muted d-block mt-2" style="font-size: 0.7rem;">* Tasas calculadas con margen de cambio local</small>
    </div>
    <div class="row g-4">
        <?php foreach ($tours as $slug => $tour): 
            // FILTRO DE OCULTOS
            if(!empty($tour['oculto']) && $tour['oculto'] == true) continue;
        ?>
        <div class="col-12 col-md-6 col-lg-4">
            <a href="./<?= $slug ?>" class="card card-price">
                <?php if(!empty($tour['imagen'])): ?><img src="<?= $tour['imagen'] ?>" class="tour-img-list"><?php endif; ?>
                <div class="p-4">
                    <h6 class="fw-bold mb-3 text-dark lh-base"><?= htmlspecialchars($tour['nombre']) ?></h6>
                    <div class="price-cop-highlight mb-3">$<?= number_format($tour['precio_cop']) ?> <small class="fs-6 text-muted fw-normal">COP</small>
                    <?php if(!empty($tour['rango_adulto'])): ?><div style="font-size:0.7rem;color:#999;font-weight:normal">(Adultos <?= $tour['rango_adulto'] ?>)</div><?php endif; ?></div>
                    <div class="d-flex justify-content-between align-items-end mt-auto pt-3 border-top">
                        <div class="d-flex flex-column gap-1">
                            <div class="price-usd"><img src="https://flagcdn.com/w40/us.png" class="flag-icon"> USD $<?= precio_inteligente($tour['precio_cop'] / $tasa_tuya_usd) ?></div>
                            <div class="price-brl"><img src="https://flagcdn.com/w40/br.png" class="flag-icon"> BRL R$ <?= precio_inteligente($tour['precio_cop'] / $tasa_tuya_brl) ?></div>
                        </div>
                        <div class="text-primary fs-5"><i class="fa-solid fa-circle-arrow-right"></i></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>
</body>
</html>