# Plan de pruebas recomendado - HU016 a HU033

| ID prueba | HU | Caso de prueba | Resultado esperado | Evidencia |
|---|---|---|---|---|
| CP-016-01 | HU016 | Asignar una moto disponible a un motorista activo | La asignación queda activa y la moto cambia a En uso | Captura formulario + historial |
| CP-017-01 | HU017 | Asignar motorista activo a una farmacia activa | La asignación queda activa y el motorista queda relacionado a la farmacia | Captura formulario + historial |
| CP-018-01 | HU018 | Reemplazar asignación de motorista en farmacia | La asignación anterior queda reemplazada y la nueva queda activa | Captura historial |
| CP-019-01 | HU019 | Registrar movimiento Directo | El movimiento se guarda con tipo Directo | Captura listado |
| CP-020-01 | HU020 | Registrar movimiento Receta | El movimiento se guarda con requiere_receta activo | Captura detalle/listado |
| CP-021-01 | HU021 | Registrar movimiento Traslado | El sistema exige farmacia destino y guarda el traslado | Captura formulario |
| CP-022-01 | HU022 | Registrar movimiento Reenvio | El movimiento se guarda con tipo Reenvio | Captura listado |
| CP-023-01 | HU023 | Modificar movimiento existente | Los datos modificados se actualizan correctamente | Captura antes/después |
| CP-024-01 | HU024 | Anular movimiento | El estado cambia a Anulado | Captura listado |
| CP-026-01 | HU026 | Generar reporte diario | Se visualizan totales y detalle del día seleccionado | Captura reporte diario |
| CP-027-01 | HU027 | Generar reporte mensual | Se visualizan totales y detalle del mes seleccionado | Captura reporte mensual |
| CP-028-01 | HU028 | Generar reporte anual | Se visualizan totales y detalle del año seleccionado | Captura reporte anual |
| CP-029-01 | HU029 | Login con usuario válido | El sistema permite ingresar al inicio | Captura login + inicio |
| CP-029-02 | HU029 | Login con contraseña incorrecta | El sistema rechaza el acceso | Captura error |
| CP-030-01 | HU030 | Cerrar sesión | El sistema destruye la sesión y vuelve al login | Captura login |
| CP-031-01 | HU031 | Recuperar contraseña | El sistema genera enlace temporal de recuperación | Captura enlace |
| CP-032-01 | HU032 | Cambiar contraseña | El sistema actualiza la contraseña con hash | Captura mensaje exitoso |
| CP-033-01 | HU033 | Dejar sesión inactiva | El sistema expira la sesión después del tiempo configurado | Captura login con mensaje de expiración |

## Tabla de métricas sugerida para el informe

| Indicador | Fórmula | Ejemplo de uso |
|---|---|---|
| Cobertura de HU | HU probadas / HU implementadas × 100 | 17/17 = 100% |
| Pruebas aprobadas | Casos aprobados / casos ejecutados × 100 | 16/18 = 88,9% |
| Defectos encontrados | Total de pruebas fallidas o con observación | 2 observaciones |
| Efectividad de seguridad | Pruebas de acceso correctas / pruebas de seguridad ejecutadas × 100 | 5/5 = 100% |

## Recomendación para plan de mejora

Cuando una prueba falle, registrar: incidencia, módulo, causa probable, prioridad, responsable y mejora propuesta. Esto permite cumplir la parte de comparación de resultados y recomendaciones de la rúbrica.
