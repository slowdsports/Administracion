-- ============================================================
-- AHDECO — Migración: Número de Proceso Unificado de Viáticos
-- Ejecutar UNA SOLA VEZ en la base de datos ahdeco_admin
-- Anticipo #N = Liquidación #N = Orden de Pago #N
-- ============================================================

USE ahdeco_admin;

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Tabla maestra de procesos de viáticos ─────────────────
-- Cada fila genera un número de proceso único que se comparte
-- entre gastos_viaje, solicitud_gastos y ordenes_pago.
CREATE TABLE IF NOT EXISTS procesos_viaticos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajustar AUTO_INCREMENT para evitar conflictos con documentos existentes
-- (el siguiente proceso arrancará después del máximo número ya usado en GV/SG/OP)
SET @max_gv = COALESCE(
    (SELECT MAX(CAST(SUBSTRING(numero, LOCATE('-', numero) + 1) AS UNSIGNED))
     FROM gastos_viaje WHERE numero LIKE '%-%'), 0);
SET @max_sg = COALESCE(
    (SELECT MAX(CAST(SUBSTRING(numero, LOCATE('-', numero) + 1) AS UNSIGNED))
     FROM solicitud_gastos WHERE numero LIKE '%-%'), 0);
SET @max_op = COALESCE(
    (SELECT MAX(CAST(SUBSTRING(numero, LOCATE('-', numero) + 1) AS UNSIGNED))
     FROM ordenes_pago WHERE numero LIKE '%-%'), 0);
SET @next_proc = GREATEST(@max_gv, @max_sg, @max_op) + 1;
SET @sql = CONCAT('ALTER TABLE procesos_viaticos AUTO_INCREMENT = ', @next_proc);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. gastos_viaje: columnas faltantes + proceso_id ─────────
ALTER TABLE gastos_viaje
    ADD COLUMN IF NOT EXISTS firmante1_id    INT            NULL        AFTER empleado_id,
    ADD COLUMN IF NOT EXISTS proposito       TEXT           NULL        AFTER destino,
    ADD COLUMN IF NOT EXISTS monto_alimentacion DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS proceso_id      INT            NULL;

-- ── 3. solicitud_gastos: columnas faltantes + proceso_id ─────
ALTER TABLE solicitud_gastos
    ADD COLUMN IF NOT EXISTS firmante1_id    INT            NULL        AFTER empleado_id,
    ADD COLUMN IF NOT EXISTS viaje_id        INT            NULL,
    ADD COLUMN IF NOT EXISTS proceso_id      INT            NULL;

-- ── 4. ordenes_pago: columnas faltantes + vínculos ───────────
ALTER TABLE ordenes_pago
    ADD COLUMN IF NOT EXISTS banco_tipo           ENUM('ahorros','cheques') NULL,
    ADD COLUMN IF NOT EXISTS factura_proveedor    VARCHAR(50)  NULL,
    ADD COLUMN IF NOT EXISTS cuenta_bancaria_id   INT          NULL,
    ADD COLUMN IF NOT EXISTS solicitud_gastos_id  INT          NULL,
    ADD COLUMN IF NOT EXISTS proceso_id           INT          NULL;

-- ── 5. Claves foráneas ────────────────────────────────────────
-- gastos_viaje
ALTER TABLE gastos_viaje
    ADD CONSTRAINT fk_gv_firmante1 FOREIGN KEY (firmante1_id) REFERENCES empleados(id)         ON DELETE SET NULL,
    ADD CONSTRAINT fk_gv_proceso   FOREIGN KEY (proceso_id)   REFERENCES procesos_viaticos(id);

-- solicitud_gastos
ALTER TABLE solicitud_gastos
    ADD CONSTRAINT fk_sg_firmante1 FOREIGN KEY (firmante1_id) REFERENCES empleados(id)         ON DELETE SET NULL,
    ADD CONSTRAINT fk_sg_viaje     FOREIGN KEY (viaje_id)     REFERENCES gastos_viaje(id)       ON DELETE SET NULL,
    ADD CONSTRAINT fk_sg_proceso   FOREIGN KEY (proceso_id)   REFERENCES procesos_viaticos(id);

-- ordenes_pago
ALTER TABLE ordenes_pago
    ADD CONSTRAINT fk_op_sg        FOREIGN KEY (solicitud_gastos_id) REFERENCES solicitud_gastos(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_op_proceso   FOREIGN KEY (proceso_id)           REFERENCES procesos_viaticos(id);

SET FOREIGN_KEY_CHECKS = 1;

-- ── Resultado esperado ────────────────────────────────────────
-- Al crear un anticipo (GV): se inserta en procesos_viaticos → proceso_id = N
-- Al crear la liquidación (SG) vinculada al anticipo: hereda proceso_id = N
-- Al crear la orden de pago (OP) vinculada a la liquidación: hereda proceso_id = N
-- Los tres documentos muestran "Proceso #N" en su vista.
