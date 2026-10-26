-- ============================================================
-- AHDECO — Migración: rol "Compras y Contabilidad"
-- Ejecutar UNA SOLA VEZ en la base de datos ahdeco_admin
-- Agrega el rol 'compras' (acceso limitado a Contabilidad, Compras
-- y Proyectos; ver ROLES_RESTRINGIDOS en config/auth.php).
-- ============================================================

USE ahdeco_admin;

ALTER TABLE usuarios
    MODIFY rol ENUM('admin','contador','asistente','visualizador','compras') DEFAULT 'asistente';
