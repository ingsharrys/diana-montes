-- ============================================================================
--  Base de votos real: escala de compromiso, verificación y puestos
-- ----------------------------------------------------------------------------
--  NORMALMENTE NO HACE FALTA: en el admin, la dirección ve el aviso
--  "Actualizar plataforma" en la Vista rápida y con un clic se aplica esto.
--
--  Úsalo solo si ese botón falla (p. ej. el usuario de la BD no tiene
--  permiso ALTER). Requiere haber aplicado antes 001 y 002.
-- ============================================================================

-- Escala de compromiso: indeciso < simpatizante < voto seguro < voluntario < testigo
-- ("votante confirmado" de la escala anterior pasa a "voto seguro")
UPDATE simpatizantes SET nivel = 'simpatizante' WHERE nivel IS NULL OR nivel = '';
ALTER TABLE simpatizantes MODIFY nivel ENUM('simpatizante','voluntario','votante_confirmado','indeciso','voto_seguro','testigo') NOT NULL DEFAULT 'simpatizante';
UPDATE simpatizantes SET nivel = 'voto_seguro' WHERE nivel = 'votante_confirmado';
ALTER TABLE simpatizantes MODIFY nivel ENUM('indeciso','simpatizante','voto_seguro','voluntario','testigo') NOT NULL DEFAULT 'simpatizante';

-- Verificación por llamada (quién y cuándo confirmó el compromiso)
ALTER TABLE simpatizantes
  ADD COLUMN verificado_at  DATETIME NULL,
  ADD COLUMN verificado_por INT NULL;

-- Puestos de votación: mesas y potencial electoral (habilitados para votar)
ALTER TABLE puestos_votacion
  ADD COLUMN mesas     SMALLINT UNSIGNED NULL,
  ADD COLUMN potencial INT UNSIGNED NULL;
