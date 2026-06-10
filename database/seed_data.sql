USE ahdeco_admin;

-- =========================================
-- USUARIO ADMINISTRADOR
-- Password: Admin2024! (hash bcrypt)
-- =========================================
INSERT INTO usuarios (nombre, email, password, rol) VALUES
('Administrador AHDECO', 'admin@ahdeco.hn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- NOTA: La contraseña por defecto es "password". Cámbiala después del primer acceso.

-- =========================================
-- CONFIGURACIÓN DEL SISTEMA
-- =========================================
INSERT INTO configuracion (clave, valor, descripcion) VALUES
('nombre_organizacion', 'AHDECO', 'Nombre de la organización'),
('nombre_completo', 'Asociación Hondureña para el Desarrollo Integral Comunitario', 'Nombre completo'),
('rtn', '0801-xxxx-xxxxx', 'RTN de la organización'),
('telefono', '+504 xxxx-xxxx', 'Teléfono'),
('correo', 'info@ahdeco.hn', 'Correo electrónico'),
('direccion', 'Tegucigalpa, Honduras', 'Dirección'),
('moneda', 'HNL', 'Moneda principal'),
('ihss_empleado', '2.5', 'Porcentaje IHSS empleado'),
('ihss_patronal', '5.0', 'Porcentaje IHSS patronal'),
('rap_empleado', '1.5', 'Porcentaje RAP empleado'),
('rap_patronal', '1.5', 'Porcentaje RAP patronal'),
('infop', '1.0', 'Porcentaje INFOP patronal'),
('ihss_techo_mensual', '9357.00', 'Salario techo IHSS mensual'),
('isr_exento_anual', '142771.50', 'Monto exento ISR anual'),
('prefijo_sg', 'SG', 'Prefijo solicitud de gastos'),
('prefijo_gv', 'GV', 'Prefijo gastos de viaje'),
('prefijo_pl', 'PL', 'Prefijo planilla'),
('prefijo_sc', 'SC', 'Prefijo solicitud de compra'),
('prefijo_co', 'CO', 'Prefijo cotizaciones'),
('prefijo_oc', 'OC', 'Prefijo orden de compra'),
('prefijo_op', 'OP', 'Prefijo orden de pago'),
('prefijo_nr', 'NR', 'Prefijo nota de recepción');

-- =========================================
-- CATÁLOGOS DE EMPLEADOS
-- =========================================
INSERT INTO cat_cargos (nombre) VALUES
('Técnico TI'),('Conserje'),('Presidente Junta Directiva'),
('Administración y Finanzas'),('Asesor de UAF'),('Jefe de UAF'),
('Oficial de Microcrédito'),('Operaciones y Finanzas'),('Gerente de Proyectos'),
('Abogado Administrativo'),('Director Ejecutivo'),('Asesor de Dirección');

INSERT INTO cat_departamentos (nombre) VALUES
('Administración'),('Dirección'),('Créditos'),('Proyectos');

INSERT INTO cat_bancos (nombre) VALUES
('BAC'),('Atlántida'),('FICOHSA'),('Davivienda');

-- =========================================
-- CATÁLOGO DE CUENTAS CONTABLES (QuickBooks)
-- =========================================
-- ACTIVOS
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel) VALUES
('1000', 'ACTIVOS CORRIENTES', 'activo', 'Activo Corriente', 1),
('1001', 'Caja General', 'activo', 'Banco', 2),
('1002', 'Caja Chica', 'activo', 'Banco', 2),
('1003', 'Banco BAC Honduras - Cta. Cte. HNL', 'activo', 'Banco', 2),
('1004', 'Banco Atlántida - Cta. Cte. HNL', 'activo', 'Banco', 2),
('1005', 'Banco Occidente - Cta. Cte. HNL', 'activo', 'Banco', 2),
('1006', 'Banco BAC Honduras - Cta. USD', 'activo', 'Banco', 2),
('1010', 'Cuentas por Cobrar', 'activo', 'Cuentas por Cobrar', 2),
('1011', 'Anticipos a Empleados', 'activo', 'Cuentas por Cobrar', 2),
('1012', 'Anticipos a Proveedores', 'activo', 'Cuentas por Cobrar', 2),
('1020', 'Inventario de Materiales', 'activo', 'Inventario', 2),
('1030', 'Gastos Pagados por Anticipado', 'activo', 'Activo Corriente', 2),
('1031', 'Seguros Pagados por Anticipado', 'activo', 'Activo Corriente', 2),
('1032', 'Alquileres Pagados por Anticipado', 'activo', 'Activo Corriente', 2),
('1500', 'ACTIVOS FIJOS', 'activo', 'Activo Fijo', 1),
('1501', 'Mobiliario y Equipo de Oficina', 'activo', 'Activo Fijo', 2),
('1502', 'Equipo de Cómputo', 'activo', 'Activo Fijo', 2),
('1503', 'Vehículos', 'activo', 'Activo Fijo', 2),
('1504', 'Equipo de Campo', 'activo', 'Activo Fijo', 2),
('1505', 'Bienes Inmuebles', 'activo', 'Activo Fijo', 2),
('1590', 'Depreciación Acumulada Mobiliario', 'activo', 'Depreciación Acumulada', 2),
('1591', 'Depreciación Acumulada Cómputo', 'activo', 'Depreciación Acumulada', 2),
('1592', 'Depreciación Acumulada Vehículos', 'activo', 'Depreciación Acumulada', 2);

-- PASIVOS
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel) VALUES
('2000', 'PASIVOS CORRIENTES', 'pasivo', 'Pasivo Corriente', 1),
('2001', 'Cuentas por Pagar - Proveedores', 'pasivo', 'Cuentas por Pagar', 2),
('2002', 'Gastos Acumulados por Pagar', 'pasivo', 'Pasivo Corriente', 2),
('2010', 'IHSS Empleados por Pagar', 'pasivo', 'Retenciones', 2),
('2011', 'IHSS Patronal por Pagar', 'pasivo', 'Retenciones', 2),
('2012', 'RAP Empleados por Pagar', 'pasivo', 'Retenciones', 2),
('2013', 'RAP Patronal por Pagar', 'pasivo', 'Retenciones', 2),
('2014', 'INFOP por Pagar', 'pasivo', 'Retenciones', 2),
('2015', 'ISR Retenido por Pagar', 'pasivo', 'Retenciones', 2),
('2016', 'Sueldos y Salarios por Pagar', 'pasivo', 'Pasivo Corriente', 2),
('2020', 'Retenciones Impuesto Sobre Ventas', 'pasivo', 'Retenciones', 2),
('2030', 'Fondos Recibidos por Rendir', 'pasivo', 'Pasivo Corriente', 2);

-- CAPITAL
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel) VALUES
('3000', 'PATRIMONIO NETO', 'capital', 'Capital', 1),
('3001', 'Activos Netos No Restringidos', 'capital', 'Capital', 2),
('3002', 'Activos Netos Restringidos', 'capital', 'Capital', 2),
('3003', 'Superávit / Déficit del Ejercicio', 'capital', 'Capital', 2);

-- INGRESOS
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel) VALUES
('4000', 'INGRESOS', 'ingreso', 'Ingreso', 1),
('4001', 'Donaciones - Cooperación Internacional', 'ingreso', 'Donaciones', 2),
('4002', 'Donaciones - Gobierno', 'ingreso', 'Donaciones', 2),
('4003', 'Donaciones - Sector Privado', 'ingreso', 'Donaciones', 2),
('4010', 'Cuotas de Membresía', 'ingreso', 'Cuotas', 2),
('4020', 'Ingresos por Servicios', 'ingreso', 'Servicios', 2),
('4030', 'Intereses Bancarios', 'otro_ingreso', 'Otros Ingresos', 2),
('4040', 'Otros Ingresos', 'otro_ingreso', 'Otros Ingresos', 2);

-- CATÁLOGO CONTABLE DE GASTOS (basado en Catalogo Contable de Gastos.xlsx)
-- Nivel 1: Encabezado
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel) VALUES
('50000', 'GASTOS OPERATIVOS Y ADMINISTRATIVOS', 'gasto', 'Gasto', 1);

-- Nivel 2: Grupos
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50100', 'RECLUTAMIENTO Y PERSONAL', 'gasto', 'Recursos Humanos', 2, id FROM cuentas_contables WHERE codigo='50000';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50200', 'SERVICIOS PROFESIONALES', 'gasto', 'Servicios Profesionales', 2, id FROM cuentas_contables WHERE codigo='50000';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50300', 'CAPACITACIÓN Y REUNIONES', 'gasto', 'Capacitación', 2, id FROM cuentas_contables WHERE codigo='50000';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50400', 'VIAJES Y TRANSPORTE', 'gasto', 'Viáticos y Transporte', 2, id FROM cuentas_contables WHERE codigo='50000';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id)
SELECT '50500', 'GASTOS DE OFICINA Y OPERATIVOS', 'gasto', 'Gastos de Oficina', 2, id FROM cuentas_contables WHERE codigo='50000';

-- Nivel 3: 50100 — Personal y planilla
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50101','SUELDOS Y SALARIOS - ADMINISTRACIÓN','gasto','Recursos Humanos',3,id FROM cuentas_contables WHERE codigo='50100';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50102','SUELDOS Y SALARIOS - PROGRAMAS','gasto','Recursos Humanos',3,id FROM cuentas_contables WHERE codigo='50100';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50103','HORAS EXTRA','gasto','Recursos Humanos',3,id FROM cuentas_contables WHERE codigo='50100';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50104','BONIFICACIONES','gasto','Recursos Humanos',3,id FROM cuentas_contables WHERE codigo='50100';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50105','GASTOS DE RECLUTAMIENTO','gasto','Recursos Humanos',3,id FROM cuentas_contables WHERE codigo='50100';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50110','IHSS PATRONAL','gasto','Cargas Sociales',3,id FROM cuentas_contables WHERE codigo='50100';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50111','RAP PATRONAL','gasto','Cargas Sociales',3,id FROM cuentas_contables WHERE codigo='50100';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50112','INFOP','gasto','Cargas Sociales',3,id FROM cuentas_contables WHERE codigo='50100';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50120','AGUINALDO (DÉCIMO TERCER MES)','gasto','Prestaciones',3,id FROM cuentas_contables WHERE codigo='50100';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50121','DÉCIMO CUARTO MES','gasto','Prestaciones',3,id FROM cuentas_contables WHERE codigo='50100';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50122','VACACIONES','gasto','Prestaciones',3,id FROM cuentas_contables WHERE codigo='50100';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50123','INDEMNIZACIONES Y LIQUIDACIONES','gasto','Prestaciones',3,id FROM cuentas_contables WHERE codigo='50100';

-- Nivel 3: 50200 — Servicios Profesionales
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50202','SERVICIOS LEGALES','gasto','Servicios Profesionales',3,id FROM cuentas_contables WHERE codigo='50200';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50220','OTROS GASTOS DE SERVICIOS PROFESIONALES','gasto','Servicios Profesionales',3,id FROM cuentas_contables WHERE codigo='50200';

-- Nivel 3: 50300 — Capacitación y Reuniones
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50301','CAPACITACIONES','gasto','Capacitación',3,id FROM cuentas_contables WHERE codigo='50300';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50302','CONFERENCIAS Y REUNIONES LOCALES','gasto','Capacitación',3,id FROM cuentas_contables WHERE codigo='50300';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50303','CONFERENCIAS Y REUNIONES INTERNACIONALES','gasto','Capacitación',3,id FROM cuentas_contables WHERE codigo='50300';

-- Nivel 3: 50400 — Viajes y Transporte
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50401','ALIMENTACIÓN','gasto','Viáticos y Transporte',3,id FROM cuentas_contables WHERE codigo='50400';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50402','HOSPEDAJE','gasto','Viáticos y Transporte',3,id FROM cuentas_contables WHERE codigo='50400';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50403','RENTA DE VEHÍCULOS','gasto','Viáticos y Transporte',3,id FROM cuentas_contables WHERE codigo='50400';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50404','VUELOS','gasto','Viáticos y Transporte',3,id FROM cuentas_contables WHERE codigo='50400';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50405','MANTENIMIENTO Y REPARACIÓN DE VEHÍCULO','gasto','Viáticos y Transporte',3,id FROM cuentas_contables WHERE codigo='50400';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50408','COMBUSTIBLES Y LUBRICANTES','gasto','Viáticos y Transporte',3,id FROM cuentas_contables WHERE codigo='50400';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50420','OTROS GASTOS DE VIAJES Y TRANSPORTE','gasto','Viáticos y Transporte',3,id FROM cuentas_contables WHERE codigo='50400';

-- Nivel 3: 50500 — Gastos de Oficina y Operativos
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50501','MATERIALES Y SUMINISTROS DE OFICINA','gasto','Gastos de Oficina',3,id FROM cuentas_contables WHERE codigo='50500';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50503','REPARACIONES DE EQUIPO DE OFICINA','gasto','Gastos de Oficina',3,id FROM cuentas_contables WHERE codigo='50500';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50505','ENVÍOS Y CORRESPONDENCIA','gasto','Gastos de Oficina',3,id FROM cuentas_contables WHERE codigo='50500';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50507','MATERIAL DE MERCADEO Y VISUALIZACIÓN','gasto','Gastos de Oficina',3,id FROM cuentas_contables WHERE codigo='50500';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50508','SUMINISTROS DE LIMPIEZA','gasto','Gastos de Oficina',3,id FROM cuentas_contables WHERE codigo='50500';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50514','TELÉFONO Y COMUNICACIONES','gasto','Gastos de Oficina',3,id FROM cuentas_contables WHERE codigo='50500';
INSERT INTO cuentas_contables (codigo, nombre, tipo, subtipo, nivel, cuenta_padre_id) SELECT '50520','OTROS GASTOS DE OFICINA','gasto','Gastos de Oficina',3,id FROM cuentas_contables WHERE codigo='50500';

-- =========================================
-- EMPLEADOS DE EJEMPLO
-- =========================================
INSERT INTO empleados (codigo, nombre, apellidos, identidad, cargo, departamento, tipo_contrato, fecha_ingreso, sueldo_mensual, banco, aplica_ihss, aplica_rap, aplica_isr, email, activo) VALUES
('EMP001', 'Noe', 'García López', '0801-1990-12345', 'Administración y Finanzas', 'Administración', 'indefinido', '2022-01-01', 12000.00, 'BAC', 1, 1, 1, 'noe.garcia@ahdeco.hn', 1),
('EMP002', 'Nora', 'Martínez Reyes', '0504-1988-67890', 'Gerente de Proyectos', 'Proyectos', 'indefinido', '2021-03-15', 18000.00, 'Atlántida', 1, 1, 1, 'nora.martinez@ahdeco.hn', 1),
('EMP003', 'Director', 'Ejecutivo AHDECO', '0801-1980-11111', 'Director Ejecutivo', 'Dirección', 'indefinido', '2019-06-01', 30000.00, 'BAC', 1, 1, 1, 'director@ahdeco.hn', 1),
('EMP004', 'Contadora', 'Principal AHDECO', '0801-1985-22222', 'Administración y Finanzas', 'Administración', 'indefinido', '2020-08-01', 22000.00, 'BAC', 1, 1, 1, 'contadora@ahdeco.hn', 1);

-- =========================================
-- PROYECTOS DE EJEMPLO
-- =========================================
INSERT INTO proyectos (codigo, nombre, donante, descripcion, fecha_inicio, fecha_fin, presupuesto_total, moneda, estado) VALUES
('PROJ-2024-01', 'Fortalecimiento Comunitario Choluteca', 'USAID', 'Proyecto de fortalecimiento de capacidades comunitarias en el departamento de Choluteca', '2024-01-01', '2025-12-31', 500000.00, 'USD', 'activo'),
('PROJ-2024-02', 'Seguridad Alimentaria Valle', 'FAO', 'Programa de seguridad alimentaria en el Valle de Sula', '2024-03-01', '2025-02-28', 350000.00, 'USD', 'activo'),
('ADM-2024', 'Administración y Operaciones', 'AHDECO', 'Gastos operativos y administrativos de la organización', '2024-01-01', '2024-12-31', 1200000.00, 'HNL', 'activo');

-- =========================================
-- PROVEEDORES DE EJEMPLO
-- =========================================
INSERT INTO proveedores (codigo, nombre, rtn, contacto, telefono, email, categoria) VALUES
('PROV001', 'Librería e Imprenta Renacimiento', '0801-xxxx-01', 'Juan Torres', '2222-1111', 'renacimiento@gmail.com', 'bienes'),
('PROV002', 'Ferretería El Constructor', '0801-xxxx-02', 'Pedro Alvarado', '2222-2222', 'constructor@gmail.com', 'bienes'),
('PROV003', 'Servicios de Transporte Rápido S.A.', '0801-xxxx-03', 'María Soto', '9999-1111', 'transporte@gmail.com', 'servicios'),
('PROV004', 'Supermercado La Colonia', '0801-xxxx-04', 'Ventas', '2222-3333', 'ventas@lacolonia.hn', 'bienes'),
('PROV005', 'Tech Honduras - Equipos y Cómputo', '0801-xxxx-05', 'Carlos Mejía', '9999-2222', 'tech@techhn.com', 'bienes');
