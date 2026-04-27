-- ============================================================
-- ContaUBI — Comprobantes de demostración
-- (Opcional. Carga 4 asientos aprobados para tener datos en los reportes.)
-- Códigos PUCT 8 dígitos:  C·G·SG·CP·CA  (1+1+2+2+2)
-- ============================================================
USE contaubi;

DELETE FROM movimientos;
DELETE FROM comprobantes;
ALTER TABLE comprobantes AUTO_INCREMENT = 1;
ALTER TABLE movimientos  AUTO_INCREMENT = 1;

-- 1) Aporte inicial de capital — 50,000 Bs en banco
INSERT INTO comprobantes (numero, tipo, fecha, glosa, moneda, estado, total_debe, total_haber)
VALUES ('2026-000001','APERTURA','2026-01-02','Aporte inicial de socios al banco','Bs.','APROBADO',50000,50000);
SET @c1 = LAST_INSERT_ID();
INSERT INTO movimientos (comprobante_id, cuenta_id, debe, haber, glosa_linea, orden) VALUES
(@c1, (SELECT id FROM cuentas WHERE codigo='11010201'), 50000, 0, 'Depósito aporte socios', 1),
(@c1, (SELECT id FROM cuentas WHERE codigo='31010101'), 0, 50000, 'Capital inicial', 2);

-- 2) Compra de equipo de computación — 8,400 Bs pagado con cheque
INSERT INTO comprobantes (numero, tipo, fecha, glosa, moneda, estado, total_debe, total_haber)
VALUES ('2026-000002','EGRESO','2026-01-15','Compra de 3 computadoras para oficina','Bs.','APROBADO',8400,8400);
SET @c2 = LAST_INSERT_ID();
INSERT INTO movimientos (comprobante_id, cuenta_id, debe, haber, glosa_linea, orden) VALUES
(@c2, (SELECT id FROM cuentas WHERE codigo='12030301'), 8400, 0, 'Equipos PC', 1),
(@c2, (SELECT id FROM cuentas WHERE codigo='11010201'), 0, 8400, 'Pago con cheque', 2);

-- 3) Venta de servicios — 12,000 Bs cobrados al contado
INSERT INTO comprobantes (numero, tipo, fecha, glosa, moneda, estado, total_debe, total_haber)
VALUES ('2026-000003','INGRESO','2026-02-05','Servicios prestados febrero 2026','Bs.','APROBADO',12000,12000);
SET @c3 = LAST_INSERT_ID();
INSERT INTO movimientos (comprobante_id, cuenta_id, debe, haber, glosa_linea, orden) VALUES
(@c3, (SELECT id FROM cuentas WHERE codigo='11010101'), 12000, 0, 'Cobranza en efectivo', 1),
(@c3, (SELECT id FROM cuentas WHERE codigo='41010201'), 0, 12000, 'Servicios prestados', 2);

-- 4) Pago de sueldos — 5,000 Bs vía banco
INSERT INTO comprobantes (numero, tipo, fecha, glosa, moneda, estado, total_debe, total_haber)
VALUES ('2026-000004','EGRESO','2026-02-28','Sueldos del personal de febrero','Bs.','APROBADO',5000,5000);
SET @c4 = LAST_INSERT_ID();
INSERT INTO movimientos (comprobante_id, cuenta_id, debe, haber, glosa_linea, orden) VALUES
(@c4, (SELECT id FROM cuentas WHERE codigo='52010101'), 5000, 0, 'Sueldos febrero', 1),
(@c4, (SELECT id FROM cuentas WHERE codigo='11010201'), 0, 5000, 'Pago vía banco', 2);
