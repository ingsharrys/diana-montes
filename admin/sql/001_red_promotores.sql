-- ============================================================================
--  Red de Súper Promotores + mapa de geolocalización
-- ----------------------------------------------------------------------------
--  NORMALMENTE NO HACE FALTA: en el admin, la dirección ve el aviso
--  "Activar red de promotores" en el Panel y con un clic se aplica esto.
--
--  Úsalo solo si ese botón falla (p. ej. el usuario de la BD no tiene
--  permiso ALTER): ejecútalo en phpMyAdmin y luego pulsa de nuevo el botón
--  en el Panel, que asigna el enlace personal a los simpatizantes existentes.
-- ============================================================================

ALTER TABLE simpatizantes
  ADD COLUMN codigo_promotor VARCHAR(16) NULL,   -- código público: ?ref=pxxxxxx
  ADD COLUMN token_panel     CHAR(32)    NULL,   -- llave secreta de promotor.php?t=
  ADD COLUMN referido_por    INT         NULL,   -- simpatizante que lo invitó
  ADD COLUMN lat             DECIMAL(9,6) NULL,  -- ubicación aproximada (~100 m)
  ADD COLUMN lng             DECIMAL(9,6) NULL,
  ADD UNIQUE KEY uq_simp_codigo_promotor (codigo_promotor),
  ADD UNIQUE KEY uq_simp_token_panel (token_panel),
  ADD KEY idx_simp_referido_por (referido_por);
