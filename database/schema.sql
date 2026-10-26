-- ============================================================
-- AHDECO - Sistema de Administración y Finanzas
-- Asociación Hondureña para el Desarrollo Integral Comunitario
-- ============================================================

-- Eliminar y recrear limpiamente para evitar errores InnoDB #1932
DROP DATABASE IF EXISTS ahdeco_admin;
CREATE DATABASE ahdeco_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ahdeco_admin;

SET FOREIGN_KEY_CHECKS = 0;

-- Usuarios del sistema
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin','contador','asistente','visualizador') DEFAULT 'asistente',
    activo TINYINT(1) DEFAULT 1,
    ultimo_acceso DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Empleados
CREATE TABLE empleados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    identidad VARCHAR(20),
    rtn VARCHAR(20),
    cargo VARCHAR(100),
    departamento VARCHAR(100),
    tipo_contrato ENUM('indefinido','definido','servicio') DEFAULT 'indefinido',
    fecha_ingreso DATE,
    fecha_egreso DATE,
    sueldo_mensual DECIMAL(10,2) DEFAULT 0.00,
    cuenta_banco VARCHAR(50),
    banco VARCHAR(100),
    aplica_ihss        TINYINT(1) DEFAULT 1,
    aplica_rap         TINYINT(1) DEFAULT 1,
    aplica_isr         TINYINT(1) DEFAULT 1,
    aplica_credimpulsa TINYINT(1) DEFAULT 0,
    ded_ihss_empleado  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ded_ihss_patronal  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ded_rap            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ded_isr            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ded_credimpulsa    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    email VARCHAR(150),
    telefono VARCHAR(20),
    direccion TEXT,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE historial_salarial (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id     INT NOT NULL,
    sueldo_anterior DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    sueldo_nuevo    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cambio_pct      DECIMAL(7,2)  NOT NULL DEFAULT 0.00,
    usuario_id      INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogos de empleados
CREATE TABLE cat_cargos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cat_departamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cat_bancos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogo de Cuentas Contables (compatible QuickBooks)
CREATE TABLE cuentas_contables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    tipo ENUM('activo','pasivo','capital','ingreso','gasto','otro_ingreso','otro_gasto') NOT NULL,
    subtipo VARCHAR(100),
    cuenta_padre_id INT DEFAULT NULL,
    nivel INT DEFAULT 1,
    descripcion TEXT,
    activa TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cuenta_padre_id) REFERENCES cuentas_contables(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proyectos / Donantes
CREATE TABLE proyectos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) UNIQUE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    donante VARCHAR(200),
    descripcion TEXT,
    fecha_inicio DATE,
    fecha_fin DATE,
    presupuesto_total DECIMAL(15,2) DEFAULT 0.00,
    moneda ENUM('HNL','USD','EUR') DEFAULT 'HNL',
    responsable_id INT,
    estado ENUM('activo','cerrado','suspendido') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (responsable_id) REFERENCES empleados(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proveedores
CREATE TABLE proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE,
    nombre VARCHAR(200) NOT NULL,
    rtn VARCHAR(20),
    contacto VARCHAR(100),
    telefono VARCHAR(20),
    email VARCHAR(150),
    direccion TEXT,
    categoria ENUM('bienes','servicios','ambos') DEFAULT 'ambos',
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Procesos de Viáticos (número compartido entre anticipo, liquidación y orden de pago)
CREATE TABLE procesos_viaticos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Solicitudes de Gastos (Reembolsos / Liquidaciones)
CREATE TABLE solicitud_gastos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    proceso_id INT NULL,
    fecha DATE NOT NULL,
    empleado_id INT NOT NULL,
    firmante1_id INT NULL,
    descripcion VARCHAR(300) NOT NULL,
    proyecto_id INT,
    viaje_id INT NULL,
    monto_total DECIMAL(10,2) DEFAULT 0.00,
    estado ENUM('pendiente','aprobada','rechazada','pagada') DEFAULT 'pendiente',
    aprobador_id INT,
    fecha_aprobacion DATE,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id)   REFERENCES empleados(id),
    FOREIGN KEY (firmante1_id)  REFERENCES empleados(id) ON DELETE SET NULL,
    FOREIGN KEY (proyecto_id)   REFERENCES proyectos(id) ON DELETE SET NULL,
    FOREIGN KEY (aprobador_id)  REFERENCES empleados(id) ON DELETE SET NULL,
    FOREIGN KEY (viaje_id)      REFERENCES gastos_viaje(id) ON DELETE SET NULL,
    FOREIGN KEY (proceso_id)    REFERENCES procesos_viaticos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle de Solicitud de Gastos
CREATE TABLE solicitud_gastos_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    fecha DATE,
    descripcion VARCHAR(300),
    cuenta_id INT,
    monto DECIMAL(10,2) DEFAULT 0.00,
    comprobante VARCHAR(255),
    FOREIGN KEY (solicitud_id) REFERENCES solicitud_gastos(id) ON DELETE CASCADE,
    FOREIGN KEY (cuenta_id) REFERENCES cuentas_contables(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anticipos (viaje/viáticos o compra de insumos/activos)
CREATE TABLE gastos_viaje (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    proceso_id INT NULL,
    tipo ENUM('viaje','compra') NOT NULL DEFAULT 'viaje',
    empleado_id INT NOT NULL,
    firmante1_id INT NULL,
    proyecto_id INT,
    destino VARCHAR(200) NOT NULL,
    proposito TEXT,
    fecha_salida DATE NOT NULL,
    fecha_regreso DATE NOT NULL,
    viaticos_anticipados DECIMAL(10,2) DEFAULT 0.00,
    monto_alimentacion DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_gastos DECIMAL(10,2) DEFAULT 0.00,
    saldo DECIMAL(10,2) DEFAULT 0.00,
    estado ENUM('pendiente','aprobada','rechazada','liquidada') DEFAULT 'pendiente',
    aprobador_id INT,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id)  REFERENCES empleados(id),
    FOREIGN KEY (firmante1_id) REFERENCES empleados(id) ON DELETE SET NULL,
    FOREIGN KEY (proyecto_id)  REFERENCES proyectos(id) ON DELETE SET NULL,
    FOREIGN KEY (aprobador_id) REFERENCES empleados(id) ON DELETE SET NULL,
    FOREIGN KEY (proceso_id)   REFERENCES procesos_viaticos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle de Gastos de Viaje
CREATE TABLE gastos_viaje_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    viaje_id INT NOT NULL,
    fecha DATE,
    descripcion VARCHAR(300),
    cuenta_id INT,
    monto DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (viaje_id) REFERENCES gastos_viaje(id) ON DELETE CASCADE,
    FOREIGN KEY (cuenta_id) REFERENCES cuentas_contables(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Períodos de Planilla
CREATE TABLE planilla_periodos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    anio INT NOT NULL,
    mes INT NOT NULL,
    quincena ENUM('primera','segunda') NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    fecha_pago DATE,
    estado ENUM('borrador','procesada','aprobada','pagada') DEFAULT 'borrador',
    total_bruto DECIMAL(12,2) DEFAULT 0.00,
    total_deducciones DECIMAL(12,2) DEFAULT 0.00,
    total_neto DECIMAL(12,2) DEFAULT 0.00,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_periodo (anio, mes, quincena)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle de Planilla por Empleado
CREATE TABLE planilla_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periodo_id INT NOT NULL,
    empleado_id INT NOT NULL,
    sueldo_quincenal DECIMAL(10,2) DEFAULT 0.00,
    horas_extra DECIMAL(10,2) DEFAULT 0.00,
    bono DECIMAL(10,2) DEFAULT 0.00,
    otros_ingresos DECIMAL(10,2) DEFAULT 0.00,
    total_bruto DECIMAL(10,2) DEFAULT 0.00,
    ihss_empleado DECIMAL(10,2) DEFAULT 0.00,
    rap_empleado DECIMAL(10,2) DEFAULT 0.00,
    isr DECIMAL(10,2) DEFAULT 0.00,
    otras_deducciones DECIMAL(10,2) DEFAULT 0.00,
    total_deducciones DECIMAL(10,2) DEFAULT 0.00,
    sueldo_neto DECIMAL(10,2) DEFAULT 0.00,
    ihss_patronal DECIMAL(10,2) DEFAULT 0.00,
    rap_patronal DECIMAL(10,2) DEFAULT 0.00,
    infop DECIMAL(10,2) DEFAULT 0.00,
    notas VARCHAR(300),
    FOREIGN KEY (periodo_id) REFERENCES planilla_periodos(id) ON DELETE CASCADE,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id),
    UNIQUE KEY uk_planilla_emp (periodo_id, empleado_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Solicitudes de Compra
CREATE TABLE solicitud_compra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    fecha DATE NOT NULL,
    solicitante_id INT NOT NULL,
    proyecto_id INT,
    descripcion VARCHAR(300) NOT NULL,
    justificacion TEXT,
    urgencia ENUM('normal','urgente','muy_urgente') DEFAULT 'normal',
    presupuesto_estimado DECIMAL(10,2),
    estado ENUM('pendiente','aprobada','cotizando','adjudicada','rechazada','anulada') DEFAULT 'pendiente',
    aprobador_id INT,
    fecha_aprobacion DATE,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitante_id) REFERENCES empleados(id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE SET NULL,
    FOREIGN KEY (aprobador_id) REFERENCES empleados(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle de Solicitud de Compra
CREATE TABLE solicitud_compra_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    descripcion VARCHAR(300) NOT NULL,
    unidad VARCHAR(50),
    cantidad DECIMAL(10,2) DEFAULT 1.00,
    precio_estimado DECIMAL(10,2),
    FOREIGN KEY (solicitud_id) REFERENCES solicitud_compra(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resumen de Cotizaciones
CREATE TABLE cotizaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    solicitud_id INT NOT NULL,
    fecha DATE NOT NULL,
    elaborado_por_id INT,
    proveedor_seleccionado_id INT,
    justificacion TEXT,
    estado ENUM('borrador','pendiente','aprobada','rechazada') DEFAULT 'pendiente',
    aprobador_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitud_id) REFERENCES solicitud_compra(id),
    FOREIGN KEY (elaborado_por_id) REFERENCES empleados(id) ON DELETE SET NULL,
    FOREIGN KEY (proveedor_seleccionado_id) REFERENCES proveedores(id) ON DELETE SET NULL,
    FOREIGN KEY (aprobador_id) REFERENCES empleados(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle: Cotización por Proveedor
CREATE TABLE cotizaciones_proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cotizacion_id INT NOT NULL,
    proveedor_id INT NOT NULL,
    num_cotizacion VARCHAR(50),
    fecha_cotizacion DATE,
    total DECIMAL(10,2) DEFAULT 0.00,
    condiciones VARCHAR(200),
    tiempo_entrega VARCHAR(100),
    notas TEXT,
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Órdenes de Compra
CREATE TABLE ordenes_compra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    cotizacion_id INT,
    solicitud_id INT,
    proveedor_id INT NOT NULL,
    fecha DATE NOT NULL,
    fecha_entrega DATE,
    condiciones_pago VARCHAR(200),
    lugar_entrega VARCHAR(200),
    subtotal DECIMAL(10,2) DEFAULT 0.00,
    descuento DECIMAL(10,2) DEFAULT 0.00,
    impuestos DECIMAL(10,2) DEFAULT 0.00,
    impuesto_turismo DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) DEFAULT 0.00,
    proyecto_id INT,
    estado ENUM('emitida','parcial','completa','anulada') DEFAULT 'emitida',
    aprobador_id INT,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE SET NULL,
    FOREIGN KEY (solicitud_id) REFERENCES solicitud_compra(id) ON DELETE SET NULL,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE SET NULL,
    FOREIGN KEY (aprobador_id) REFERENCES empleados(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle de Órdenes de Compra
CREATE TABLE ordenes_compra_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    descripcion VARCHAR(300) NOT NULL,
    unidad VARCHAR(50),
    cantidad DECIMAL(10,2) DEFAULT 1.00,
    precio_unitario DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) DEFAULT 0.00,
    cuenta_id INT,
    FOREIGN KEY (orden_id) REFERENCES ordenes_compra(id) ON DELETE CASCADE,
    FOREIGN KEY (cuenta_id) REFERENCES cuentas_contables(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Órdenes de Pago
CREATE TABLE ordenes_pago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    proceso_id INT NULL,
    fecha DATE NOT NULL,
    beneficiario VARCHAR(200) NOT NULL,
    beneficiario_tipo ENUM('proveedor','empleado','otro') DEFAULT 'proveedor',
    proveedor_id INT,
    empleado_id INT,
    concepto TEXT NOT NULL,
    monto DECIMAL(10,2) DEFAULT 0.00,
    forma_pago ENUM('cheque','transferencia','efectivo') DEFAULT 'transferencia',
    numero_cheque VARCHAR(50),
    factura_proveedor VARCHAR(50),
    banco VARCHAR(100),
    banco_tipo ENUM('ahorros','cheques') NULL,
    cuenta_bancaria VARCHAR(50),
    cuenta_bancaria_id INT,
    orden_compra_id INT,
    solicitud_gastos_id INT,
    cuenta_id INT,
    proyecto_id INT,
    estado ENUM('pendiente','aprobada','pagada','anulada') DEFAULT 'pendiente',
    aprobador1_id INT,
    aprobador2_id INT,
    fecha_pago DATE,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proveedor_id)        REFERENCES proveedores(id)      ON DELETE SET NULL,
    FOREIGN KEY (empleado_id)         REFERENCES empleados(id)         ON DELETE SET NULL,
    FOREIGN KEY (orden_compra_id)     REFERENCES ordenes_compra(id)   ON DELETE SET NULL,
    FOREIGN KEY (solicitud_gastos_id) REFERENCES solicitud_gastos(id) ON DELETE SET NULL,
    FOREIGN KEY (cuenta_id)           REFERENCES cuentas_contables(id) ON DELETE SET NULL,
    FOREIGN KEY (proyecto_id)         REFERENCES proyectos(id)         ON DELETE SET NULL,
    FOREIGN KEY (proceso_id)          REFERENCES procesos_viaticos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notas de Recepción
CREATE TABLE notas_recepcion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    orden_compra_id INT,
    proveedor_id INT NOT NULL,
    fecha DATE NOT NULL,
    factura_proveedor VARCHAR(50),
    fecha_factura DATE,
    receptor_id INT,
    estado ENUM('completa','parcial','devolucion') DEFAULT 'completa',
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (orden_compra_id) REFERENCES ordenes_compra(id) ON DELETE SET NULL,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (receptor_id) REFERENCES empleados(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle de Notas de Recepción
CREATE TABLE notas_recepcion_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nota_id INT NOT NULL,
    descripcion VARCHAR(300) NOT NULL,
    unidad VARCHAR(50),
    cantidad_pedida DECIMAL(10,2),
    cantidad_recibida DECIMAL(10,2) DEFAULT 0.00,
    precio_unitario DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) DEFAULT 0.00,
    estado_item ENUM('bueno','danado','incompleto') DEFAULT 'bueno',
    observacion VARCHAR(200),
    FOREIGN KEY (nota_id) REFERENCES notas_recepcion(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Presupuesto Anual por Cuenta/Proyecto
CREATE TABLE presupuesto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    anio INT NOT NULL,
    proyecto_id INT,
    cuenta_id INT NOT NULL,
    monto_presupuestado DECIMAL(12,2) DEFAULT 0.00,
    notas TEXT,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE SET NULL,
    FOREIGN KEY (cuenta_id) REFERENCES cuentas_contables(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activos Fijos
CREATE TABLE activos_fijos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) UNIQUE NOT NULL,
    descripcion VARCHAR(200) NOT NULL,
    categoria VARCHAR(100),
    fecha_adquisicion DATE,
    costo_adquisicion DECIMAL(10,2) DEFAULT 0.00,
    vida_util_anios INT,
    porcentaje_depreciacion DECIMAL(5,2),
    valor_residual DECIMAL(10,2) DEFAULT 0.00,
    valor_libro DECIMAL(10,2) DEFAULT 0.00,
    ubicacion VARCHAR(100),
    responsable_id INT,
    numero_serie VARCHAR(100),
    proveedor_id INT,
    estado ENUM('activo','baja','vendido','robado','donado') DEFAULT 'activo',
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (responsable_id) REFERENCES empleados(id) ON DELETE SET NULL,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuración del sistema
CREATE TABLE configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descripcion VARCHAR(200)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditoría
CREATE TABLE auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    accion VARCHAR(100),
    tabla_afectada VARCHAR(100),
    registro_id INT,
    ip VARCHAR(45),
    detalle TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
