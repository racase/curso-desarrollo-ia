# Verificación de incidencias, seguimiento y comentarios

Cambio: `add-ticket-management`. Fecha: 16 de noviembre de 2026.

## Resultado

- PHPUnit: **47 pruebas y 340 comprobaciones**, sin fallos ni avisos, ejecutando `vendor/bin/phpunit --fail-on-warning`.
- Laravel Pint: comprobación de formato con `vendor/bin/pint --test`.
- OpenSpec: validación estricta mediante `openspec validate add-ticket-management --strict --no-interactive`.
- Migración y reversión verificadas en SQLite y PostgreSQL desechables.

Se utilizó Podman, la alternativa documentada a Docker. La suite final se ejecutó en un proyecto Compose independiente (`infraticket-check`) de la instancia de revisión en navegador (`infraticket-test`), sin compartir datos PostgreSQL. Ambos usan la estructura de `compose.testing.yaml`.

El lanzador `artisan test` intentó consultar un archivo de entorno inexistente y emitió avisos. La verificación final utilizó PHPUnit directamente; `Tests\TestCase` y el proceso auxiliar de concurrencia omiten los archivos de entorno locales y utilizan configuración explícita de pruebas. No se crearon ni modificaron archivos `.env`.

## Correspondencia entre especificaciones y pruebas

| Capacidad / requisitos | Evidencia |
| --- | --- |
| Alta, límites y valores iniciales; identidad no manipulable | `TicketManagementTest`: creación, campos inválidos y atributos protegidos; `TicketResourceTest`: alta desde formulario y errores vinculados a campos. |
| Visibilidad por rol, acceso directo y edición | `TicketManagementTest`: aislamiento y permisos por estado; `TicketResourceTest`: listado, acceso ajeno, acceso anónimo y edición. |
| Listado, filtros combinados, resultado vacío y ficha | `TicketResourceTest`: filtros con visibilidad, ficha de propuesta y consulta del equipo. |
| Asignación, reasignación y destinatarios permitidos | `TicketTrackingTest`: asignación, conservación de estado, retirada inválida y denegación por rol o cierre. |
| Flujo completo y autorización de transiciones | `TicketTrackingTest`: todas las parejas de los cinco estados, apertura sin técnico y permisos del responsable; `PostgresTicketTest`: recorrido completo con los tres roles. |
| Historial, autoría, orden y actualización de la ficha | `TicketTrackingTest`: persistencia y orden; `TicketResourceTest`: acciones, autor visible y refresco mediante evento; `TicketCommentsTest`: denegación del acceso directo al historial ajeno. |
| Atomicidad y formularios desactualizados | `TicketTrackingTest`: reversión ante fallo y versión desactualizada; `TicketResourceTest`: error de versión en el formulario. |
| Concurrencia real de seguimiento | `PostgresTicketTest`: dos procesos con conexiones independientes, espera de bloqueo confirmada y rechazo de transición o reasignación obsoleta. |
| Comentarios, límites y autoría no manipulable | `TicketCommentsTest`: publicación con los tres roles, límites inclusivos, espacios en blanco y errores en el campo. |
| Conversación, orden, paginación y texto seguro | `TicketCommentsTest`: orden estable, paginación y escape de HTML. |
| Privacidad, conservación y cierre de comentarios | `TicketCommentsTest`: acceso ajeno denegado, conversación cerrada consultable y ausencia de acciones de edición/eliminación. |
| Cierre concurrente con publicación | `PostgresTicketTest`: el comentario espera el bloqueo y se rechaza si el cierre se confirma primero. |
| Regresión del acceso existente | `AdminLoginTest` y las pruebas de ejemplo originales. |

## Revisión en navegador

Se revisó una ventana automatizada en `http://127.0.0.1:18000`, primero sin interfaz visible y después en modo visible a petición del usuario:

1. El solicitante creó una propuesta con prioridad media y solicitante automático, sin técnico asignado, y publicó un comentario.
2. El administrador asignó un técnico y abrió la propuesta. El selector ofreció únicamente usuarios técnicos.
3. El técnico asignado avanzó por en curso, resuelta y cerrada. La ficha cerrada dejó de ofrecer edición, asignación y transición.
4. La revisión detectó un historial desactualizado tras las acciones; se corrigió y se añadió una prueba de regresión. Una segunda incidencia confirmó que la transición aparece sin recargar la página.
5. Se dejó una ventana visible con la sesión del administrador de demostración para revisión del usuario.

La base de desarrollo no se utilizó ni se migró. Para incorporar el cambio a ese entorno, ejecutar las migraciones pendientes siguiendo el procedimiento habitual, sin recrear su base.
