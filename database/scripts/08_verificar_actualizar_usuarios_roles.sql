USE logico_entrega3;

-- Verificación de usuarios y roles para Entrega 3.
-- Todos usan contraseña: Logico123
SELECT id, nombre, correo, rol, motorista_id, farmacia_id, estado
FROM usuarios
ORDER BY id;

-- Verificación de data exigida en 3.1.1.1:
-- 4 tipos de movimientos, 3 farmacias/locales y motoristas distintos.
SELECT
    m.codigo_pedido,
    m.tipo,
    fo.nombre AS farmacia_origen,
    fo.comuna AS comuna_origen,
    CONCAT(mt.nombres, ' ', mt.apellidos) AS motorista,
    mo.patente,
    m.fecha_movimiento,
    m.estado
FROM movimientos m
LEFT JOIN farmacias fo ON fo.id = m.farmacia_origen_id
LEFT JOIN motoristas mt ON mt.id = m.motorista_id
LEFT JOIN motos mo ON mo.id = m.moto_id
ORDER BY m.fecha_movimiento DESC;
