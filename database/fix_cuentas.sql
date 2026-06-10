-- ============================================================
-- AHDECO - Corrección del Catálogo Contable de Gastos
-- Basado en "Catalogo Contable de Gastos.xlsx"
-- Ejecutar en phpMyAdmin sobre la base: ahdeco_admin
-- ============================================================

USE ahdeco_admin;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Eliminar cuentas genéricas de gastos (5000-7999) ──────────
DELETE FROM cuentas_contables
WHERE tipo IN ('gasto','otro_gasto')
   OR (codigo REGEXP '^[567]');

-- ============================================================
-- NIVEL 1 — Encabezado principal de gastos
-- ============================================================
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel) VALUES
('50000', 'GASTOS OPERATIVOS Y ADMINISTRATIVOS', 'gasto', 'Gasto', 1);

-- ============================================================
-- NIVEL 2 — Grupos de gasto  (padre = 50000)
-- ============================================================
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50100', 'RECLUTAMIENTO Y PERSONAL', 'gasto', 'Recursos Humanos', 2, id
FROM cuentas_contables WHERE codigo = '50000';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50200', 'SERVICIOS PROFESIONALES', 'gasto', 'Servicios Profesionales', 2, id
FROM cuentas_contables WHERE codigo = '50000';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50300', 'CAPACITACIÓN Y REUNIONES', 'gasto', 'Capacitación', 2, id
FROM cuentas_contables WHERE codigo = '50000';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50400', 'VIAJES Y TRANSPORTE', 'gasto', 'Viáticos y Transporte', 2, id
FROM cuentas_contables WHERE codigo = '50000';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50500', 'GASTOS DE OFICINA Y OPERATIVOS', 'gasto', 'Gastos de Oficina', 2, id
FROM cuentas_contables WHERE codigo = '50000';

-- ============================================================
-- NIVEL 3 — Cuentas de detalle
-- ============================================================

-- ── 50100 RECLUTAMIENTO Y PERSONAL ───────────────────────────────
-- Cuentas de planilla (necesarias para módulo de planillas)
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50101', 'SUELDOS Y SALARIOS - ADMINISTRACIÓN', 'gasto', 'Recursos Humanos', 3, id
FROM cuentas_contables WHERE codigo = '50100';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50102', 'SUELDOS Y SALARIOS - PROGRAMAS', 'gasto', 'Recursos Humanos', 3, id
FROM cuentas_contables WHERE codigo = '50100';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50103', 'HORAS EXTRA', 'gasto', 'Recursos Humanos', 3, id
FROM cuentas_contables WHERE codigo = '50100';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50104', 'BONIFICACIONES', 'gasto', 'Recursos Humanos', 3, id
FROM cuentas_contables WHERE codigo = '50100';

-- Cuenta directamente del catálogo AHDECO
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50105', 'GASTOS DE RECLUTAMIENTO', 'gasto', 'Recursos Humanos', 3, id
FROM cuentas_contables WHERE codigo = '50100';

-- Cargas sociales patronales
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50110', 'IHSS PATRONAL', 'gasto', 'Cargas Sociales', 3, id
FROM cuentas_contables WHERE codigo = '50100';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50111', 'RAP PATRONAL', 'gasto', 'Cargas Sociales', 3, id
FROM cuentas_contables WHERE codigo = '50100';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50112', 'INFOP', 'gasto', 'Cargas Sociales', 3, id
FROM cuentas_contables WHERE codigo = '50100';

-- Prestaciones laborales
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50120', 'AGUINALDO (DÉCIMO TERCER MES)', 'gasto', 'Prestaciones', 3, id
FROM cuentas_contables WHERE codigo = '50100';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50121', 'DÉCIMO CUARTO MES', 'gasto', 'Prestaciones', 3, id
FROM cuentas_contables WHERE codigo = '50100';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50122', 'VACACIONES', 'gasto', 'Prestaciones', 3, id
FROM cuentas_contables WHERE codigo = '50100';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50123', 'INDEMNIZACIONES Y LIQUIDACIONES', 'gasto', 'Prestaciones', 3, id
FROM cuentas_contables WHERE codigo = '50100';

-- ── 50200 SERVICIOS PROFESIONALES ────────────────────────────────
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50202', 'SERVICIOS LEGALES', 'gasto', 'Servicios Profesionales', 3, id
FROM cuentas_contables WHERE codigo = '50200';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50220', 'OTROS GASTOS DE SERVICIOS PROFESIONALES', 'gasto', 'Servicios Profesionales', 3, id
FROM cuentas_contables WHERE codigo = '50200';

-- ── 50300 CAPACITACIÓN Y REUNIONES ───────────────────────────────
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50301', 'CAPACITACIONES', 'gasto', 'Capacitación', 3, id
FROM cuentas_contables WHERE codigo = '50300';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50302', 'CONFERENCIAS Y REUNIONES LOCALES', 'gasto', 'Capacitación', 3, id
FROM cuentas_contables WHERE codigo = '50300';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50303', 'CONFERENCIAS Y REUNIONES INTERNACIONALES', 'gasto', 'Capacitación', 3, id
FROM cuentas_contables WHERE codigo = '50300';

-- ── 50400 VIAJES Y TRANSPORTE ────────────────────────────────────
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50401', 'ALIMENTACIÓN', 'gasto', 'Viáticos y Transporte', 3, id
FROM cuentas_contables WHERE codigo = '50400';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50402', 'HOSPEDAJE', 'gasto', 'Viáticos y Transporte', 3, id
FROM cuentas_contables WHERE codigo = '50400';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50403', 'RENTA DE VEHÍCULOS', 'gasto', 'Viáticos y Transporte', 3, id
FROM cuentas_contables WHERE codigo = '50400';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50404', 'VUELOS', 'gasto', 'Viáticos y Transporte', 3, id
FROM cuentas_contables WHERE codigo = '50400';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50405', 'MANTENIMIENTO Y REPARACIÓN DE VEHÍCULO', 'gasto', 'Viáticos y Transporte', 3, id
FROM cuentas_contables WHERE codigo = '50400';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50408', 'COMBUSTIBLES Y LUBRICANTES', 'gasto', 'Viáticos y Transporte', 3, id
FROM cuentas_contables WHERE codigo = '50400';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50420', 'OTROS GASTOS DE VIAJES Y TRANSPORTE', 'gasto', 'Viáticos y Transporte', 3, id
FROM cuentas_contables WHERE codigo = '50400';

-- ── 50500 GASTOS DE OFICINA Y OPERATIVOS ─────────────────────────
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50501', 'MATERIALES Y SUMINISTROS DE OFICINA', 'gasto', 'Gastos de Oficina', 3, id
FROM cuentas_contables WHERE codigo = '50500';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50503', 'REPARACIONES DE EQUIPO DE OFICINA', 'gasto', 'Gastos de Oficina', 3, id
FROM cuentas_contables WHERE codigo = '50500';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50505', 'ENVÍOS Y CORRESPONDENCIA', 'gasto', 'Gastos de Oficina', 3, id
FROM cuentas_contables WHERE codigo = '50500';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50507', 'MATERIAL DE MERCADEO Y VISUALIZACIÓN', 'gasto', 'Gastos de Oficina', 3, id
FROM cuentas_contables WHERE codigo = '50500';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50508', 'SUMINISTROS DE LIMPIEZA', 'gasto', 'Gastos de Oficina', 3, id
FROM cuentas_contables WHERE codigo = '50500';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50514', 'TELÉFONO Y COMUNICACIONES', 'gasto', 'Gastos de Oficina', 3, id
FROM cuentas_contables WHERE codigo = '50500';

INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50520', 'OTROS GASTOS DE OFICINA', 'gasto', 'Gastos de Oficina', 3, id
FROM cuentas_contables WHERE codigo = '50500';

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación
SELECT codigo, nombre, nivel, subtipo FROM cuentas_contables
WHERE tipo = 'gasto'
ORDER BY codigo;
