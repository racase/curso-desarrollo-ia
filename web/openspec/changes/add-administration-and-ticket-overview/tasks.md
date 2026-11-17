# Tasks

## 1. Usuarios y credenciales — issue #4

- [x] 1.1 Añadir migraciones incrementales para la fila estable `administration_locks.users` y normalización de correos, con comprobación previa de colisiones; verificar migración y reversión en bases SQLite y PostgreSQL desechables y una prueba que rechace duplicados normalizados sin modificar cuentas.
- [x] 1.2 Crear política y acciones de alta y edición de usuarios con nombre, correo normalizado, rol reconocido y contraseña mínima de 12 caracteres; verificar con `UserManagementTest` autorización por rol, límites, unicidad, creación autenticable y conservación de contraseña vacía.
- [x] 1.3 Implementar eliminación protegida por referencias y continuidad del último administrador bajo el bloqueo compartido; verificar en `UserManagementTest` cada tipo de referencia, usuario sin referencias, cambio de rol y eliminación del último administrador.
- [x] 1.4 Proteger cambios de rol de técnicos con asignaciones no cerradas y adaptar `AssignTicket` al mismo orden de bloqueo y recarga del actor; verificar asignaciones pendientes, referencias cerradas y las pruebas de regresión de seguimiento.
- [x] 1.5 Crear `UserResource`, sus páginas, formularios y tabla paginada con textos en español y contraseñas nunca hidratadas; verificar mediante `UserResourceTest` de Livewire CRUD autorizado, errores por campo, navegación y denegación de acceso directo para técnicos, solicitantes e invitados.
- [x] 1.6 Ampliar el soporte PostgreSQL de pruebas con carreras de eliminación/degradación de los últimos administradores y de asignación frente a cambio de rol; verificar con procesos independientes que permanece un administrador y que una incidencia no cerrada nunca queda asignada a un usuario sin rol técnico.
- [x] 1.7 Documentar en `docs/user-management.md` las credenciales, restricciones de borrado, reasignación previa y normalización de correos; verificar que las reglas documentadas coinciden con los casos funcionales de #4.

## 2. Catálogo de sedes y activos — issue #5

- [x] 2.1 Crear modelos, factories y migraciones de `Site` y `Asset`, con códigos únicos normalizados y sede obligatoria con borrado restrictivo; verificar relaciones, duplicados y migración/reversión en SQLite y PostgreSQL desechables.
- [x] 2.2 Implementar políticas y acciones del catálogo con consulta para los tres roles y mantenimiento administrativo, límites de datos y rechazo de eliminación referenciada; verificar en `SitesAndAssetsTest` peticiones directas, validación, códigos repetidos, traslado de sede y conservación de referencias.
- [x] 2.3 Crear `SiteResource` y `AssetResource` con listados paginados, fichas y formularios en español; verificar en `SitesAndAssetsResourceTest` consulta por todos los roles, mantenimiento solo administrativo y ausencia de datos de incidencias ajenas en relaciones inversas.
- [x] 2.4 Documentar en `docs/sites-and-assets.md` códigos, pertenencia, consulta compartida, restricciones de borrado y contexto actual tras un traslado; contrastar los ejemplos con las pruebas del catálogo.

## 3. Activos en las incidencias — issue #5

- [x] 3.1 Añadir la migración nullable e indexada `tickets.asset_id` con clave foránea restrictiva y las relaciones `Ticket::asset` y `Asset::tickets`; verificar que las incidencias anteriores conservan datos y seguimiento, y que un activo referenciado no puede borrarse en SQLite ni PostgreSQL.
- [x] 3.2 Extender `TicketData`, `CreateTicket` y `UpdateTicket` para persistir el activo opcional y rechazar referencias inválidas de forma atómica; ampliar `TicketManagementTest` con alta con/sin activo, clave omitida en edición, sustitución, retirada explícita y permisos por rol/estado.
- [x] 3.3 Incorporar selector con búsqueda por código y nombre y etiqueta de sede en `TicketForm`, y contexto o ausencia del activo en `TicketInfolist`, precargando `asset.site`; verificar en `TicketResourceTest` formularios, ficha, traslado del activo y denegación de acceso a incidencias ajenas.
- [x] 3.4 Cubrir en PostgreSQL la carrera entre eliminación de activo y guardado de su referencia; verificar que una operación se rechaza con mensaje comprensible y no quedan referencias inválidas ni guardados parciales.
- [x] 3.5 Ampliar `docs/sites-and-assets.md` con el alta sin activo, vinculación y corrección autorizada de incidencias y compatibilidad de registros antiguos; verificar el recorrido documentado con los tres roles.

## 4. Resumen del panel — issue #6

- [x] 4.1 Crear `TicketStatusOverview` usando la consulta `visibleTo`, agregación por los cuatro estados no cerrados y valores cero para ausencias; verificar en `TicketOverviewTest` todos los estados, datos propios/ajenos, roles del equipo y panel vacío.
- [x] 4.2 Integrar el widget en el panel con etiquetas españolas, orden estable y sin navegación desde los recuentos; verificar en pruebas Livewire y del dashboard su disponibilidad para los tres roles y denegación sin sesión.
- [x] 4.3 Verificar recálculo tras alta, transición y cierre al refrescar el componente o volver a cargar el panel, incluyendo cambio de sesión entre usuarios; comprobar que no se reutilizan recuentos de otra persona.
- [x] 4.4 Documentar en `docs/ticket-overview.md` estados incluidos, visibilidad, ceros y actualización por recarga; contrastar sus ejemplos con los recuentos de las pruebas.

## 5. Integración y revisión

- [x] 5.1 Ejecutar la suite PHPUnit completa y las pruebas de concurrencia PostgreSQL en entornos desechables, aplicar Pint al PHP modificado y ejecutar `openspec validate add-administration-and-ticket-overview --strict --no-interactive`; registrar resultados y resolver regresiones.
- [x] 5.2 Revisar en navegador con administrador, técnico y solicitante el recorrido catálogo → incidencia con activo → seguimiento → widget y las denegaciones de mantenimiento; guardar capturas y evidencia de los criterios de éxito de #4, #5 y #6 en `docs/administration-and-ticket-overview-verification.md`.
