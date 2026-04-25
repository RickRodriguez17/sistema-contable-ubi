-- ============================================================
-- ContaUBI — Plan Único de Cuentas (PUC) Bolivia
-- Códigos de 8 dígitos: G S CC SS AA
-- Naturaleza: DEUDORA = saldo normal en el debe (Activos, Egresos)
--             ACREEDORA = saldo normal en el haber (Pasivos, Patrimonio, Ingresos)
-- es_imputable=0: sólo agrupación (no acepta movimientos)
-- es_imputable=1: cuenta de movimiento
-- ============================================================
USE contaubi;

-- ------------------------------------------------------------
-- CLASE 1 — ACTIVO (DEUDORA)
-- ------------------------------------------------------------
INSERT INTO cuentas (codigo, clase, grupo, cuenta, subcuenta, auxiliar, nombre, descripcion, naturaleza, es_imputable) VALUES
-- 1.1 DISPONIBLE
('11000000', 1, 0, 0, 0, 0, 'ACTIVO',                              'Recursos económicos de la entidad',           'DEUDORA', 0),
('11010000', 1, 1, 0, 0, 0, 'DISPONIBLE',                          'Efectivo y equivalentes',                     'DEUDORA', 0),
('11010100', 1, 1, 1, 1, 0, 'Caja',                                'Dinero en efectivo',                          'DEUDORA', 1),
('11010101', 1, 1, 1, 1, 1, 'Caja Moneda Nacional',                'Efectivo en bolivianos',                      'DEUDORA', 1),
('11010102', 1, 1, 1, 1, 2, 'Caja Moneda Extranjera',              'Efectivo en divisas',                         'DEUDORA', 1),
('11010103', 1, 1, 1, 1, 3, 'Caja Chica',                          'Fondo fijo de caja chica',                    'DEUDORA', 1),
('11020100', 1, 1, 2, 1, 0, 'Bancos',                              'Cuentas bancarias',                           'DEUDORA', 1),
('11020101', 1, 1, 2, 1, 1, 'Bancos Moneda Nacional',              'Cuentas corrientes en Bs',                    'DEUDORA', 1),
('11020102', 1, 1, 2, 1, 2, 'Bancos Moneda Extranjera',            'Cuentas corrientes en USD',                   'DEUDORA', 1),
('11030100', 1, 1, 3, 1, 0, 'Inversiones Temporarias',             'Depósitos a plazo, valores',                  'DEUDORA', 1),
-- 1.2 EXIGIBLE
('12010000', 1, 2, 0, 0, 0, 'EXIGIBLE',                            'Cuentas y documentos por cobrar',             'DEUDORA', 0),
('12010100', 1, 2, 1, 1, 0, 'Cuentas por Cobrar Comerciales',      'Clientes',                                    'DEUDORA', 1),
('12010101', 1, 2, 1, 1, 1, 'Clientes Nacionales',                 '',                                            'DEUDORA', 1),
('12010102', 1, 2, 1, 1, 2, 'Clientes del Exterior',               '',                                            'DEUDORA', 1),
('12020100', 1, 2, 2, 1, 0, 'Documentos por Cobrar',               'Letras y pagarés',                            'DEUDORA', 1),
('12030100', 1, 2, 3, 1, 0, 'Anticipos a Proveedores',             'Pagos por adelantado a proveedores',          'DEUDORA', 1),
('12040100', 1, 2, 4, 1, 0, 'Préstamos al Personal',               'Préstamos otorgados a empleados',             'DEUDORA', 1),
('12050100', 1, 2, 5, 1, 0, 'Previsión Cuentas Incobrables',       'Estimación de deudores dudosos',              'ACREEDORA', 1),
-- 1.3 REALIZABLE
('13010000', 1, 3, 0, 0, 0, 'REALIZABLE',                          'Inventarios',                                 'DEUDORA', 0),
('13010100', 1, 3, 1, 1, 0, 'Inventario de Mercaderías',           'Mercaderías para la venta',                   'DEUDORA', 1),
('13020100', 1, 3, 2, 1, 0, 'Inventario de Materia Prima',         '',                                            'DEUDORA', 1),
('13030100', 1, 3, 3, 1, 0, 'Inventario Productos en Proceso',     '',                                            'DEUDORA', 1),
('13040100', 1, 3, 4, 1, 0, 'Inventario Productos Terminados',     '',                                            'DEUDORA', 1),
-- 1.4 ACTIVO FIJO
('14010000', 1, 4, 0, 0, 0, 'ACTIVO FIJO',                         'Bienes de uso',                               'DEUDORA', 0),
('14010100', 1, 4, 1, 1, 0, 'Muebles y Enseres',                   '',                                            'DEUDORA', 1),
('14020100', 1, 4, 2, 1, 0, 'Equipos de Oficina',                  '',                                            'DEUDORA', 1),
('14030100', 1, 4, 3, 1, 0, 'Equipos de Computación',              '',                                            'DEUDORA', 1),
('14040100', 1, 4, 4, 1, 0, 'Vehículos',                           '',                                            'DEUDORA', 1),
('14050100', 1, 4, 5, 1, 0, 'Inmuebles',                           'Terrenos y edificios',                        'DEUDORA', 1),
('14050101', 1, 4, 5, 1, 1, 'Terrenos',                            '',                                            'DEUDORA', 1),
('14050102', 1, 4, 5, 1, 2, 'Edificios',                           '',                                            'DEUDORA', 1),
('14060100', 1, 4, 6, 1, 0, 'Depreciación Acumulada Activo Fijo',  'Cuenta regularizadora',                       'ACREEDORA', 1),
-- 1.5 DIFERIDO
('15010000', 1, 5, 0, 0, 0, 'DIFERIDO',                            'Cargos diferidos',                            'DEUDORA', 0),
('15010100', 1, 5, 1, 1, 0, 'Gastos Pagados por Adelantado',       '',                                            'DEUDORA', 1),
('15010101', 1, 5, 1, 1, 1, 'Alquileres Pagados por Adelantado',   '',                                            'DEUDORA', 1),
('15010102', 1, 5, 1, 1, 2, 'Seguros Pagados por Adelantado',      '',                                            'DEUDORA', 1),
('15020100', 1, 5, 2, 1, 0, 'Crédito Fiscal IVA',                  'IVA a favor',                                 'DEUDORA', 1);

-- ------------------------------------------------------------
-- CLASE 2 — PASIVO (ACREEDORA)
-- ------------------------------------------------------------
INSERT INTO cuentas (codigo, clase, grupo, cuenta, subcuenta, auxiliar, nombre, descripcion, naturaleza, es_imputable) VALUES
('21000000', 2, 0, 0, 0, 0, 'PASIVO',                              'Obligaciones de la entidad',                  'ACREEDORA', 0),
('21010000', 2, 1, 0, 0, 0, 'PASIVO CORRIENTE',                    'Obligaciones a corto plazo',                  'ACREEDORA', 0),
('21010100', 2, 1, 1, 1, 0, 'Proveedores',                         '',                                            'ACREEDORA', 1),
('21010101', 2, 1, 1, 1, 1, 'Proveedores Nacionales',              '',                                            'ACREEDORA', 1),
('21010102', 2, 1, 1, 1, 2, 'Proveedores del Exterior',            '',                                            'ACREEDORA', 1),
('21020100', 2, 1, 2, 1, 0, 'Cuentas por Pagar',                   '',                                            'ACREEDORA', 1),
('21030100', 2, 1, 3, 1, 0, 'Documentos por Pagar',                '',                                            'ACREEDORA', 1),
('21040100', 2, 1, 4, 1, 0, 'Impuestos por Pagar',                 '',                                            'ACREEDORA', 1),
('21040101', 2, 1, 4, 1, 1, 'IVA Débito Fiscal',                   '',                                            'ACREEDORA', 1),
('21040102', 2, 1, 4, 1, 2, 'IT por Pagar',                        'Impuesto a las Transacciones',                'ACREEDORA', 1),
('21040103', 2, 1, 4, 1, 3, 'IUE por Pagar',                       'Impuesto sobre las Utilidades',               'ACREEDORA', 1),
('21040104', 2, 1, 4, 1, 4, 'RC-IVA por Pagar',                    '',                                            'ACREEDORA', 1),
('21050100', 2, 1, 5, 1, 0, 'Sueldos por Pagar',                   '',                                            'ACREEDORA', 1),
('21060100', 2, 1, 6, 1, 0, 'Beneficios Sociales por Pagar',       'Aguinaldos, primas, finiquitos',              'ACREEDORA', 1),
('21060101', 2, 1, 6, 1, 1, 'Aguinaldo por Pagar',                 '',                                            'ACREEDORA', 1),
('21060102', 2, 1, 6, 1, 2, 'Prima por Pagar',                     '',                                            'ACREEDORA', 1),
('21060103', 2, 1, 6, 1, 3, 'Indemnización por Pagar',             '',                                            'ACREEDORA', 1),
('21070100', 2, 1, 7, 1, 0, 'Aportes y Retenciones por Pagar',     'AFP, CNS y retenciones',                      'ACREEDORA', 1),
('21080100', 2, 1, 8, 1, 0, 'Préstamos a Corto Plazo',             '',                                            'ACREEDORA', 1),
('21090100', 2, 1, 9, 1, 0, 'Anticipos de Clientes',               '',                                            'ACREEDORA', 1),
('22010000', 2, 2, 0, 0, 0, 'PASIVO NO CORRIENTE',                 'Obligaciones a largo plazo',                  'ACREEDORA', 0),
('22010100', 2, 2, 1, 1, 0, 'Préstamos Bancarios LP',              '',                                            'ACREEDORA', 1),
('22020100', 2, 2, 2, 1, 0, 'Hipotecas por Pagar',                 '',                                            'ACREEDORA', 1),
('22030100', 2, 2, 3, 1, 0, 'Previsión para Indemnizaciones',      '',                                            'ACREEDORA', 1);

-- ------------------------------------------------------------
-- CLASE 3 — PATRIMONIO (ACREEDORA)
-- ------------------------------------------------------------
INSERT INTO cuentas (codigo, clase, grupo, cuenta, subcuenta, auxiliar, nombre, descripcion, naturaleza, es_imputable) VALUES
('31000000', 3, 0, 0, 0, 0, 'PATRIMONIO',                          'Patrimonio neto',                             'ACREEDORA', 0),
('31010000', 3, 1, 0, 0, 0, 'CAPITAL',                             '',                                            'ACREEDORA', 0),
('31010100', 3, 1, 1, 1, 0, 'Capital Social',                      'Aporte de socios',                            'ACREEDORA', 1),
('31020100', 3, 1, 2, 1, 0, 'Aportes Pendientes Capitalización',   '',                                            'ACREEDORA', 1),
('32010000', 3, 2, 0, 0, 0, 'RESERVAS',                            '',                                            'ACREEDORA', 0),
('32010100', 3, 2, 1, 1, 0, 'Reserva Legal',                       'Reserva obligatoria 5% anual',                'ACREEDORA', 1),
('32020100', 3, 2, 2, 1, 0, 'Reservas Estatutarias',               '',                                            'ACREEDORA', 1),
('32030100', 3, 2, 3, 1, 0, 'Reservas Voluntarias',                '',                                            'ACREEDORA', 1),
('33010000', 3, 3, 0, 0, 0, 'RESULTADOS',                          '',                                            'ACREEDORA', 0),
('33010100', 3, 3, 1, 1, 0, 'Resultados Acumulados',               'Utilidades retenidas',                        'ACREEDORA', 1),
('33020100', 3, 3, 2, 1, 0, 'Resultado del Ejercicio',             'Utilidad o pérdida del período',              'ACREEDORA', 1);

-- ------------------------------------------------------------
-- CLASE 4 — INGRESOS (ACREEDORA)
-- ------------------------------------------------------------
INSERT INTO cuentas (codigo, clase, grupo, cuenta, subcuenta, auxiliar, nombre, descripcion, naturaleza, es_imputable) VALUES
('41000000', 4, 0, 0, 0, 0, 'INGRESOS',                            '',                                            'ACREEDORA', 0),
('41010000', 4, 1, 0, 0, 0, 'INGRESOS OPERATIVOS',                 '',                                            'ACREEDORA', 0),
('41010100', 4, 1, 1, 1, 0, 'Ventas',                              '',                                            'ACREEDORA', 1),
('41010101', 4, 1, 1, 1, 1, 'Ventas Mercaderías',                  '',                                            'ACREEDORA', 1),
('41010102', 4, 1, 1, 1, 2, 'Ventas Productos Terminados',         '',                                            'ACREEDORA', 1),
('41020100', 4, 1, 2, 1, 0, 'Servicios Prestados',                 '',                                            'ACREEDORA', 1),
('41030100', 4, 1, 3, 1, 0, 'Descuentos y Devoluciones s/Ventas',  'Cuenta regularizadora',                       'DEUDORA', 1),
('42010000', 4, 2, 0, 0, 0, 'INGRESOS NO OPERATIVOS',              '',                                            'ACREEDORA', 0),
('42010100', 4, 2, 1, 1, 0, 'Ingresos Financieros',                'Intereses ganados',                           'ACREEDORA', 1),
('42020100', 4, 2, 2, 1, 0, 'Ingresos Extraordinarios',            '',                                            'ACREEDORA', 1),
('42030100', 4, 2, 3, 1, 0, 'Diferencia de Cambio (Ganancia)',     '',                                            'ACREEDORA', 1);

-- ------------------------------------------------------------
-- CLASE 5 — EGRESOS / GASTOS (DEUDORA)
-- ------------------------------------------------------------
INSERT INTO cuentas (codigo, clase, grupo, cuenta, subcuenta, auxiliar, nombre, descripcion, naturaleza, es_imputable) VALUES
('51000000', 5, 0, 0, 0, 0, 'EGRESOS',                             '',                                            'DEUDORA', 0),
('51010000', 5, 1, 0, 0, 0, 'COSTO DE VENTAS',                     '',                                            'DEUDORA', 0),
('51010100', 5, 1, 1, 1, 0, 'Costo de Mercadería Vendida',         'CMV',                                         'DEUDORA', 1),
('51020100', 5, 1, 2, 1, 0, 'Costo de Servicios Prestados',        '',                                            'DEUDORA', 1),
('52010000', 5, 2, 0, 0, 0, 'GASTOS OPERATIVOS',                   '',                                            'DEUDORA', 0),
('52010100', 5, 2, 1, 1, 0, 'Sueldos y Salarios',                  '',                                            'DEUDORA', 1),
('52020100', 5, 2, 2, 1, 0, 'Cargas Sociales',                     'Aportes patronales',                          'DEUDORA', 1),
('52030100', 5, 2, 3, 1, 0, 'Alquileres',                          '',                                            'DEUDORA', 1),
('52040100', 5, 2, 4, 1, 0, 'Servicios Básicos',                   'Luz, agua, gas, internet',                    'DEUDORA', 1),
('52040101', 5, 2, 4, 1, 1, 'Energía Eléctrica',                   '',                                            'DEUDORA', 1),
('52040102', 5, 2, 4, 1, 2, 'Agua Potable',                        '',                                            'DEUDORA', 1),
('52040103', 5, 2, 4, 1, 3, 'Telefonía e Internet',                '',                                            'DEUDORA', 1),
('52050100', 5, 2, 5, 1, 0, 'Suministros de Oficina',              '',                                            'DEUDORA', 1),
('52060100', 5, 2, 6, 1, 0, 'Depreciación del Ejercicio',          '',                                            'DEUDORA', 1),
('52070100', 5, 2, 7, 1, 0, 'Amortización del Ejercicio',          '',                                            'DEUDORA', 1),
('52080100', 5, 2, 8, 1, 0, 'Gastos de Publicidad',                '',                                            'DEUDORA', 1),
('52090100', 5, 2, 9, 1, 0, 'Impuestos y Tasas',                   'Tributos no recuperables',                    'DEUDORA', 1),
('53010000', 5, 3, 0, 0, 0, 'GASTOS NO OPERATIVOS',                '',                                            'DEUDORA', 0),
('53010100', 5, 3, 1, 1, 0, 'Gastos Financieros',                  'Intereses pagados, comisiones',               'DEUDORA', 1),
('53020100', 5, 3, 2, 1, 0, 'Pérdidas Extraordinarias',            '',                                            'DEUDORA', 1),
('53030100', 5, 3, 3, 1, 0, 'Diferencia de Cambio (Pérdida)',      '',                                            'DEUDORA', 1);
