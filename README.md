# LogiCo - Entrega 3

Proyecto funcional PHP + MySQL preparado para XAMPP.

## Instalación

1. Copiar la carpeta `logico_entrega3` dentro de `C:\xampp\htdocs\`.
2. Iniciar Apache y MySQL en XAMPP.
3. Entrar a phpMyAdmin e importar `database/logico.sql`.
4. Abrir `http://localhost/logico_entrega3/`.

## Base de datos

Nombre esperado: `logico_entrega3`.

El script carga datos de prueba para evidenciar la rúbrica 3.1.1.1:

- 4 tipos de movimientos: Directo, Receta, Traslado y Reenvio.
- 3 locales distintos: Arica, Recoleta y Punta Arenas.
- 3 motoristas distintos: Felipe Rojas, Carlos Muñoz y Diego Vargas.
- Listado general visible en el panel Operador Control de Despacho.

## Usuarios de prueba

Todos usan la contraseña: `Logico123`.

| Rol | Correo |
|---|---|
| Administrador | admin@logico.cl |
| Farmacia Central | central@logico.cl |
| Operador Control Despacho | operador@logico.cl |
| Motorista Arica | motorista.arica@logico.cl |
| Motorista Recoleta | motorista@logico.cl |
| Motorista Punta Arenas | motorista.austral@logico.cl |
| Local Arica | local.arica@logico.cl |
| Local Recoleta | local@logico.cl |
| Local Punta Arenas | local.austral@logico.cl |

## Rutas principales

- `index.php`: redirige automáticamente según rol.
- `modulos_page/`: módulos administrativos.
- `seguridad/`: login, logout, recuperar contraseña y cambiar contraseña.
- `vistas_rol/`: paneles separados por rol.

## Seguridad aplicada

- Contraseñas con `password_hash` y `password_verify`.
- Control de sesión y expiración por inactividad.
- Control de acceso por rol mediante `require_role()`.
- Consultas preparadas con PDO.
- Sanitización de salida HTML con `e()`.
- Transacción al asignar motorista y moto desde Control de Despacho.


Actualización geográfica Chile:
- Farmacias y motoristas registran dirección completa, comuna, provincia y región.
- Los datos de prueba usan regiones distintas: Región de Arica y Parinacota, Región Metropolitana de Santiago y Región de Magallanes y de la Antártica Chilena.

## Módulo de creación de cuentas de usuario

El módulo `modulos_page/usuarios.php` queda disponible solo para el rol Administrador. Se accede desde el menú desplegable del perfil del Administrador, junto a las opciones `Cambiar contraseña` y `Cerrar sesión`.

Reglas implementadas:

- **Motorista:** la cuenta solo se puede crear si el motorista ya existe en el mantenedor de Motoristas. La cuenta queda vinculada mediante `usuarios.motorista_id`.
- **Local Despacho:** la cuenta solo se puede crear si el local ya existe como farmacia tipo `Local`. La cuenta queda vinculada mediante `usuarios.farmacia_id`.
- **Farmacia Central:** puede crearse sin restricción previa. El vínculo con una farmacia central es opcional.
- **Operador Control Despacho:** puede crearse sin restricción previa.
- **Administrador:** puede crearse sin restricción previa.

Las contraseñas nuevas se guardan con `password_hash()` y el inicio de sesión valida con `password_verify()`.
