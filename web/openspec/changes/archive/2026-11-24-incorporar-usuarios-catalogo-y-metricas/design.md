# Design

## Context

Ver `proposal.md` para la motivación y las decisiones de producto confirmadas. El proyecto utiliza Laravel 13, Filament 5 y PostgreSQL; las pruebas funcionales usan SQLite. `User` ya tiene un enum con tres roles y cast de contraseña a hash. `Incident::visibleTo()` restringe a solicitantes a sus propios registros. Las acciones de alta, asignación y transición concentran validación y autorización fuera de las páginas.

No hay recursos de usuarios, modelos de catálogo ni widget de incidencias. El dashboard registra `AccountWidget`. Las cuatro referencias de incidencias, comentarios e historial hacia usuarios tienen borrado restrictivo. Las pruebas existentes de alta y sus factories no proporcionan activo. `IncidentPolicy::update()` deniega edición general y debe seguir haciéndolo.

## Goals / Non-Goals

**Goals:** reutilizar las reglas de visibilidad y el patrón de acciones; mantener integridad bajo concurrencia; migrar sin alterar incidencias anteriores; disponer de datos de catálogo estables; verificar permisos tanto en servidor como en componentes del panel.

**Non-Goals:** introducir otro sistema de permisos, edición general de incidencias, CRUD de inventario, auditoría de cambios de activo o infraestructura de tiempo real. No se modifica el cambio preexistente `gestionar-usuarios-sedes-y-resumen`.

## Decisions

### 1. Recurso de usuarios con política y acciones compartidas

Añadir un recurso Filament bajo `app/Filament/Resources/Users/` con listado, creación y edición; borrado individual confirmado y sin borrado masivo en esta entrega. Un `UserPolicy` restringirá las operaciones al rol administrador. Acciones bajo `app/Actions/Users/` realizarán validación, autorización y persistencia, incluidas las defensas que no deben depender de ocultar botones.

Nombre y email se recortan, el email se normaliza a minúsculas, y se valida unicidad excluyendo la propia cuenta al editar. Se exige contraseña confirmada de al menos 12 caracteres al crear y se ignora el campo vacío al editar. Se reutiliza el cast de hash y nunca se precarga el hash en formularios. El rol se valida contra `UserRole`; ninguna entrada permite crear roles nuevos. Alternativa descartada: depender exclusivamente de validación o visibilidad en el recurso, porque las operaciones directas necesitan las mismas defensas.

### 2. Preservar usuarios referenciados y continuidad de roles

Mantener las cuatro claves foráneas restrictivas y detectar referencias antes de borrar para devolver errores comprensibles; traducir también el conflicto de integridad si aparece una referencia concurrente. No usar borrado en cascada ni eliminación lógica, que cambiarían el acceso y la consulta de autores.

Serializar las acciones de gestión de usuarios con un bloqueo transaccional común: seleccionar y bloquear las cuentas administradoras en orden de id y releer actor, destino y número de administradores antes de persistir. Todas las acciones administrativas de usuarios seguirán este orden, con reintento acotado ante deadlock; comprobar su comportamiento real en PostgreSQL. La autorización se reevalúa con el rol actualizado. Impedir eliminación propia y retirada del último administrador. Se permite que un administrador cambie su propio rol si queda otro administrador; su siguiente petición pierde los permisos anteriores.

Un técnico con asignaciones conserva su rol hasta que el administrador reasigne esas incidencias, incluso cerradas. Así no quedan responsables sin rol técnico. Coordinar la lectura bloqueada de la cuenta destino con `AssignIncident`, que ya bloquea al técnico, y cubrir la carrera asignación/cambio de rol. No se desasignan ni reasignan casos implícitamente. Alternativa descartada: contar administradores sin bloqueo, pues dos operaciones simultáneas podrían superar la protección.

### 3. Catálogo fijo con identidad estable

Añadir modelos `Site` y `Asset`, tablas `sites` y `assets`, códigos únicos, nombres de hasta 255 caracteres y una clave obligatoria `assets.site_id` con borrado restrictivo. Sembrar S01–S06 (Sede 01–06) y, para cada una, cinco activos con códigos S01-A01…S06-A05 y nombres Activo 01…05. Estos nombres neutrales son una decisión de demo, no ubicaciones reales.

Un seeder específico utilizará los códigos como claves estables y se invocará desde `DatabaseSeeder`. Repetirlo no eliminará registros ni cambiará ids. Comprobará cardinalidad y distribución; un catálogo inesperado provocará un diagnóstico y rollback, sin borrar datos para forzar el total. La instalación soportada parte de las tablas nuevas vacías o de este catálogo ya cargado. No añadir recursos de mantenimiento ni endpoints de escritura. Alternativa descartada: tratar 6/30 como simples datos iniciales de un inventario extensible; el usuario confirmó que son el catálogo fijo.

### 4. Compatibilidad mediante vínculo nullable y validación de negocio

Añadir una migración expansiva con `incidents.asset_id` nullable, indexado y con clave foránea restrictiva. Las incidencias anteriores permanecerán en null. La base permite ese legado; la acción `CreateIncident` exige siempre un activo existente para las altas nuevas. No introducir un activo ficticio, no seleccionar automáticamente el primero y no ejecutar `migrate:fresh`.

Añadir `Incident::asset()`, `Asset::site()` y relaciones inversas. No persistir `site_id` en incidencias: derivarlo del activo evita contradicciones. El alta tendrá un selector buscable con etiquetas de código, nombre y sede; con solo 30 opciones basta agrupar por sede. Listado y ficha cargarán `asset.site` junto a las relaciones actuales para evitar consultas por fila; mostrarán «Sin activo» y «Sin sede» en el legado.

Añadir una habilidad específica de política y una acción `LinkIncidentAsset`, accesible únicamente a administradores desde la ficha. Validará el activo y actualizará únicamente `asset_id` sobre el registro vigente, sin guardar un snapshot de otros campos ni crear transiciones. Así convive con asignaciones y cambios de estado concurrentes. Técnicos y solicitantes no pueden corregir el vínculo. Alternativa descartada: habilitar `update` general, que ampliaría permisos fuera del alcance.

Las factories crearán por defecto relaciones válidas para pruebas ordinarias sin depender del catálogo demo; un estado explícito representará incidencias anteriores sin activo. Las pruebas del catálogo usarán su seeder y verificarán 6/30 y repetición. Actualizar las pruebas de creación para enviar un activo válido y conservar sus aserciones de identidad, prioridades y permisos.

### 5. Widget con consulta común de visibilidad

Añadir `IncidentStatusOverview` bajo `app/Filament/Widgets/`, registrado una sola vez en el dashboard mediante el mecanismo existente. Reutilizar `Incident::visibleTo($user)`, excluir `IncidentStatus::Closed` y agrupar en una sola consulta por estado. Completar los estados ausentes con cero en el orden del enum. No consultar comentarios ni historial, ni reutilizar filtros del listado.

No utilizar caché compartida entre usuarios. Calcular al cargar y configurar una actualización de 30 segundos mientras esté visible; recargar también obtiene cifras nuevas. No es una garantía de actualización instantánea. El widget conserva `AccountWidget`, no añade enlaces ni acciones y explica que incluye todos los estados salvo Cerrada. Alternativas descartadas: una consulta por cifra, contar únicamente el estado Abierta o introducir websockets.

### 6. Continuidad visual y accesibilidad

Aplicar `DESIGN.md` y `identidad-visual-panel` a los nuevos formularios, confirmaciones y recuentos: superficies claras, texto violeta oscuro, Rubik, títulos de 24–30px y botón principal `#150f23` con texto blanco. Reservar el lima, sin añadir colores de estado. Mostrar etiquetas, errores y mensajes además del color, foco visible, manejo por teclado y retorno de foco al cerrar confirmaciones. Apilar las cifras y formularios en móvil, manteniendo controles de 44px, lectura a 320px y ampliación al 200%. No se requiere rediseñar el tema ni alterar la especificación visual vigente.

## Risks / Trade-offs

- [El campo nullable permite legados sin contexto físico] → Señalización explícita y vinculación administrativa; las altas nuevas lo exigen en servidor.
- [El cambio del contrato de alta rompe pruebas o consumidores internos] → Actualizar todas las llamadas a `CreateIncident`, formularios y factories en el mismo cambio; mantener la restricción documentada como breaking.
- [SQLite no reproduce bloqueos de PostgreSQL] → Prueba concurrente adicional en una base PostgreSQL temporal siguiendo `tests/Support/check-postgres-concurrency.php`, sin migrar ni borrar la base de la demo.
- [Las reglas de rol pueden competir con asignaciones] → Bloqueo de la cuenta técnica y regresión concurrente, conservando el rechazo de asignaciones a usuarios sin rol técnico.
- [El catálogo fijo limita la demo] → Mantener exactamente el alcance confirmado; cualquier ampliación exige otro cambio.
- [Recuentos con hasta 30 segundos de retraso] → Explicar la frecuencia y verificar carga, recarga y actualización periódica sin prometer tiempo real.

## Migration Plan

1. Ejecutar las migraciones nuevas de catálogo y vínculo nullable, conservando todos los registros anteriores.
2. Ejecutar el seeder de catálogo y verificar 6 sedes, 5 activos por sede y claves estables. Incorporarlo al arranque actual que ya migra y siembra; antes de aceptar nuevas altas comprobar que el catálogo está disponible.
3. Desplegar acciones, recursos y widget como una entrega coordinada. Comprobar una incidencia anterior sin activo y una nueva con activo, además de permisos y recuentos de cada rol.
4. Para rollback operativo, volver al código anterior conservando tablas y columna nuevas: los datos previos siguen siendo compatibles. No revertir las migraciones con vínculos ya usados sin exportar previamente esos datos; un rollback estructural elimina el contexto añadido.

## Validation

Ejecutar pruebas Feature de gestión de usuarios, catálogo, altas y corrección de activo, y recuentos para los tres roles, con `RefreshDatabase`. Añadir verificaciones concurrentes de último administrador y rol técnico frente a asignación en PostgreSQL aislado. Ejecutar después la suite completa mediante `docker compose exec app php artisan test` y Pint para los archivos PHP modificados. Revisar visualmente usuarios, alta, ficha y dashboard a 320px y escritorio, con teclado y ampliación; guardar capturas para cambios visibles. La propuesta en sí se valida con OpenSpec, sin ejecutar pruebas de código que todavía no se ha implementado.
