USE logico_entrega3;

-- Data de prueba alineada a 3.1.1.1: 4 tipos de movimiento, 3 farmacias/locales y 3 motoristas distintos.
INSERT INTO farmacias (codigo, nombre, direccion, comuna, provincia, region, telefono, tipo, estado) VALUES
('F000', 'Farmacia Cruz Verde Central', 'Av. Libertador Bernardo O\'Higgins 1449', 'Santiago', 'Santiago', 'Región Metropolitana de Santiago', '+56911112222', 'Central', 'Activa'),
('F001', 'Farmacia Cruz Verde Local Arica', 'Av. Santa María 2401', 'Arica', 'Arica', 'Región de Arica y Parinacota', '+56922223333', 'Local', 'Activa'),
('F002', 'Farmacia Cruz Verde Local Norte', 'Av. Recoleta 455', 'Recoleta', 'Santiago', 'Región Metropolitana de Santiago', '+56933334444', 'Local', 'Activa'),
('F003', 'Farmacia Cruz Verde Local Austral', 'Av. Colón 980', 'Punta Arenas', 'Magallanes', 'Región de Magallanes y de la Antártica Chilena', '+56944445555', 'Local', 'Activa');

INSERT INTO motoristas (farmacia_id, rut, nombres, apellidos, direccion, comuna, provincia, region, telefono, correo, licencia, estado) VALUES
(2, '11111111-1', 'Felipe', 'Rojas', 'Pasaje Azapa 1254', 'Arica', 'Arica', 'Región de Arica y Parinacota', '+56955556661', 'felipe.rojas@logico.cl', 'Clase C', 'Activo'),
(3, '12222222-2', 'Carlos', 'Muñoz', 'Av. México 865', 'Recoleta', 'Santiago', 'Región Metropolitana de Santiago', '+56955556662', 'carlos.munoz@logico.cl', 'Clase C', 'Activo'),
(4, '13333333-3', 'Diego', 'Vargas', 'Calle Manantiales 321', 'Punta Arenas', 'Magallanes', 'Región de Magallanes y de la Antártica Chilena', '+56955556663', 'diego.vargas@logico.cl', 'Clase C', 'Activo');

INSERT INTO motos (motorista_id, patente, marca, modelo, anio, estado) VALUES
(1, 'ARIC12', 'Honda', 'CB 125F', 2021, 'En uso'),
(2, 'RECO34', 'Yamaha', 'FZ 150', 2022, 'En uso'),
(3, 'PARE56', 'Suzuki', 'Gixxer 150', 2020, 'En uso');

INSERT INTO usuarios (nombre, correo, password_hash, rol, motorista_id, farmacia_id, estado) VALUES
('Administrador LogiCo', 'admin@logico.cl', '$2y$12$/Tz9X9r1Jk9pkE6Pu9i3GeoDXJhU9oNhyqM3zZx.X/xBNni9WEQ7S', 'Administrador', NULL, NULL, 'Activo'),
('Felipe Rojas Motorista', 'motorista.arica@logico.cl', '$2y$12$/Tz9X9r1Jk9pkE6Pu9i3GeoDXJhU9oNhyqM3zZx.X/xBNni9WEQ7S', 'Motorista', 1, NULL, 'Activo'),
('Carlos Muñoz Motorista', 'motorista@logico.cl', '$2y$12$/Tz9X9r1Jk9pkE6Pu9i3GeoDXJhU9oNhyqM3zZx.X/xBNni9WEQ7S', 'Motorista', 2, NULL, 'Activo'),
('Diego Vargas Motorista', 'motorista.austral@logico.cl', '$2y$12$/Tz9X9r1Jk9pkE6Pu9i3GeoDXJhU9oNhyqM3zZx.X/xBNni9WEQ7S', 'Motorista', 3, NULL, 'Activo'),
('Usuario Farmacia Central', 'central@logico.cl', '$2y$12$/Tz9X9r1Jk9pkE6Pu9i3GeoDXJhU9oNhyqM3zZx.X/xBNni9WEQ7S', 'Farmacia Central', NULL, 1, 'Activo'),
('Operador Control Despacho', 'operador@logico.cl', '$2y$12$/Tz9X9r1Jk9pkE6Pu9i3GeoDXJhU9oNhyqM3zZx.X/xBNni9WEQ7S', 'Operador Control Despacho', NULL, NULL, 'Activo'),
('Local Arica', 'local.arica@logico.cl', '$2y$12$/Tz9X9r1Jk9pkE6Pu9i3GeoDXJhU9oNhyqM3zZx.X/xBNni9WEQ7S', 'Local Despacho', NULL, 2, 'Activo'),
('Local Recoleta', 'local@logico.cl', '$2y$12$/Tz9X9r1Jk9pkE6Pu9i3GeoDXJhU9oNhyqM3zZx.X/xBNni9WEQ7S', 'Local Despacho', NULL, 3, 'Activo'),
('Local Austral', 'local.austral@logico.cl', '$2y$12$/Tz9X9r1Jk9pkE6Pu9i3GeoDXJhU9oNhyqM3zZx.X/xBNni9WEQ7S', 'Local Despacho', NULL, 4, 'Activo');

INSERT INTO asignaciones_farmacia (farmacia_id, motorista_id, estado, observacion) VALUES
(2, 1, 'Activa', 'Felipe Rojas asignado a Local Arica para prueba de cobertura norte'),
(3, 2, 'Activa', 'Carlos Muñoz asignado a Local Norte / Recoleta'),
(4, 3, 'Activa', 'Diego Vargas asignado a Local Austral / Punta Arenas');

INSERT INTO asignaciones_moto (moto_id, motorista_id, estado, observacion) VALUES
(1, 1, 'Activa', 'Moto ARIC12 asignada a Felipe Rojas'),
(2, 2, 'Activa', 'Moto RECO34 asignada a Carlos Muñoz'),
(3, 3, 'Activa', 'Moto PARE56 asignada a Diego Vargas');

INSERT INTO movimientos (codigo_pedido, tipo, farmacia_origen_id, farmacia_destino_id, motorista_id, moto_id, fecha_movimiento, fecha_entrega, cliente_nombre, direccion_entrega, telefono_cliente, descripcion, requiere_receta, receta_retirada, disponibilidad_producto, estado, incidencia_descripcion, creado_por_usuario_id) VALUES
('PED-ARICA-0001', 'Directo', 2, NULL, 1, 1, NOW(), NULL, 'Cliente Arica Directo', 'Chacabuco 520, Arica', '+56910000001', 'Despacho directo desde Local Arica', 0, 0, 'Disponible', 'En curso', NULL, 5),
('PED-ARICA-0002', 'Receta', 2, NULL, 1, 1, DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL, 'Cliente Arica Receta', '21 de Mayo 1050, Arica', '+56910000002', 'Pedido con receta retenida; requiere retiro previo', 1, 0, 'Disponible', 'Asignado a motorista', NULL, 5),
('PED-RECO-0001', 'Traslado', 3, 4, 2, 2, DATE_SUB(NOW(), INTERVAL 2 HOUR), NULL, 'Farmacia Cruz Verde Local Austral', 'Av. Colón 980, Punta Arenas', '+56944445555', 'Traslado de productos entre locales', 0, 0, 'Disponible', 'Listo para retiro', NULL, 5),
('PED-RECO-0002', 'Directo', 3, NULL, 2, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 'Cliente Recoleta Terminado', 'Av. Recoleta 3400, Recoleta', '+56910000003', 'Despacho terminado correctamente', 0, 0, 'Disponible', 'Terminado', NULL, 5),
('PED-AUST-0001', 'Reenvio', 4, NULL, 3, 3, DATE_SUB(NOW(), INTERVAL 3 HOUR), NULL, 'Cliente Austral Reenvío', 'Bories 750, Punta Arenas', '+56910000004', 'Reenvío por cliente ausente en primer intento', 0, 0, 'Disponible', 'Incidencia', 'Cliente no se encontraba en domicilio. Se solicita reprogramación.', 5),
('PED-AUST-0002', 'Directo', 4, NULL, 3, 3, DATE_SUB(NOW(), INTERVAL 4 HOUR), NULL, 'Cliente Austral Pendiente', 'España 1210, Punta Arenas', '+56910000005', 'Pedido directo pendiente de preparación por local austral', 0, 0, 'Pendiente', 'Pendiente local', NULL, 5);

INSERT INTO incidencias (movimiento_id, motorista_id, usuario_id, descripcion, estado) VALUES
(5, 3, 4, 'Cliente no se encontraba en domicilio. Se deja constancia para reenvío.', 'Abierta');

INSERT INTO historial_movimientos (movimiento_id, usuario_id, estado_anterior, estado_nuevo, observacion) VALUES
(1, 6, 'Asignado a motorista', 'En curso', 'Pedido retirado por Felipe Rojas desde Local Arica'),
(2, 5, NULL, 'Asignado a motorista', 'Orden con receta generada por Farmacia Central y asignada a Felipe Rojas'),
(3, 8, 'Preparando', 'Listo para retiro', 'Local Norte dejó listo el traslado'),
(4, 3, 'En curso', 'Terminado', 'Carlos Muñoz registró entrega correcta'),
(5, 4, 'En curso', 'Incidencia', 'Diego Vargas reportó cliente ausente'),
(6, 5, NULL, 'Pendiente local', 'Orden creada por Farmacia Central para Local Austral');
