
<?php

// =======================================
// HISTORIAS CIUDADANAS PUBLICADAS
// =======================================

$historiasPublicadas = [];

try {

    $sql = "
        SELECT
            id,
            nombre,
            mensaje,
            created_at
        FROM historias_ciudadanas
        WHERE estado = 'aprobada'
          AND consentimiento_datos = 1
          AND autoriza_publicacion = 1
        ORDER BY created_at DESC, id DESC
        LIMIT 6
    ";

    $historiasPublicadas = db()
        ->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        '[historias_publicadas] ' . $e->getMessage()
    );

}

?>

<?php if (!empty($historiasPublicadas)): ?>

<div class="container historias-publicadas"
     id="historias-publicadas">

    <!-- ENCABEZADO -->
    <header class="historias-publicadas-head">

        <p class="eyebrow">VOCES DE GARZÓN</p>

        <h2 class="section-title">
            Historias que nos mueven
        </h2>

        <p class="section-lead">
            Cada persona tiene una historia que merece
            ser escuchada. Conoce algunas de las
            experiencias que nuestra comunidad ha
            decidido compartir.
        </p>

    </header>

    <!-- LISTADO -->
    <div class="historias-publicadas-grid">

        <?php foreach ($historiasPublicadas as $historia): ?>

            <?php

                $nombre = $historia['nombre'] ?? 'Anónimo';
                $mensaje = trim((string) $historia['mensaje']);
                $fecha = $historia['created_at'] ?? 'Sin fecha';

                $limite = 170;

                $esLarga = mb_strlen($mensaje) > $limite;

                $resumen = $esLarga
                    ? mb_substr($mensaje, 0, $limite)
                    : $mensaje;

            ?>

            <article class="historia-publica-card">
                <div class="historia-publica-icon"><i class="fas fa-quote-left" aria-hidden="true"></i> <span class="historia-publica-label">RELATO CIUDADANO</span></div>
                <span class="historia-publica-label"><?= $nombre ?></span>
                <div class="historia-publica-contenido">
                    <p class="historia-publica-resumen"><?= nl2br(e($resumen)) ?><?= $esLarga ? '…' : '' ?></p>
                    <?php if ($esLarga): ?>
                        <details class="historia-publica-detalle">
                            <summary>Leer historia completa <i class="fas fa-arrow-right" aria-hidden="true"></i></summary>
                            <p class="historia-publica-texto"><?= nl2br(e($mensaje)) ?></p>
                        </details>
                    <?php endif; ?>
                </div>
                <div class="historia-publica-footer"><i class="fas fa-heart" aria-hidden="true"></i> Una historia de Garzón - <?= $fecha ?></div>
            </article>

        <?php endforeach; ?>

    </div>

</div>

<?php endif; ?>
