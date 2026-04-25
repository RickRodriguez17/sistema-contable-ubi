# ContaUBI — Sistema Contable

**Sistema Contable Universidad Boliviana de Informática**

Aplicación web modular en PHP + MySQL para llevar contabilidad básica
basada en el **PUC (Plan Único de Cuentas) de Bolivia** con códigos de
**8 dígitos** (`G S CC SS AA` — clase, grupo, cuenta, subcuenta, auxiliar).

## Características

### Módulos
- **Plan de Cuentas** con PUC Bolivia precargado (96+ cuentas en 5 clases).
- **Comprobantes** (asientos contables) con partida doble, estados
  *Borrador / Aprobado / Anulado* y numeración automática por ejercicio.
- **Libro Diario** filtrable por rango de fechas, tipo y estado.
- **Libro Mayor** por cuenta con saldo inicial + saldo acumulado.
- **Balance de Comprobación** de Sumas y Saldos con verificación de cuadre.
- **Estado de Resultados** (utilidad / pérdida del ejercicio).
- **Balance General** con ecuación contable verificada.
- **Empresa** — configuración del nombre, NIT, ejercicio, moneda, etc.

### Restricciones / validaciones (defensa en profundidad)
- Códigos de cuenta de exactamente 8 dígitos, con validación en frontend
  (`maxlength`, `pattern`, JS) y en backend (regex + UNIQUE en BD).
- No se permiten cuentas con nombre duplicado dentro del mismo nivel.
- Sólo cuentas marcadas como **imputables** pueden recibir movimientos.
- Cada movimiento tiene **debe XOR haber** (no ambos) y montos ≥ 0.
- Cada comprobante debe **cuadrar**: Σ debe = Σ haber.
- La fecha del comprobante debe estar **dentro del ejercicio activo**.
- No se pueden eliminar cuentas con movimientos asociados.
- Comprobantes aprobados sólo se pueden anular (auditoría).

### Filtros
- **Plan de Cuentas**: clase, naturaleza, imputable, estado, búsqueda.
- **Comprobantes**: rango de fechas, tipo, estado, búsqueda.
- **Libro Diario**: rango, tipo, estado.
- **Libro Mayor**: cuenta + rango.
- **Balance Comprobación**: rango + clase.
- **Estado Resultados** y **Balance General**: rango / fecha de corte.

### UI
- Tema oscuro institucional verde + dorado UBI.
- Logo SVG vectorial propio.
- Sidebar con navegación por secciones.
- KPIs, badges por clase, chips para códigos, indicador de cuadre.
- Vista de impresión limpia con encabezado de empresa.
- Responsive (móvil 1 columna).

## Stack

- **PHP 8.1+** (mysqli)
- **MySQL 5.7+ / MariaDB 10.4+**
- **Bootstrap Icons** (CDN) y fuentes Inter / JetBrains Mono.
- Sin frameworks: PHP plano modular para entornos académicos.

## Estructura del proyecto

```
contaubi/
├── index.php                 ← Dashboard
├── empresa.php               ← Configuración de empresa
├── cuentas.php               ← Plan de cuentas (lista + filtros)
├── cuenta_crear.php          ← Crear cuenta
├── cuenta_editar.php         ← Editar cuenta
├── cuenta_eliminar.php       ← Eliminar (POST)
├── comprobantes.php          ← Lista de comprobantes
├── comprobante_crear.php     ← Nuevo asiento (partida doble)
├── comprobante_ver.php       ← Detalle / aprobar / anular
├── libro_diario.php
├── libro_mayor.php
├── balance_comprobacion.php
├── estado_resultados.php
├── balance_general.php
│
├── conexion.php              ← Conexión PDO (mysqli)
├── helpers.php               ← Helpers comunes
├── layout_top.php / layout_bottom.php
│
├── assets/
│   ├── img/logo.svg
│   └── css/app.css
│
├── db/
│   ├── schema.sql            ← Schema completo
│   └── seed_puc.sql          ← PUC Bolivia precargado
│
└── scripts/
    └── install.php           ← Instalador (CLI o web)
```

## Instalación

### 1. Requisitos
- PHP 8.1+ con `mysqli`
- MySQL 5.7+ o MariaDB 10.4+
- Cualquier servidor: PHP built-in, Apache, Nginx, XAMPP, MAMP…

### 2. Clonar
```bash
git clone https://github.com/fransystem/contaubi.git
cd contaubi
```

### 3. Configurar conexión

Por defecto usa: `host=127.0.0.1 user=root pass='' db=contaubi`.
Para sobreescribir, exportá variables de entorno:

```bash
export CONTAUBI_DB_HOST=127.0.0.1
export CONTAUBI_DB_USER=root
export CONTAUBI_DB_PASS=tupass
export CONTAUBI_DB_NAME=contaubi
```

### 4. Crear la base + cargar PUC

```bash
php scripts/install.php
```

Este script crea la base `contaubi`, aplica `db/schema.sql` y carga
el PUC Bolivia desde `db/seed_puc.sql`.

### 5. Levantar el servidor

```bash
php -S 0.0.0.0:8000 -t .
```

Abrir [http://localhost:8000](http://localhost:8000) en el navegador.

## Uso típico

1. Configurar la empresa en **Empresa** (nombre, NIT, ejercicio).
2. Revisar el **Plan de Cuentas** (ya viene cargado con el PUC Bolivia).
3. Registrar **Nuevo Asiento**:
   - Seleccionar fecha, tipo y glosa.
   - Agregar al menos 2 líneas con cuentas + montos en debe / haber.
   - El sistema valida que cuadre antes de permitir guardar.
4. **Aprobar** el comprobante para que afecte los reportes.
5. Consultar **Libro Diario**, **Libro Mayor**, y los **balances**.

## Licencia

Proyecto académico — Universidad Boliviana de Informática.
