-- ============================================================
-- ContaUBI — Migración 001
-- Agrega soporte multimoneda + historial de tipos de cambio
-- (Aplicar sólo si ya tenés datos en la BD; si recién instalás,
--  ejecutá db/schema.sql que ya trae todo.)
-- ============================================================

USE contaubi;

-- ------------------------------------------------------------
-- 1) Tabla histórica de tipos de cambio (Bs/USD y UFV)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipos_cambio (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    fecha     DATE NOT NULL,
    tasa_usd  DECIMAL(14,6) NOT NULL DEFAULT 0,
    ufv       DECIMAL(14,6) NOT NULL DEFAULT 0,
    nota      VARCHAR(160) DEFAULT '',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_fecha (fecha),
    KEY idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tipos_cambio (fecha, tasa_usd, ufv, nota)
VALUES (CURDATE(), 6.960000, 2.480000, 'Tasas iniciales — actualizar diariamente');

-- ------------------------------------------------------------
-- 2) Columnas nuevas en comprobantes (multimoneda + tc por comprobante)
-- ------------------------------------------------------------
ALTER TABLE comprobantes
  MODIFY COLUMN moneda ENUM('BOB','USD','UFV') NOT NULL DEFAULT 'BOB';

ALTER TABLE comprobantes
  ADD COLUMN IF NOT EXISTS tc_usd          DECIMAL(14,6) NOT NULL DEFAULT 0 AFTER moneda,
  ADD COLUMN IF NOT EXISTS tc_ufv          DECIMAL(14,6) NOT NULL DEFAULT 0 AFTER tc_usd,
  ADD COLUMN IF NOT EXISTS tipo_cambio_id  INT NULL                          AFTER tc_ufv,
  ADD KEY IF NOT EXISTS idx_moneda (moneda);

-- ------------------------------------------------------------
-- 3) Ajuste de tamaño de descripción en cuentas
-- ------------------------------------------------------------
ALTER TABLE cuentas
  MODIFY COLUMN descripcion VARCHAR(500) DEFAULT '';
