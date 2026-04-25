-- ============================================================
-- ContaUBI — Sistema Contable Universidad Boliviana de Informática
-- Schema de base de datos (MySQL 5.7+ / 8.0+)
-- ============================================================

CREATE DATABASE IF NOT EXISTS contaubi
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE contaubi;

-- ------------------------------------------------------------
-- Empresa (configuración global, una sola fila)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS movimientos;
DROP TABLE IF EXISTS comprobantes;
DROP TABLE IF EXISTS cuentas;
DROP TABLE IF EXISTS empresa;

CREATE TABLE empresa (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(150) NOT NULL DEFAULT 'Universidad Boliviana de Informática',
    nit         VARCHAR(20)  NOT NULL DEFAULT '0000000000',
    ciudad      VARCHAR(80)  NOT NULL DEFAULT 'La Paz',
    direccion   VARCHAR(200) DEFAULT '',
    telefono    VARCHAR(40)  DEFAULT '',
    email       VARCHAR(120) DEFAULT '',
    moneda      VARCHAR(10)  NOT NULL DEFAULT 'Bs.',
    ejercicio   INT          NOT NULL DEFAULT 2026,
    fecha_inicio_ejercicio DATE NOT NULL DEFAULT '2026-01-01',
    fecha_cierre_ejercicio DATE NOT NULL DEFAULT '2026-12-31',
    logo_texto  VARCHAR(10)  NOT NULL DEFAULT 'UBI',
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO empresa (nombre, nit, ciudad, direccion, telefono, email, moneda, ejercicio, fecha_inicio_ejercicio, fecha_cierre_ejercicio)
VALUES ('Universidad Boliviana de Informática', '1023456789', 'La Paz', 'Av. Arce N° 2799', '+591 2 2123456', 'contabilidad@ubi.edu.bo',
        'Bs.', 2026, '2026-01-01', '2026-12-31');

-- ------------------------------------------------------------
-- Plan de Cuentas (PUC Bolivia, código de 8 dígitos)
--   codigo = G S CC SS AA
--   G  Clase     (1-5)
--   S  Grupo     (1-9)
--   CC Cuenta    (01-99)
--   SS Subcuenta (01-99)
--   AA Auxiliar  (00-99)  · 00 = mayor sin auxiliar
-- ------------------------------------------------------------
CREATE TABLE cuentas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    codigo      VARCHAR(8)   NOT NULL,
    clase       TINYINT      NOT NULL,
    grupo       TINYINT      NOT NULL,
    cuenta      TINYINT      NOT NULL,
    subcuenta   TINYINT      NOT NULL DEFAULT 0,
    auxiliar    TINYINT      NOT NULL DEFAULT 0,
    nombre      VARCHAR(120) NOT NULL,
    descripcion TEXT,
    naturaleza  ENUM('DEUDORA','ACREEDORA') NOT NULL,
    es_imputable TINYINT(1) NOT NULL DEFAULT 1,  -- 1 = se pueden registrar movimientos; 0 = solo agrupación
    activa      TINYINT(1)  NOT NULL DEFAULT 1,
    creado_en   TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_codigo (codigo),
    UNIQUE KEY uniq_nombre_nivel (clase, grupo, cuenta, subcuenta, nombre),
    KEY idx_clase (clase),
    KEY idx_imputable (es_imputable, activa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Comprobantes (cabecera del asiento)
-- ------------------------------------------------------------
CREATE TABLE comprobantes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    numero      VARCHAR(20) NOT NULL,
    tipo        ENUM('INGRESO','EGRESO','TRASPASO','APERTURA','CIERRE','AJUSTE') NOT NULL DEFAULT 'TRASPASO',
    fecha       DATE NOT NULL,
    glosa       VARCHAR(255) NOT NULL,
    moneda      VARCHAR(10) NOT NULL DEFAULT 'Bs.',
    estado      ENUM('BORRADOR','APROBADO','ANULADO') NOT NULL DEFAULT 'BORRADOR',
    total_debe  DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_haber DECIMAL(14,2) NOT NULL DEFAULT 0,
    creado_por  VARCHAR(80) DEFAULT 'sistema',
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_numero (numero),
    KEY idx_fecha (fecha),
    KEY idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Movimientos (líneas del asiento — partida doble)
-- ------------------------------------------------------------
CREATE TABLE movimientos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    comprobante_id  INT NOT NULL,
    cuenta_id       INT NOT NULL,
    debe            DECIMAL(14,2) NOT NULL DEFAULT 0,
    haber           DECIMAL(14,2) NOT NULL DEFAULT 0,
    glosa_linea     VARCHAR(255) DEFAULT '',
    orden           INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_mov_comp   FOREIGN KEY (comprobante_id) REFERENCES comprobantes(id) ON DELETE CASCADE,
    CONSTRAINT fk_mov_cuenta FOREIGN KEY (cuenta_id)      REFERENCES cuentas(id) ON DELETE RESTRICT,
    KEY idx_comp (comprobante_id),
    KEY idx_cuenta (cuenta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
