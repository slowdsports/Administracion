-- ============================================================
-- AHDECO — Migración: Descuento e Impuesto de Turismo en Órdenes de Compra
-- Ejecutar UNA SOLA VEZ en la base de datos ahdeco_admin
-- Agrega columnas para registrar el descuento (%) aplicado y el
-- impuesto sobre turismo (4%), además del ISV (15%) ya existente.
-- ============================================================

USE ahdeco_admin;

ALTER TABLE ordenes_compra
    ADD COLUMN IF NOT EXISTS descuento DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal,
    ADD COLUMN IF NOT EXISTS impuesto_turismo DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER impuestos;
