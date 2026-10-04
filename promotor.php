<?php
/**
 * promotor.php — "Mi panel de promotor" (público, sin contraseña).
 *
 * Cada simpatizante entra con su llave secreta: promotor.php?t=TOKEN
 * (se la entrega la landing al registrarse, o el equipo por WhatsApp).
 *
 * Muestra: su enlace personal + QR, barra de progreso hacia el siguiente
 * nivel, conteo de invitados (directos y de segundo nivel), ranking de
 * promotores y su lista de tareas.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/inc/promotores.php';

// La llave va en la URL: nada de caché, buscadores ni Referer hacia otros sitios
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$db    = db();
$token = $_GET['t'] ?? '';
$yo    = null;

if (promotor_esquema_listo($db) && is_string($token) && preg_match('/^[a-f0-9]{32}$/', $token)) {
    $st = $db->prepare(
        'SELECT s.id, s.nombre, s.codigo_promotor, s.created_at, z.nombre AS zona
         FROM simpatizantes s LEFT JOIN zonas z ON z.id = s.zona_id
         WHERE s.token_panel = :t LIMIT 1'
    );
    $st->execute(['t' => $token]);
    $yo = $st->fetch() ?: null;
}

if (!$yo) {
    http_response_code(404);
}

if ($yo) {
    $id = (int)$yo['id'];

    $q = $db->prepare('SELECT COUNT(*) FROM simpatizantes WHERE referido_por = :id');
    $q->execute(['id' => $id]);
    $invitados = (int)$q->fetchColumn();

    // Segundo nivel: los invitados de mis invitados también son fruto de mi red
    $q = $db->prepare(
        'SELECT COUNT(*) FROM simpatizantes
         WHERE referido_por IN (SELECT id FROM (SELECT id FROM simpatizantes WHERE referido_por = :id) t)'
    );
    $q->execute(['id' => $id]);
    $segundoNivel = (int)$q->fetchColumn();

    $q = $db->prepare('SELECT nombre, created_at FROM simpatizantes WHERE referido_por = :id ORDER BY created_at DESC LIMIT 5');
    $q->execute(['id' => $id]);
    $ultimos = $q->fetchAll();

    // Ranking: los 10 promotores con más invitados directos
    $ranking = $db->query(
        'SELECT p.id, p.nombre, COUNT(r.id) AS invitados
         FROM simpatizantes r JOIN simpatizantes p ON p.id = r.referido_por
         GROUP BY p.id, p.nombre
         ORDER BY invitados DESC, MIN(r.created_at) ASC
         LIMIT 10'
    )->fetchAll();

    $miPuesto = null;
    if ($invitados > 0) {
        $q = $db->prepare(
            'SELECT COUNT(*) FROM (
               SELECT referido_por FROM simpatizantes WHERE referido_por IS NOT NULL
               GROUP BY referido_por HAVING COUNT(*) > :mios) t'
        );
        $q->execute(['mios' => $invitados]);
        $miPuesto = (int)$q->fetchColumn() + 1;
    }
    $enRanking = in_array($id, array_map('intval', array_column($ranking, 'id')), true);

    $nivel = promotor_nivel($invitados);

    // Enlace personal (mismo dominio y carpeta que esta página)
    $https  = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host   = preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $_SERVER['HTTP_HOST'] ?? '') ? $_SERVER['HTTP_HOST'] : 'localhost';
    $base   = ($https ? 'https' : 'http') . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/';
    $enlace = $base . '?ref=' . rawurlencode($yo['codigo_promotor']) . '#sumate';
    $textoWa = '¡Hola! 👋 Me sumé a la campaña de Diana Lucía Montes a la Alcaldía de Garzón. '
             . 'Súmate tú también, toma menos de un minuto: ' . $enlace;

    $tareas = [
        ['Únete a la red de Diana', true, null],
        ['Comparte tu enlace con tu familia por WhatsApp', $invitados >= 1, 'wa'],
        ['Suma tu primer invitado', $invitados >= 1, null],
        ['Llega a 3 invitados: nivel Promotor ⭐', $invitados >= 3, null],
        ['Llega a 10 invitados: Súper Promotor 🚀', $invitados >= 10, null],
        ['Descarga tu QR y compártelo en tu barrio o negocio', false, 'qr'],
        ['Llega a 25 invitados: Embajador 🏆', $invitados >= 25, null],
    ];
    $hechas = count(array_filter($tareas, fn($t) => $t[1]));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>Mi panel de promotor · Diana Lucía Montes</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --rosa:#E0186C; --rosa-osc:#C0105A; --rosa-soft:#FDEBF3;
    --violeta:#7C3AED; --violeta-soft:#F3EEFD;
    --ink:#101226; --gris:#5A6072; --linea:#E9EBF2; --fondo-2:#F8F9FC; --verde:#16A34A;
    --grad:linear-gradient(120deg,#E0186C 0%,#7C3AED 100%);
    --sombra:0 1px 2px rgba(16,18,38,.05),0 12px 32px rgba(16,18,38,.07);
    --head:'Plus Jakarta Sans',system-ui,sans-serif; --body:'Inter',system-ui,sans-serif;
  }
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:var(--body);background:var(--fondo-2);color:var(--ink);font-size:14px;line-height:1.5}
  h1,h2,h3{font-family:var(--head)}
  a{color:var(--rosa)}
  .wrap{max-width:560px;margin:0 auto;padding:0 16px 40px}
  .hero{background:var(--grad);color:#fff;padding:26px 16px 70px;text-align:center}
  .hero .kicker{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;opacity:.85}
  .hero h1{font-size:24px;margin:6px 0 4px}
  .hero p{opacity:.9;font-size:13.5px}
  .card{background:#fff;border-radius:18px;box-shadow:var(--sombra);padding:18px;margin-top:14px}
  .card.sube{margin-top:-52px}
  .card h2{font-size:16px;margin-bottom:10px;display:flex;align-items:center;gap:8px}
  .nivel{display:flex;align-items:center;gap:14px}
  .nivel .emoji{font-size:38px;line-height:1}
  .nivel b{font-family:var(--head);font-size:19px;display:block}
  .nivel span{color:var(--gris);font-size:12.5px}
  .barra{height:14px;background:var(--rosa-soft);border-radius:99px;overflow:hidden;margin:14px 0 6px}
  .barra i{display:block;height:100%;background:var(--grad);border-radius:99px;transition:width .8s}
  .barra-txt{display:flex;justify-content:space-between;font-size:12px;color:var(--gris)}
  .kpis{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px}
  .kpi{background:var(--fondo-2);border:1px solid var(--linea);border-radius:14px;padding:12px;text-align:center}
  .kpi b{font-family:var(--head);font-size:26px;display:block;color:var(--rosa-osc)}
  .kpi span{font-size:11.5px;color:var(--gris)}
  .link{background:var(--fondo-2);border:1px dashed #F0A9C8;border-radius:10px;padding:9px 11px;font-size:12.5px;font-weight:600;word-break:break-all}
  .btns{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
  .btn{flex:1;min-width:140px;display:inline-flex;align-items:center;justify-content:center;gap:7px;border:none;border-radius:12px;padding:12px 14px;font-family:var(--head);font-weight:700;font-size:13.5px;text-decoration:none;cursor:pointer}
  .btn-wa{background:#1FAF5A;color:#fff}
  .btn-soft{background:#fff;color:var(--ink);border:1.5px solid var(--linea)}
  .btn-rosa{background:var(--rosa);color:#fff}
  .qr-wrap{display:flex;gap:14px;align-items:center;margin-top:14px;flex-wrap:wrap}
  .qr{width:150px;height:150px;background:#fff;border:1px solid var(--linea);border-radius:12px;padding:6px;flex:none}
  .qr svg{width:100%;height:100%;display:block}
  .qr-txt{flex:1;min-width:160px;font-size:12.5px;color:var(--gris)}
  .tareas li{list-style:none;display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--linea);font-size:13.5px}
  .tareas li:last-child{border-bottom:none}
  .tareas .chk{width:22px;height:22px;border-radius:7px;border:2px solid var(--linea);display:flex;align-items:center;justify-content:center;font-size:12px;flex:none}
  .tareas li.ok .chk{background:var(--verde);border-color:var(--verde);color:#fff}
  .tareas li.ok span{color:var(--gris);text-decoration:line-through}
  .tareas button{margin-left:auto;background:var(--rosa-soft);color:var(--rosa-osc);border:none;border-radius:8px;padding:5px 10px;font-weight:700;font-size:12px;cursor:pointer}
  .rank{width:100%;border-collapse:collapse}
  .rank td{padding:8px 4px;border-bottom:1px solid var(--linea);font-size:13.5px}
  .rank td.pos{width:36px;font-family:var(--head);font-weight:800;color:var(--gris)}
  .rank td.num{text-align:right;font-weight:700}
  .rank tr.yo td{background:var(--rosa-soft);color:var(--rosa-osc);font-weight:700}
  .rank tr.sep td{text-align:center;color:var(--gris);border-bottom:none}
  .vacio{color:var(--gris);font-size:13px;text-align:center;padding:10px 0}
  .ultimos li{list-style:none;display:flex;justify-content:space-between;padding:6px 0;font-size:13px;border-bottom:1px solid var(--linea)}
  .ultimos li:last-child{border-bottom:none}
  .ultimos span{color:var(--gris);font-size:12px}
  .pie{text-align:center;color:var(--gris);font-size:11.5px;margin-top:18px}
  .error{text-align:center;padding:60px 16px}
  .error h1{font-size:22px;margin:10px 0 6px}
</style>
</head>
<body>
<?php if (!$yo): ?>
  <div class="wrap error">
    <div style="font-size:44px">🔒</div>
    <h1>Este enlace no es válido</h1>
    <p style="color:var(--gris)">Revisa que hayas copiado completo el enlace de tu panel, o pídele a tu líder que te lo reenvíe.</p>
    <p style="margin-top:16px"><a href="./#sumate">Ir a la página de la campaña</a></p>
  </div>
<?php else: ?>
  <header class="hero">
    <div class="kicker">Mi panel de promotor</div>
    <h1>¡Hola, <?= e(strtok($yo['nombre'], ' ')) ?>! 👋</h1>
    <p>Cada persona que invitas acerca a Diana a la Alcaldía de Garzón.</p>
  </header>

  <main class="wrap">
    <!-- Nivel y barra de progreso -->
    <section class="card sube">
      <div class="nivel">
        <span class="emoji"><?= $nivel['actual'][2] ?></span>
        <div>
          <b><?= e($nivel['actual'][1]) ?></b>
          <?php if ($nivel['siguiente']): ?>
            <span>Te faltan <b style="display:inline;font-size:inherit"><?= $nivel['faltan'] ?></b> invitado<?= $nivel['faltan'] === 1 ? '' : 's' ?> para ser <?= e($nivel['siguiente'][1]) ?> <?= $nivel['siguiente'][2] ?></span>
          <?php else: ?>
            <span>¡Llegaste al nivel más alto! Gracias por tanto 💜</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="barra" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $nivel['progreso'] ?>"><i style="width:<?= $nivel['progreso'] ?>%"></i></div>
      <div class="barra-txt">
        <span><?= e($nivel['actual'][1]) ?></span>
        <span><?= $nivel['siguiente'] ? e($nivel['siguiente'][1]) . ' · ' . $nivel['siguiente'][0] . ' invitados' : '🏆' ?></span>
      </div>
      <div class="kpis">
        <div class="kpi"><b><?= $invitados ?></b><span>personas invitadas por ti</span></div>
        <div class="kpi"><b><?= $miPuesto ? '#' . $miPuesto : '—' ?></b><span>tu puesto en el ranking</span></div>
      </div>
      <?php if ($segundoNivel > 0): ?>
        <p class="pie" style="margin-top:10px">✨ Además, tus invitados ya sumaron <b><?= $segundoNivel ?></b> persona<?= $segundoNivel === 1 ? '' : 's' ?> más a tu red.</p>
      <?php endif; ?>
    </section>

    <!-- Enlace personal y QR -->
    <section class="card">
      <h2>🔗 Tu enlace personal</h2>
      <div class="link" id="enlace"><?= e($enlace) ?></div>
      <div class="btns">
        <a class="btn btn-wa" id="btnWa" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($textoWa) ?>">📲 Invitar por WhatsApp</a>
        <button type="button" class="btn btn-soft" id="btnCopiar">Copiar enlace</button>
      </div>
      <div class="qr-wrap">
        <div class="qr" id="qr"></div>
        <div class="qr-txt">
          <b style="color:var(--ink)">Tu QR es tu llave maestra.</b><br>
          Quien lo escanee llega directo al registro y queda en tu red.
          <div class="btns"><button type="button" class="btn btn-rosa" id="btnQr">⬇ Descargar QR</button></div>
        </div>
      </div>
    </section>

    <!-- Lista de tareas -->
    <section class="card">
      <h2>✅ Tus tareas <span style="margin-left:auto;font-size:12px;color:var(--gris)"><span id="hechas"><?= $hechas ?></span>/<?= count($tareas) ?></span></h2>
      <ul class="tareas">
        <?php foreach ($tareas as [$texto, $ok, $accion]): ?>
        <li class="<?= $ok ? 'ok' : '' ?>"<?= $accion ? ' data-accion="' . $accion . '"' : '' ?>>
          <span class="chk"><?= $ok ? '✓' : '' ?></span>
          <span><?= e($texto) ?></span>
          <?php if (!$ok && $accion === 'wa'): ?><button type="button" onclick="document.getElementById('btnWa').click()">Compartir</button><?php endif; ?>
          <?php if (!$ok && $accion === 'qr'): ?><button type="button" onclick="document.getElementById('btnQr').click()">Descargar</button><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <!-- Ranking -->
    <section class="card">
      <h2>🏆 Ranking de promotores</h2>
      <?php if (!$ranking): ?>
        <p class="vacio">Aún nadie ha invitado a alguien. ¡Sé el primero en aparecer aquí!</p>
      <?php else: ?>
        <table class="rank">
          <?php foreach ($ranking as $i => $r): ?>
          <tr class="<?= (int)$r['id'] === $id ? 'yo' : '' ?>">
            <td class="pos"><?= ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) ?></td>
            <td><?= e(promotor_nombre_corto($r['nombre'])) ?><?= (int)$r['id'] === $id ? ' (tú)' : '' ?></td>
            <td class="num"><?= (int)$r['invitados'] ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if ($miPuesto && !$enRanking): ?>
          <tr class="sep"><td colspan="3">···</td></tr>
          <tr class="yo"><td class="pos"><?= $miPuesto ?></td><td><?= e(promotor_nombre_corto($yo['nombre'])) ?> (tú)</td><td class="num"><?= $invitados ?></td></tr>
          <?php endif; ?>
        </table>
      <?php endif; ?>
    </section>

    <!-- Últimos invitados -->
    <?php if ($ultimos): ?>
    <section class="card">
      <h2>🤝 Tus últimos invitados</h2>
      <ul class="ultimos">
        <?php foreach ($ultimos as $u): ?>
        <li><?= e(promotor_nombre_corto($u['nombre'])) ?> <span><?= date('d/m/Y', strtotime($u['created_at'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <p class="pie">🔒 Este panel es personal: no compartas este enlace (el de tu panel).<br>Para invitar, usa tu enlace personal o tu QR.</p>
  </main>

  <script src="assets/vendor/qrcode.js"></script>
  <script>
  const enlace = <?= json_encode($enlace, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const clave  = 'tareas_' + <?= json_encode($yo['codigo_promotor']) ?>;

  /* Tareas que se cumplen con un clic (compartir / descargar QR): se recuerdan
     en este navegador. Si el almacenamiento no está disponible, no pasa nada. */
  function leerHechas() { try { return JSON.parse(localStorage.getItem(clave)) || []; } catch (e) { return []; } }
  function marcar(accion, guardar) {
    const li = document.querySelector('.tareas li[data-accion="' + accion + '"]');
    if (!li || li.classList.contains('ok')) return;
    li.classList.add('ok');
    li.querySelector('.chk').textContent = '✓';
    const b = li.querySelector('button'); if (b) b.remove();
    const n = document.getElementById('hechas'); n.textContent = +n.textContent + 1;
    if (guardar) { try { localStorage.setItem(clave, JSON.stringify(leerHechas().concat(accion))); } catch (e) {} }
  }
  leerHechas().forEach(a => marcar(a, false));
  document.getElementById('btnWa').addEventListener('click', () => marcar('wa', true));

  document.getElementById('btnCopiar').addEventListener('click', function () {
    navigator.clipboard.writeText(enlace).then(() => {
      this.textContent = '¡Copiado!';
      setTimeout(() => this.textContent = 'Copiar enlace', 1600);
    });
  });

  if (window.qrcode) {
    const q = qrcode(0, 'M'); q.addData(enlace); q.make();
    document.getElementById('qr').innerHTML = q.createSvgTag(4, 2);
    document.getElementById('btnQr').addEventListener('click', () => {
      const a = document.createElement('a');
      a.href = q.createDataURL(10, 4);
      a.download = 'mi-qr-diana-montes.gif';
      a.click();
      marcar('qr', true);
    });
  } else {
    document.querySelector('.qr-wrap').style.display = 'none';
  }
  </script>
<?php endif; ?>
</body>
</html>
