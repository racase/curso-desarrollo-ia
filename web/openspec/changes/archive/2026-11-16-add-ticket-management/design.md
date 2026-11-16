# Design

## Context

La motivación y alcance se describen en [proposal.md](proposal.md). El proyecto usa Laravel 13 y Filament 5; `User` ya contiene `UserRole` y `hasRole()`. `AdminPanelProvider` descubre recursos en `app/Filament/Resources`. Las pruebas de acceso utilizan PHPUnit, Livewire y `RefreshDatabase`; la configuración habitual usa SQLite en memoria frente a PostgreSQL en desarrollo.

Los contratos se separan en `ticket-management`, `ticket-tracking` y `ticket-comments`. No existen especificaciones principales previas ni datos de incidencias que migrar.

## Goals / Non-Goals

**Goals:** mantener las reglas fuera de las pantallas, autorizar cada operación en el servidor, conservar coherencia entre estado e historial y permitir entregas incrementales con pruebas por capacidad.

**Non-Goals:** API pública, dependencias nuevas, abstracciones genéricas de flujos o auditoría completa. La gestión de usuarios y su eliminación desde el panel pertenecen al issue 04.

## Decisions

### 1. Persistencia explícita y compatible

Crear `Ticket`, `TicketStatusChange` y `TicketComment`, y enumeraciones `TicketStatus` y `TicketPriority` con valores en inglés y etiquetas en español. Usar valores de texto con conversión a enumeraciones, evitando tipos enumerados exclusivos de PostgreSQL para mantener compatibilidad con las pruebas SQLite.

`TicketStatus` representará propuesta (`proposed`), abierta (`open`), en curso (`in_progress`), resuelta (`resolved`) y cerrada (`closed`). El alta establecerá propuesta; las únicas transiciones serán propuesta → abierta → en curso → resuelta → cerrada.

`Ticket` contendrá título, descripción, estado, prioridad, solicitante, técnico opcional, fechas y una versión entera para cambios de seguimiento. Cada transición referenciará incidencia, estado anterior, nuevo estado, autor y fecha. Cada comentario referenciará incidencia, autor, contenido y fecha. Usar identificador como segundo criterio de orden cronológico.

Añadir claves foráneas restrictivas para conservar usuarios referenciados y no perder autoría; revisar esta decisión al implementar el issue 04. No añadir borrado lógico ni copias del nombre: no existe gestión de eliminación en este alcance. Indexar solicitante, estado/prioridad y las relaciones de comentarios e historial con la incidencia.

### 2. Autorización centralizada

Una política para incidencias definirá consulta, alta, edición, asignación, transición y publicación de comentarios. La consulta del recurso restringirá además los resultados del solicitante. Las operaciones relacionadas cargarán la incidencia y comprobarán su autorización, sin confiar en identificadores o acciones visibles enviados por el cliente.

| Operación | Solicitante | Técnico | Administrador |
| --- | --- | --- | --- |
| Crear a su nombre | Sí | Sí | Sí |
| Consultar ficha, historial y comentarios | Propias | Todas | Todas |
| Editar título, descripción y prioridad | Propias en propuesta o abierta | No cerradas | No cerradas |
| Asignar o reasignar a un técnico | No | No cerradas | No cerradas |
| Avanzar estado | No | Solo asignadas | Todas, con transición válida |
| Publicar comentarios | Propias no cerradas | Todas no cerradas | Todas no cerradas |

Las cerradas serán de solo consulta. No habrá eliminación de incidencias ni edición o eliminación de comentarios e historial. Autoría, solicitante y fechas se establecerán en el servidor; un formulario de datos básicos no podrá modificar estado ni asignación.

La transición propuesta → abierta mantiene los permisos de seguimiento: técnico asignado o administrador. Un administrador puede abrir una propuesta sin técnico; pasar después a en curso exige asignación. Las propuestas admiten asignación y comentarios con las reglas generales de las incidencias no cerradas.

La alternativa de autorizar únicamente en los componentes dejaría operaciones directas expuestas y duplicaría reglas.

### 3. Operaciones con transacciones y control de concurrencia

Concentrar creación, edición, asignación, transición y publicación en operaciones específicas reutilizables desde Filament. Centralizar la validación de campos en esas operaciones para cubrir también llamadas manipuladas.

Las operaciones que escriben sobre una incidencia bloquearán su registro dentro de una transacción, releerán estado, asignación y permisos y validarán antes de guardar. Asignación y transición exigirán la versión de seguimiento que vio el usuario; cada una incrementará dicha versión. Una discrepancia devolverá un mensaje para actualizar la ficha.

La transición guardará estado e historial en la misma transacción. La publicación de comentarios tomará el mismo bloqueo para serializarse con el cierre: si el cierre se confirma primero, la publicación se rechaza. La edición básica también releerá el estado para impedir cambios tras un cierre concurrente.

Se descarta confiar solo en el estado recibido por el formulario: podría sobrescribir una reasignación o permitir que el anterior responsable continuase operando.

### 4. Interfaz integrada en Filament

Un recurso `TicketResource` ofrecerá listado paginado, filtros combinables (incluido el estado propuesta), alta y ficha. Las acciones de editar, asignar y avanzar estado invocarán las operaciones anteriores. La ficha mostrará los datos básicos, el origen propuesta con fecha de alta, el historial y la conversación paginada. Los formularios usarán los límites definidos en las especificaciones y mensajes en español.

Los comentarios serán texto plano escapado. Reutilizar componentes del panel reduce estilos y JavaScript propios; no se necesita un editor enriquecido para este alcance.

### 5. Entregas y pruebas próximas al comportamiento

Primero completar alta y visibilidad; después asignación e historial; finalmente comentarios. Cada entrega incluirá sus factories, pruebas de reglas y pruebas Livewire de las pantallas. No modificar el seeder actual para insertar incidencias automáticamente.

Las pruebas SQLite cubrirán validación, permisos y reglas. Una comprobación adicional sobre PostgreSQL, en una base de pruebas separada, cubrirá bloqueos y operaciones concurrentes con conexiones independientes. No ejecutar pruebas destructivas contra la base de desarrollo.

## Risks / Trade-offs

- SQLite no reproduce los bloqueos de PostgreSQL → verificar concurrencia específicamente en PostgreSQL.
- Filtrado visual insuficiente → comprobar acceso directo y ejecución manipulada de acciones además del listado.
- Reasignación mientras otro técnico opera → versión de seguimiento y autorización tras adquirir el bloqueo.
- Texto de usuario → escapar comentarios, títulos y descripciones al mostrarlos.
- Referencias impiden eliminar usuarios → documentar la restricción y revisarla en el issue 04.
- Flujo lineal sin reapertura → decisión funcional confirmada; cualquier ampliación requiere cambiar las especificaciones.

## Migration Plan

1. Añadir migraciones aditivas para incidencias, historial y comentarios, sin modificar usuarios ni registros actuales.
2. Verificar migración y reversión en bases desechables SQLite y PostgreSQL; la reversión elimina los datos nuevos y se limita a entornos de prueba.
3. Desplegar código y ejecutar las migraciones pendientes sin `migrate:fresh`; verificar acceso con los tres roles.
4. Ante un fallo tras recibir incidencias, conservar tablas y datos, retirar temporalmente el acceso al recurso y corregir hacia delante. No revertir tablas con datos reales sin copia y un plan explícito.
