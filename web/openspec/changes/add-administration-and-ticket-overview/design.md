# Design

## Context

La motivación y el alcance se describen en `proposal.md`, con origen en los issues #4, #5 y #6. Las decisiones de producto consultadas quedaron confirmadas: mantenimiento administrativo, protección de referencias y último administrador, sede obligatoria por activo, activo opcional por incidencia y cuatro estados no cerrados visibles para los tres roles.

La aplicación tiene Laravel 13 y Filament 5. `User` ya convierte `role` mediante `UserRole` y aplica el cast `hashed` a la contraseña. Todos los roles acceden al panel; la autorización debe residir en cada recurso y operación. Las migraciones de incidencias, comentarios e historial ya restringen la eliminación de usuarios referenciados.

`Ticket::scopeVisibleTo` limita solicitantes a sus incidencias. `TicketResource` reutiliza ese scope y carga solicitante y técnico. `CreateTicket`, `UpdateTicket` y `TicketData` concentran autorización y validación; no existe catálogo ni referencia a activos. `AssignTicket` valida que el destinatario sea técnico. El panel descubre recursos y widgets y actualmente solo registra el widget de cuenta. Las pruebas existentes usan PHPUnit, Livewire y SQLite, con infraestructura adicional para concurrencia real en PostgreSQL.

## Goals / Non-Goals

**Goals:**

- Mantener la autorización y las invariantes en operaciones de aplicación, además de ocultar acciones en la interfaz.
- Incorporar referencias de catálogo sin alterar el flujo ni la trazabilidad de las incidencias existentes.
- Calcular el widget desde la misma consulta de visibilidad que el listado, sin cargar todas las incidencias.
- Comprobar concurrencia en PostgreSQL y comportamiento funcional en SQLite.

**Non-Goals:**

- Crear un sistema nuevo de permisos, caché compartida de estadísticas o transporte de eventos en tiempo real.
- Conservar instantáneas históricas de nombres y ubicaciones del catálogo.
- Cambiar el seguimiento de estados, la autoría o las reglas de comentarios.

## Decisions

### 1. Recursos administrativos y acciones de aplicación

Crear `UserResource`, `SiteResource` y `AssetResource` siguiendo la separación existente entre recurso, páginas, esquemas y tablas. Las políticas permitirán mantenimiento solo a administradores; las de catálogo permitirán consulta a cualquier usuario autenticado. Los usuarios no administrativos no verán navegación de gestión de usuarios, pero la prohibición también se comprobará en servidor. Evitar edición rápida o eliminación masiva hasta que compartan las mismas garantías que las operaciones individuales.

Las acciones de usuarios centralizarán validación, normalización y autorización. La creación exigirá nombre, correo, rol y contraseña; la edición omitirá una contraseña vacía de los datos persistidos. Reutilizar el cast de contraseña existente, sin hidratar contraseñas en los formularios. Límites propuestos como detalle de esta planificación: nombre hasta 255, correo hasta 255 y contraseña mínima de 12 caracteres. Correo en minúsculas y sin espacios exteriores. Antes de introducir esa normalización, comprobar en una consulta de solo lectura si los correos existentes colisionan al normalizar; detener la migración con diagnóstico si ocurre, sin corregir usuarios automáticamente.

Alternativa descartada: validaciones únicamente en formularios, porque las peticiones directas y las operaciones concurrentes las eludirían. No se añade registro público ni flujo de invitación.

### 2. Protección transaccional de usuarios y asignaciones

Las claves foráneas restrictivas existentes seguirán siendo la última protección de referencias. Las acciones comprobarán las referencias antes de eliminar y traducirán conflictos concurrentes de integridad a mensajes comprensibles.

Para serializar operaciones que puedan eliminar al último administrador o invalidar el rol de un técnico asignado, añadir una tabla interna `administration_locks` con una fila estable `users`. Las acciones de alta, modificación y eliminación de usuarios y `AssignTicket` bloquearán esa fila al inicio de su transacción, antes de cargar y bloquear los registros de negocio. Dentro del bloqueo se recargarán el actor y sus permisos, el usuario afectado y el conjunto actual de administradores. Solo se permitirá eliminación o degradación si permanece otro administrador. El guard se introduce mediante migración, sin depender del seeder de demostración.

Cambiar el rol de un técnico con incidencias no cerradas se rechazará hasta que se reasignen o cierren; las asignaciones cerradas permanecen como referencias históricas. Este detalle resuelve la interacción con `ticket-tracking` sin cambiar sus transiciones. El mismo guard en `AssignTicket` impide asignar mientras otra operación cambia el rol del destinatario. Las transiciones mantienen sus bloqueos actuales y no necesitan el guard: cerrar una incidencia solo reduce las asignaciones que bloquean un cambio de rol.

Alternativas: un simple recuento de administradores permite carreras; bloquear únicamente al usuario editado no protege operaciones sobre administradores distintos. Se acepta serializar estas operaciones poco frecuentes para disponer de un orden de bloqueo estable y comprobable. No bloquear el catálogo ni las consultas con este guard.

### 3. Catálogo relacional y referencias restrictivas

Añadir modelos `Site` y `Asset` y tablas `sites` y `assets`: identificador, código, nombre y timestamps; `assets.site_id` obligatorio. Código único por tabla, normalizado a mayúsculas y recortado, hasta 50 caracteres; nombre recortado hasta 200. Se admite repetir nombres porque el código identifica el registro.

Añadir `tickets.asset_id` nullable, con índice y clave foránea restrictiva. La sede se obtiene a través de `asset.site`, sin duplicar `site_id` en incidencias. Sedes con activos y activos con incidencias no podrán eliminarse. Las restricciones de base de datos resolverán también las carreras entre eliminación y nuevas referencias; los errores se presentarán en español sin exponer SQL.

Alternativa descartada: borrar en cascada o vaciar referencias automáticamente, porque perdería contexto. Tampoco se exige activo a registros existentes ni se crea un activo ficticio. Editar la sede de un activo actualizará el contexto mostrado por todas sus incidencias; no se promete una ubicación histórica.

### 4. Integración del activo con incidencias

Extender `TicketData` y las acciones de creación y edición para validar y persistir `asset_id`, preservando el resto de atributos protegidos. En edición, distinguir entre clave omitida (conservar activo actual) y valor explícitamente vacío (retirarlo). La selección será opcional, con búsqueda y etiquetas de código, nombre y sede; no se ofrecerá creación del catálogo dentro del formulario de incidencias a roles sin permiso.

`TicketPolicy` conserva los permisos actuales: solicitantes editan sus propuestas y abiertas; el equipo edita incidencias no cerradas. `TicketInfolist` mostrará activo y sede o «Sin activo asociado». Precargar `asset.site` al consultar las fichas para evitar consultas repetidas. Si el activo desaparece entre selección y guardado, rechazar la operación sin guardar cambios parciales.

Se modifica la especificación existente `ticket-management` para ampliar edición y ficha y añadir la asociación opcional; se conservan sus escenarios anteriores. No se introduce una capacidad paralela de alta de incidencias.

### 5. Agregación del widget

Crear `TicketStatusOverview` en `app/Filament/Widgets`, descubierto por el panel. Sobre `Ticket::visibleTo($user)`, restringir a `Proposed`, `Open`, `InProgress` y `Resolved` y agregar por estado en la base de datos. Completar con cero los estados ausentes, con las etiquetas del enum y orden estable. No compartir caché entre usuarios.

Mostrar cuatro cifras sin enlaces ni acciones. Se recalcularán al abrir o recargar el panel y cuando se refresque el componente; no se exige polling ni actualización entre pestañas. Alternativa descartada: cargar todos los modelos o calcular totales globales antes de filtrar, por consumo y riesgo de revelar información.

## Risks / Trade-offs

- Serialización de mantenimiento y asignación → mantener transacciones cortas y el mismo orden de bloqueo; verificar carreras en PostgreSQL desechable usando la infraestructura existente.
- Administrador que modifica su propio rol → permitirlo solo si queda otro administrador y aplicar sus nuevos permisos en la siguiente petición, sin crear una función independiente de perfil.
- Correos heredados con mayúsculas o duplicados normalizados → comprobación previa y migración transaccional; no fusionar cuentas ni cambiar identificadores.
- Catálogo compartido visible a todos → solo expone código, nombre y sede; no debe ofrecer relaciones inversas que revelen incidencias ajenas.
- Referencias que aparecen entre validación y borrado → claves foráneas restrictivas y mensajes de conflicto; sin cascadas.
- Traslado de activo que cambia la sede visible de incidencias antiguas → comportamiento explícito en la especificación; una ubicación histórica requeriría otro cambio.
- SQLite no acredita bloqueos de fila → usarlo para reglas y formularios, y PostgreSQL para carreras de administradores y asignaciones.

## Migration Plan

1. Comprobar colisiones de correos normalizados en una base desechable representativa y, antes del despliegue, en el entorno destino mediante lectura autorizada. Si existen, detenerse y solicitar su resolución concreta.
2. Añadir migraciones incrementales para normalizar correos sin colisiones, crear y sembrar la fila de bloqueo, crear sedes y activos y añadir `tickets.asset_id` nullable con índice y restricción. No recrear tablas existentes ni ejecutar `migrate:fresh` sobre datos locales.
3. Probar migración y reversión en SQLite y PostgreSQL desechables, incluyendo incidencias previas sin activo y restricciones de referencias.
4. Desplegar migraciones antes del código que consulta los nuevos campos. El catálogo puede comenzar vacío; sus datos de demostración, si se añaden, serán explícitos y exclusivos de desarrollo.
5. En una reversión, detener escrituras y retirar primero el código dependiente del catálogo; exportar asociaciones y catálogo antes de eliminar su esquema. La reversión de esquema pierde ese contexto añadido y no se ejecutará automáticamente sobre datos útiles. La normalización de correos no recuperará sus mayúsculas originales; no cambia su identidad de acceso.

## Validation Strategy

Pruebas funcionales y Livewire de usuarios, permisos por los tres roles, validaciones, contraseña opcional, eliminación con cada tipo de referencia y último administrador. Pruebas de sedes y activos con acceso directo, códigos normalizados, referencias inexistentes, borrados protegidos y cambio de sede. Ampliación de pruebas de incidencias para alta sin y con activo, selección inválida, edición y retirada autorizadas y compatibilidad de registros antiguos. Pruebas del widget por rol y por estado, ceros y recálculo tras transición y cierre.

Ampliar el soporte PostgreSQL existente con carreras entre dos operaciones sobre los últimos administradores y entre cambio de rol y asignación. Ejecutar la suite existente para detectar regresiones de seguimiento, comentarios y acceso. Aplicar Pint al PHP modificado, validar OpenSpec estrictamente y revisar el panel con los tres roles, incluyendo capturas para una futura PR.
