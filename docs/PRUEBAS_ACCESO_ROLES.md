# Pruebas de acceso por rol - LogiCo

## URL correcta del proyecto

Si la carpeta está en `C:\xampp\htdocs\logico_entrega3`, la URL correcta es:

```txt
http://localhost/logico_entrega3/
```

No usar directamente:

```txt
http://localhost/vistas_rol/motorista/index.php
```

Esa ruta omite la carpeta del proyecto y Apache responderá `Not Found`.

## Usuarios de prueba

Todos usan la contraseña:

```txt
Logico123
```

| Correo | Rol esperado | Vista esperada |
|---|---|---|
| admin@logico.cl | Administrador | Panel Administrador |
| motorista@logico.cl | Motorista | Panel Motorista |
| central@logico.cl | Farmacia Central | Panel Farmacia Central |
| operador@logico.cl | Operador Control Despacho | Panel Operador Control de Despacho |
| local@logico.cl | Local Despacho | Panel Local de Despacho |

## Flujo correcto para probar perfiles

1. Entrar a `http://localhost/logico_entrega3/`.
2. Iniciar sesión con un usuario.
3. Verificar que el sistema derive al panel correspondiente.
4. Para probar otro rol, seleccionar el menú del perfil en el navbar y presionar `Cerrar sesión`.
5. Volver a iniciar sesión con otro usuario.

## Verificación en base de datos

Ejecutar en phpMyAdmin:

```sql
SELECT id, nombre, correo, rol, motorista_id, farmacia_id, estado
FROM usuarios
ORDER BY id;
```

Cada correo debe tener un rol distinto. Si todos aparecen como `Administrador`, importar el script:

```txt
database/scripts/08_verificar_actualizar_usuarios_roles.sql
```
