# Mapeo de historias de usuario HU016 a HU033 - LogiCo Entrega 3

| HU | Historia de usuario | Módulo / archivo | Evidencia sugerida |
|---|---|---|---|
| HU016 | Asignar motos a motoristas | `asignaciones.php` | Captura del formulario y tabla de historial de motos asignadas. |
| HU017 | Asignar motoristas a farmacias | `asignaciones.php` | Captura del formulario de asignación de motorista a farmacia. |
| HU018 | Reemplazar motoristas en farmacias | `asignaciones.php` | Captura donde una asignación anterior queda como reemplazada y la nueva como activa. |
| HU019 | Agregar movimiento directo | `movimientos.php` | Captura de registro tipo Directo. |
| HU020 | Agregar movimiento con receta | `movimientos.php` | Captura de registro tipo Receta con checkbox de receta. |
| HU021 | Agregar movimiento con traslado | `movimientos.php` | Captura de registro tipo Traslado con farmacia destino. |
| HU022 | Agregar movimiento con reenvío | `movimientos.php` | Captura de registro tipo Reenvio. |
| HU023 | Modificar movimiento | `movimientos.php?action=edit&id=...` | Captura antes/después de modificación. |
| HU024 | Anular movimiento | `movimientos.php` | Captura del estado Anulado. |
| HU026 | Generar reporte diario | `reportes.php?scope=diario` | Captura de reporte diario con resumen y detalle. |
| HU027 | Generar reporte mensual | `reportes.php?scope=mensual` | Captura de reporte mensual. |
| HU028 | Generar reporte anual | `reportes.php?scope=anual` | Captura de reporte anual. |
| HU029 | Iniciar sesión | `login.php` | Captura del login exitoso. |
| HU030 | Cerrar sesión | `logout.php` | Captura de retorno al login. |
| HU031 | Recuperar contraseña | `recuperar_contrasena.php` | Captura de generación de enlace temporal. |
| HU032 | Modificar contraseña | `modificar_contrasena.php` | Captura de cambio de contraseña. |
| HU033 | Expirar sesión | `includes/functions.php` | Captura o explicación técnica de `SESSION_TIMEOUT_SECONDS`. |

## Relación con rúbrica Entrega 3

- **3.1.1.1 Coherencia funcional:** se agregan módulos reales del negocio logístico.
- **3.1.1.2 Lineamientos estéticos y funcionales:** se mantiene navegación Bootstrap, botones homogéneos y tablas responsivas.
- **3. Estructura de base de datos:** se agregan tablas normalizadas para asignaciones, movimientos y usuarios.
- **4. Optimización y normalización:** se incorporan llaves foráneas, índices y separación de entidades.
- **3.1.3.5 Seguridad:** se implementa autenticación, cierre de sesión, expiración, hash de contraseña y consultas preparadas.
- **3.1.4.8 Documentación de implementación:** el código contiene comentarios en funciones críticas y se agrega este mapeo.
- **3.1.5.9 a 3.1.6.13 Pruebas y mejora:** los módulos nuevos permiten generar casos de prueba y métricas reales.
