-- ============================================================
-- AHDECO — Migración: Distribución de planillas por proyecto
-- Ejecutar UNA SOLA VEZ en la base de datos ahdeco_admin
-- Solo crea tablas nuevas; no modifica las existentes.
--
--  * empleado_proyectos: distribución por defecto de cada empleado
--      pct_salario  → % del salario (bruto/deducciones/neto) que paga el proyecto
--      pct_patronal → % del costo patronal (IHSS/RAP patronal) que paga el proyecto
--  * planilla_detalle_proyectos: copia de la distribución en cada
--    planilla quincenal (editable mientras no esté pagada)
--  * planilla_beneficios_detalle_proyectos: % por proyecto del
--    décimo tercer / cuarto mes de cada empleado
--
-- Empleados sin filas = 100% "Sin proyecto" (compatibilidad con
-- planillas anteriores).
-- ============================================================

USE ahdeco_admin;

CREATE TABLE IF NOT EXISTS empleado_proyectos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id  INT NOT NULL,
    proyecto_id  INT NOT NULL,
    pct_salario  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    pct_patronal DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    UNIQUE KEY uk_emp_proy (empleado_id, proyecto_id),
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS planilla_detalle_proyectos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    detalle_id   INT NOT NULL,
    proyecto_id  INT NOT NULL,
    pct_salario  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    pct_patronal DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    UNIQUE KEY uk_det_proy (detalle_id, proyecto_id),
    FOREIGN KEY (detalle_id)  REFERENCES planilla_detalle(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS planilla_beneficios_detalle_proyectos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    detalle_id   INT NOT NULL,
    proyecto_id  INT NOT NULL,
    porcentaje   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    UNIQUE KEY uk_bdet_proy (detalle_id, proyecto_id),
    FOREIGN KEY (detalle_id)  REFERENCES planilla_beneficios_detalle(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Porcentajes con 6 decimales: permiten derivar el % desde un monto en
-- lempiras sin perder centavos al recalcular (p. ej. L. 3,000 de L. 7,000).
ALTER TABLE empleado_proyectos
    MODIFY pct_salario  DECIMAL(9,6) NOT NULL DEFAULT 0,
    MODIFY pct_patronal DECIMAL(9,6) NOT NULL DEFAULT 0;
ALTER TABLE planilla_detalle_proyectos
    MODIFY pct_salario  DECIMAL(9,6) NOT NULL DEFAULT 0,
    MODIFY pct_patronal DECIMAL(9,6) NOT NULL DEFAULT 0;
ALTER TABLE planilla_beneficios_detalle_proyectos
    MODIFY porcentaje   DECIMAL(9,6) NOT NULL DEFAULT 0;
