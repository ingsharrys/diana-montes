-- ============================================================================
--  Centro de mando: género, nivel en la red y configuración (meta)
-- ----------------------------------------------------------------------------
--  NORMALMENTE NO HACE FALTA: en el admin, la dirección ve el aviso
--  "Actualizar plataforma" en el Panel y con un clic se aplica esto.
--
--  Úsalo solo si ese botón falla (p. ej. el usuario de la BD no tiene
--  permiso ALTER/CREATE). Requiere haber aplicado antes 001_red_promotores.sql.
--  Después pulsa de nuevo el botón: calcula el nivel en la red de los
--  simpatizantes que ya existen.
-- ============================================================================

ALTER TABLE simpatizantes
  ADD COLUMN genero      ENUM('mujer','hombre','otro','no_dice') NULL,
  ADD COLUMN profundidad TINYINT UNSIGNED NULL,          -- 1 = entró directo; n+1 = invitado por alguien de nivel n
  ADD KEY idx_simp_profundidad (profundidad);

CREATE TABLE IF NOT EXISTS configuracion (
  clave       VARCHAR(60)  NOT NULL PRIMARY KEY,
  valor       VARCHAR(255) NULL,
  actualizado DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4;
