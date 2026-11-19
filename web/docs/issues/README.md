# Backlog inicial de InfraTicket

Estos seis PRD ligeros son un punto de partida para debatir el producto. El proyecto ya cuenta con inicio de sesión, usuarios con los roles administrador, técnico y solicitante, y usuarios de prueba. El panel está vacío: todavía no existen incidencias, gestión de usuarios ni permisos por rol.

| Número | Título | Dependencias |
|---|---|---|
| 1 | [Alta de incidencias](01-alta-de-incidencias.md) | Ninguna |
| 2 | [Asignación y seguimiento](02-asignacion-y-seguimiento.md) | 1 |
| 3 | [Comentarios en la incidencia](03-comentarios-en-la-incidencia.md) | 1 |
| 4 | [Gestión de usuarios](04-gestion-de-usuarios.md) | Ninguna |
| 5 | [Sedes y activos](05-sedes-y-activos.md) | 1; modifica el alta y la consulta de incidencias |
| 6 | [Incidencias por estado](06-widget-incidencias-por-estado.md) | 1 |

Orden recomendado: **1 → 2 → 3 → 4 → 5 → 6**. Primero se establece el registro de incidencias y después su atención y conversación; a continuación, la gestión de usuarios, el contexto de sedes y activos y el resumen del panel.

El issue 4 puede abordarse de forma independiente desde el inicio. Tras el issue 1, los issues 2, 3, 5 y 6 no dependen unos de otros. El orden recomendado no añade dependencias; las preguntas abiertas de cada PRD deberán concretarse antes de implementar.
