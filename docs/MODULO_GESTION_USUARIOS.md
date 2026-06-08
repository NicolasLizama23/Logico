# Módulo de Gestión de Usuarios

Este módulo fue agregado para que el Administrador pueda crear cuentas de acceso dentro del sistema LogiCo sin romper la separación por roles.

## Ruta

`modulos_page/usuarios.php`

## Acceso

Solo puede acceder el rol `Administrador`.

## Ubicación en la interfaz

En el navbar del Administrador, dentro del menú desplegable del perfil, se agregó la opción:

`Crear cuentas de usuarios`

Esta opción aparece junto a:

- Cambiar contraseña
- Cerrar sesión

## Reglas de negocio implementadas

| Rol de cuenta | Restricción |
|---|---|
| Administrador | No requiere registro previo. |
| Farmacia Central | No requiere registro previo. Puede vincularse opcionalmente a una farmacia central. |
| Operador Control Despacho | No requiere registro previo. |
| Motorista | Requiere seleccionar un motorista activo existente en el mantenedor de Motoristas. |
| Local Despacho | Requiere seleccionar una farmacia activa de tipo Local existente en el mantenedor de Farmacias. |

## Validaciones

- El correo debe tener formato válido.
- El correo no puede estar duplicado.
- La contraseña debe tener al menos 6 caracteres.
- La confirmación de contraseña debe coincidir.
- No se permite crear una cuenta de Motorista sin vincularla a un motorista registrado.
- No se permite crear una cuenta de Local Despacho sin vincularla a una farmacia/local registrada.
- No se permite crear más de una cuenta para el mismo motorista.
- No se permite crear más de una cuenta para el mismo local de despacho.

## Buenas prácticas aplicadas

- Separación del módulo en archivo independiente.
- Acceso protegido con `require_role(['Administrador'])`.
- Uso de consultas preparadas PDO.
- Uso de `password_hash()` para almacenar contraseñas.
- Sanitización de salida con la función `e()`.
- Validación de datos antes de insertar en base de datos.
- Uso de claves foráneas existentes `motorista_id` y `farmacia_id` para mantener trazabilidad.
