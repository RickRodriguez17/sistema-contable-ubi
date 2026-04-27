-- ============================================================
-- ContaUBI — Tabla de usuarios + 3 usuarios de prueba
-- (login con control de acceso por rol)
--
-- Roles soportados:
--   admin    → acceso total
--   contador → registra movimientos / crea comprobantes / ve reportes
--   consulta → sólo lectura
--
-- Las contraseñas están hasheadas con password_hash() (bcrypt).
-- Los 3 usuarios de prueba comparten la contraseña: 123456
-- ============================================================

USE contaubi;

DROP TABLE IF EXISTS usuarios;

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

-- ------------------------------------------------------------
-- Usuarios de prueba (contraseña en todos: 123456)
-- ------------------------------------------------------------
INSERT INTO usuarios (nombre, usuario, password, rol, estado) VALUES
('Administrador',   'admin',    '$2y$10$z/OEsjRTRx5cx8olrp6nS.Jq54daDL/B1v4OzoCzK./o7djwfHfXy', 'admin',    'activo'),
('Contador UBI',    'contador', '$2y$10$5JDeStmtkRQFQe8ssshtguCXRxhVtgBAL5mfV8R2fmOU/d769yq6S', 'contador', 'activo'),
('Usuario Consulta','consulta', '$2y$10$L3.ipPzieR/KYyXiazDGru5rCWyTxf7t/GPqQwwBAVuFh9PlDLWIe', 'consulta', 'activo');
