# Estructura por roles - LogiCo Entrega 3

## Objetivo

Ordenar el proyecto LogiCo para que cada tipo de usuario tenga una vista funcional según sus responsabilidades dentro del proceso de despacho.

## Organización aplicada

- `modulos_page/`: contiene los módulos administrativos existentes.
- `seguridad/`: contiene login, logout, recuperar contraseña y cambiar contraseña.
- `vistas_rol/`: contiene las interfaces específicas para cada perfil de usuario.

## Roles implementados

| Rol | Carpeta | Función principal |
|---|---|---|
| Administrador | `vistas_rol/administrador/` | Administración completa del sistema |
| Motorista | `vistas_rol/motorista/` | Gestión de pedidos asignados e incidencias |
| Farmacia Central | `vistas_rol/farmacia_central/` | Creación y asignación inicial de órdenes |
| Operador Control de Despacho | `vistas_rol/operador_control/` | Monitoreo y asignación operativa |
| Local de Despacho | `vistas_rol/local_despacho/` | Preparación y entrega al motorista |

## Tablas nuevas o ampliadas

| Tabla | Uso |
|---|---|
| `usuarios` | Control de acceso y roles |
| `movimientos` | Base para órdenes, pedidos y despachos |
| `incidencias` | Registro de problemas informados por motoristas |
| `historial_movimientos` | Trazabilidad de cambios de estado |

## Flujo de trabajo recomendado para demostrar en video

1. Iniciar sesión como Farmacia Central.
2. Crear una orden de despacho y asignarla a un local.
3. Iniciar sesión como Local de Despacho.
4. Confirmar disponibilidad y marcar el pedido como listo para retiro.
5. Iniciar sesión como Operador Control de Despacho.
6. Asignar motorista y moto al pedido.
7. Iniciar sesión como Motorista.
8. Marcar el pedido en curso y luego terminado o reportar incidencia.
9. Iniciar sesión como Administrador.
10. Revisar reportes y movimientos.

## Relación con la rúbrica de Entrega 3

Esta estructura permite evidenciar:

- Código fuente organizado.
- Implementación funcional por perfiles.
- Conexión real con base de datos.
- Seguridad mediante login, cierre de sesión y cambio de contraseña.
- Trazabilidad de movimientos.
- Pruebas por rol y por módulo.
- Evidencia clara para capturas y video.
