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
DROP TABLE IF EXISTS usuarios;
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
-- Plan de Cuentas (PUCT Bolivia – Plan Único de Cuentas Tributario)
-- Codificación de 8 dígitos:  C·G·SG·CP·CA  (1+1+2+2+2)
--   C   Clase             1 dígito  (1-5)              cerrado por PUCT
--   G   Grupo             1 dígito                      cerrado por PUCT
--   SG  Subgrupo          2 dígitos                     cerrado por PUCT
--   CP  Cuenta Principal  2 dígitos                     cerrado por PUCT
--   CA  Cuenta Analítica  2 dígitos (00-99)             abierto al contribuyente
-- nivel : 1=Clase 2=Grupo 3=Subgrupo 4=Cta. Principal 5=Cta. Analítica
-- es_puct : 1 = forma parte del PUCT oficial (inmutable)
--           0 = analítica creada por el contribuyente (editable / borrable)
-- es_imputable : sólo el nivel 5 (CA) acepta movimientos contables
-- ------------------------------------------------------------
CREATE TABLE cuentas (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    codigo            VARCHAR(8)   NOT NULL,
    clase             TINYINT      NOT NULL,
    grupo             TINYINT      NOT NULL DEFAULT 0,
    subgrupo          TINYINT      NOT NULL DEFAULT 0,
    cuenta_principal  TINYINT      NOT NULL DEFAULT 0,
    cuenta_analitica  TINYINT      NOT NULL DEFAULT 0,
    nivel             TINYINT      NOT NULL DEFAULT 5,
    nombre            VARCHAR(160) NOT NULL,
    descripcion       TEXT,
    naturaleza        ENUM('DEUDORA','ACREEDORA') NOT NULL,
    es_imputable      TINYINT(1)   NOT NULL DEFAULT 0,
    es_puct           TINYINT(1)   NOT NULL DEFAULT 1,
    activa            TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_codigo (codigo),
    UNIQUE KEY uniq_nombre_nivel (clase, grupo, subgrupo, cuenta_principal, cuenta_analitica, nombre),
    KEY idx_clase (clase),
    KEY idx_nivel (nivel, activa),
    KEY idx_imputable (es_imputable, activa),
    KEY idx_puct (es_puct)
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

-- ------------------------------------------------------------
-- Usuarios (login y control de acceso por rol)
--   admin    → acceso total + gestión de usuarios
--   contador → registra movimientos / crea comprobantes / ve reportes
--   consulta → sólo lectura (dashboard + reportes)
-- Las contraseñas se guardan hasheadas con password_hash() (bcrypt).
-- Los seeds están en db/usuarios.sql (3 usuarios de prueba).
-- ------------------------------------------------------------
CREATE TABLE usuarios (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nombre    VARCHAR(120) NOT NULL,
    usuario   VARCHAR(50)  NOT NULL,
    password  VARCHAR(255) NOT NULL,
    rol       ENUM('admin','contador','consulta') NOT NULL DEFAULT 'consulta',
    estado    ENUM('activo','inactivo')           NOT NULL DEFAULT 'activo',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_usuario (usuario),
    KEY idx_rol (rol),
    KEY idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
