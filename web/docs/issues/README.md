# Backlog inicial de InfraTicket

Estos PRD ligeros describen capacidades propuestas para InfraTicket. Las preguntas abiertas deben concretarse antes de implementar. Los números son identificadores locales del backlog.

| Número | Título | Dependencias |
| --- | --- | --- |
| 1 | [Alta de incidencias](01-alta-de-incidencias.md) | Ninguna |
| 2 | [Asignación y seguimiento](02-asignacion-y-seguimiento.md) | 1 |
| 3 | [Comentarios en la incidencia](03-comentarios-en-la-incidencia.md) | 1 |
| 4 | [Gestión de usuarios](04-gestion-de-usuarios.md) | Ninguna |
| 5 | [Sedes y activos](05-sedes-y-activos.md) | 1; modifica el alta y la consulta de incidencias |
| 6 | [Widget de incidencias por estado](06-widget-incidencias-por-estado.md) | 1 |
| 7 | [Clasificación automática de incidencias con OpenRouter](07-automatic-ticket-classification.md) | 1; modifica el alta y la edición de incidencias |

## Orden recomendado

1. **Alta de incidencias (1):** establece la capacidad central de producto.
2. **Gestión de usuarios (4):** permite mantener las cuentas y los roles desde el panel; también puede abordarse antes del issue 1 al ser independiente.
3. **Asignación y seguimiento (2):** incorpora responsabilidad y evolución de las incidencias.
4. **Comentarios en la incidencia (3):** reúne las aclaraciones durante la atención.
5. **Sedes y activos (5):** añade contexto y adapta el comportamiento del alta existente.
6. **Widget de incidencias por estado (6):** resume el volumen una vez aclarado qué significa «abiertas».
7. **Clasificación automática de incidencias (7):** propone categoría y urgencia mediante OpenRouter y permite su corrección por el técnico.

Los issues 2, 3, 5 y 6 solo dependen formalmente del 1; su posición relativa es una recomendación. Al concretar el issue 4, conviene revisar el efecto de eliminar usuarios sobre incidencias, asignaciones y comentarios.
