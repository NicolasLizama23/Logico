CREATE DATABASE IF NOT EXISTS logico_entrega3
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE logico_entrega3;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS historial_movimientos;
DROP TABLE IF EXISTS incidencias;
DROP TABLE IF EXISTS movimientos;
DROP TABLE IF EXISTS asignaciones_moto;
DROP TABLE IF EXISTS asignaciones_farmacia;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS motos;
DROP TABLE IF EXISTS motoristas;
DROP TABLE IF EXISTS farmacias;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE farmacias (
    id INT NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    direccion VARCHAR(180) NOT NULL,
    comuna VARCHAR(80) NOT NULL,
    provincia VARCHAR(80) NOT NULL,
    region VARCHAR(120) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    tipo ENUM('Central','Local') NOT NULL DEFAULT 'Local',
    estado ENUM('Activa','Inactiva') NOT NULL DEFAULT 'Activa',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_farmacias PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE motoristas (
    id INT NOT NULL AUTO_INCREMENT,
    farmacia_id INT NULL,
    rut VARCHAR(15) NOT NULL UNIQUE,
    nombres VARCHAR(80) NOT NULL,
    apellidos VARCHAR(80) NOT NULL,
    direccion VARCHAR(180) NOT NULL,
    comuna VARCHAR(80) NOT NULL,
    provincia VARCHAR(80) NOT NULL,
    region VARCHAR(120) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    correo VARCHAR(120) NOT NULL,
    licencia VARCHAR(40) NOT NULL,
    estado ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_motoristas PRIMARY KEY (id),
    CONSTRAINT fk_motoristas_farmacias FOREIGN KEY (farmacia_id)
        REFERENCES farmacias(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE motos (
    id INT NOT NULL AUTO_INCREMENT,
    motorista_id INT NULL,
    patente VARCHAR(12) NOT NULL UNIQUE,
    marca VARCHAR(80) NOT NULL,
    modelo VARCHAR(80) NOT NULL,
    anio INT NOT NULL,
    estado ENUM('Disponible','En uso','Mantención','Inactiva') NOT NULL DEFAULT 'Disponible',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_motos PRIMARY KEY (id),
    CONSTRAINT fk_motos_motoristas FOREIGN KEY (motorista_id)
        REFERENCES motoristas(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(120) NOT NULL,
    correo VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('Administrador','Motorista','Farmacia Central','Operador Control Despacho','Local Despacho') NOT NULL,
    motorista_id INT NULL,
    farmacia_id INT NULL,
    estado ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
    reset_token VARCHAR(120) NULL,
    reset_expira DATETIME NULL,
    ultimo_acceso DATETIME NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_usuarios PRIMARY KEY (id),
    CONSTRAINT fk_usuarios_motoristas FOREIGN KEY (motorista_id)
        REFERENCES motoristas(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_usuarios_farmacias FOREIGN KEY (farmacia_id)
        REFERENCES farmacias(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE asignaciones_moto (
    id INT NOT NULL AUTO_INCREMENT,
    moto_id INT NOT NULL,
    motorista_id INT NOT NULL,
    fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_termino DATETIME NULL,
    estado ENUM('Activa','Finalizada','Reemplazada') NOT NULL DEFAULT 'Activa',
    observacion VARCHAR(255) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_asignaciones_moto PRIMARY KEY (id),
    CONSTRAINT fk_asignaciones_moto_moto FOREIGN KEY (moto_id)
        REFERENCES motos(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_asignaciones_moto_motorista FOREIGN KEY (motorista_id)
        REFERENCES motoristas(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE asignaciones_farmacia (
    id INT NOT NULL AUTO_INCREMENT,
    farmacia_id INT NOT NULL,
    motorista_id INT NOT NULL,
    fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_termino DATETIME NULL,
    estado ENUM('Activa','Finalizada','Reemplazada') NOT NULL DEFAULT 'Activa',
    observacion VARCHAR(255) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_asignaciones_farmacia PRIMARY KEY (id),
    CONSTRAINT fk_asignaciones_farmacia_farmacia FOREIGN KEY (farmacia_id)
        REFERENCES farmacias(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_asignaciones_farmacia_motorista FOREIGN KEY (motorista_id)
        REFERENCES motoristas(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE movimientos (
    id INT NOT NULL AUTO_INCREMENT,
    codigo_pedido VARCHAR(30) NOT NULL UNIQUE,
    tipo ENUM('Directo','Receta','Traslado','Reenvio') NOT NULL DEFAULT 'Directo',
    farmacia_origen_id INT NULL,
    farmacia_destino_id INT NULL,
    motorista_id INT NULL,
    moto_id INT NULL,
    fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_entrega DATETIME NULL,
    cliente_nombre VARCHAR(120) NOT NULL,
    direccion_entrega VARCHAR(180) NOT NULL,
    telefono_cliente VARCHAR(30) NULL,
    descripcion VARCHAR(255) NULL,
    requiere_receta TINYINT(1) NOT NULL DEFAULT 0,
    receta_retirada TINYINT(1) NOT NULL DEFAULT 0,
    disponibilidad_producto ENUM('Pendiente','Disponible','No disponible') NOT NULL DEFAULT 'Pendiente',
    estado ENUM('Pendiente local','Producto no disponible','Preparando','Listo para retiro','Asignado a motorista','En curso','Terminado','No entregado','Incidencia','Anulado','Reenviado') NOT NULL DEFAULT 'Pendiente local',
    incidencia_descripcion TEXT NULL,
    motivo_anulacion VARCHAR(255) NULL,
    creado_por_usuario_id INT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_movimientos PRIMARY KEY (id),
    CONSTRAINT fk_movimientos_farmacia_origen FOREIGN KEY (farmacia_origen_id)
        REFERENCES farmacias(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_movimientos_farmacia_destino FOREIGN KEY (farmacia_destino_id)
        REFERENCES farmacias(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_movimientos_motorista FOREIGN KEY (motorista_id)
        REFERENCES motoristas(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_movimientos_moto FOREIGN KEY (moto_id)
        REFERENCES motos(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_movimientos_usuario_creador FOREIGN KEY (creado_por_usuario_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE incidencias (
    id INT NOT NULL AUTO_INCREMENT,
    movimiento_id INT NOT NULL,
    motorista_id INT NULL,
    usuario_id INT NULL,
    descripcion TEXT NOT NULL,
    estado ENUM('Abierta','Cerrada') NOT NULL DEFAULT 'Abierta',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_incidencias PRIMARY KEY (id),
    CONSTRAINT fk_incidencias_movimientos FOREIGN KEY (movimiento_id)
        REFERENCES movimientos(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_incidencias_motoristas FOREIGN KEY (motorista_id)
        REFERENCES motoristas(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_incidencias_usuarios FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE historial_movimientos (
    id INT NOT NULL AUTO_INCREMENT,
    movimiento_id INT NOT NULL,
    usuario_id INT NULL,
    estado_anterior VARCHAR(60) NULL,
    estado_nuevo VARCHAR(60) NOT NULL,
    observacion TEXT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_historial_movimientos PRIMARY KEY (id),
    CONSTRAINT fk_historial_movimientos_movimientos FOREIGN KEY (movimiento_id)
        REFERENCES movimientos(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_historial_movimientos_usuarios FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_movimientos_fecha ON movimientos(fecha_movimiento);
CREATE INDEX idx_movimientos_tipo_estado ON movimientos(tipo, estado);
CREATE INDEX idx_movimientos_motorista ON movimientos(motorista_id);
CREATE INDEX idx_movimientos_farmacia_origen ON movimientos(farmacia_origen_id);
CREATE INDEX idx_asignaciones_moto_estado ON asignaciones_moto(estado);
CREATE INDEX idx_asignaciones_farmacia_estado ON asignaciones_farmacia(estado);

