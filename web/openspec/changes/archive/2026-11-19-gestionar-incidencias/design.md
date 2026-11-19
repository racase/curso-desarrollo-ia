# Design

## Context

Ver `proposal.md` para la motivación y `specs/` para el contrato observable. Se han leído `User`, `UserRole`, `AdminPanelProvider`, `DatabaseSeeder`, `AdminLoginTest` y `composer.json`. Existen autenticación y tres roles, pero no modelos de negocio, políticas ni recursos. El panel ya descubre recursos en `app/Filament/Resources`. Laravel 13 y Filament 5 están declarados; PHP y Composer se ejecutan dentro de Docker. PHPUnit 12 usa SQLite en memoria, mientras la aplicación usa PostgreSQL.

## Goals / Non-Goals

**Goals:** mantener las reglas de negocio y autorización fuera de las páginas; guardar las transiciones con integridad; reutilizar roles y descubrimiento existentes; permitir comprobar aislamiento y acciones manipuladas con pruebas automatizadas.

**Non-Goals:** API pública, paquetes de permisos, motor de workflows, eventos externos y edición genérica que pueda saltarse las acciones de dominio. No se modifica código durante esta propuesta.

## Decisions

### 1. Persistencia explícita y portable

Crear `Incident` con título (255), descripción (texto), estado y prioridad como cadenas con casts a enums `IncidentStatus` y `IncidentPriority`, solicitante obligatorio, técnico nullable y timestamps. Valores internos: `proposed`, `open`, `in_progress`, `resolved`, `closed`; prioridades `low`, `medium`, `high`, `critical`, con etiquetas españolas. Preferir cadenas a enums nativos de PostgreSQL para usar también SQLite.

Crear `IncidentStatusHistory` con incidencia, actor, estado anterior, estado nuevo y fecha; `IncidentComment` con incidencia, autor, contenido y timestamps. Usar claves foráneas e índices por solicitante, técnico, estado/prioridad e incidencia/fecha/id. Restringir eliminación de usuarios referenciados para conservar atribución; las tablas hijas pueden usar cascada al borrar la incidencia, aunque no se expone borrado en el producto. El issue 04 deberá reconciliar esta restricción antes de implementar eliminación de usuarios.

Relacionar `User` con incidencias creadas, asignadas, comentarios y transiciones. Añadir factories para preparar cada rol y estado en pruebas. No añadir datos obligatorios al seeder existente. Un JSON de historial dentro de la incidencia se descarta porque dificulta integridad y consultas ordenadas.

### 2. Autorización compartida y consulta acotada

Una `IncidentPolicy` resolverá consulta, alta, asignación y transición; una `IncidentCommentPolicy` resolverá lectura y creación delegando acceso a la incidencia y denegará modificación y borrado. Centralizar la consulta visible por usuario y aplicarla al recurso y a cualquier carga de ficha. Los solicitantes reciben solo sus registros; no se basta con ocultar botones. Denegar edición y borrado de incidencias y no generar páginas para esas operaciones.

Las acciones de dominio revalidarán permisos y datos; el formulario no será la frontera de seguridad. Solicitante, estado inicial, autor y fechas se fijan en servidor mediante datos explícitamente permitidos. Los estados y prioridades desconocidos se rechazan. Recortar espacios y validar límites definidos en las specs; los límites son decisiones de esta propuesta. La salida de título, descripción y comentarios se escapa como texto plano. Evitar un paquete RBAC adicional: `UserRole` ya representa los permisos necesarios.

### 3. Acciones transaccionales para cambios

Crear acciones específicas de alta, asignación/autoasignación, transición y comentario. Para asignar o transicionar, abrir transacción, recargar y bloquear la incidencia, revalidar actor, responsable y estado actual, y persistir. El destino de asignación debe seguir existiendo con rol técnico al ejecutar. Un administrador puede reasignar en cualquier estado; no hay desasignación manual. La autoasignación se limita a incidencias libres.

La transición recibe el estado de origen esperado por la pantalla y compara con el actual para detectar peticiones obsoletas. El único mapa permitido es `proposed → open → in_progress → resolved → closed`; el alta fija `proposed`. El paso de propuesta a abierta no exige técnico y lo autoriza un administrador o el técnico asignado; el paso de abierta a en curso sí requiere técnico asignado. La actualización y la entrada de historial comparten transacción: cualquier error revierte ambos. Rechazar repeticiones sin añadir historial. La creación no se registra como transición; las asignaciones tampoco. Frente a guardar desde un formulario genérico u observar cualquier actualización del modelo, estas acciones dejan explícitos actor y precondiciones y evitan duplicar entradas.

### 4. Recurso único en Filament

Crear `IncidentResource` y páginas de listado, alta y ficha. Alta con título, descripción, estado inicial propuesta fijado en servidor y selector de prioridad media por defecto; listado con columnas y filtros combinables; ficha con datos completos, responsable y acciones de asignación y siguiente estado. La interfaz muestra únicamente acciones autorizadas y comunica validación o conflicto solicitando refrescar datos cuando corresponda.

Incluir en la ficha historial de solo lectura y una sección de comentarios con formulario de publicación. Cargar relaciones de autores y técnicos evitando consultas por cada fila. Ordenar historial y comentarios por fecha ascendente e id ascendente, con paginación para conversaciones extensas que conserve el orden. No ofrecer edición/borrado ni permitir ejecutar esas acciones con peticiones manuales. Usar componentes estándar de Filament; no hacen falta nuevos assets ni vistas con JavaScript propio.

### 5. Validación por comportamiento

Feature tests con `RefreshDatabase` y Livewire siguiendo `AdminLoginTest`: creación y valores protegidos por rol; límites; consulta directa y filtros sin fugas; asignaciones válidas/ilegales; matriz completa de transiciones y permisos; comentarios en los cinco estados, orden, texto escapado e inmutabilidad. Comprobar rollback del estado si falla el historial y rechazo de estado de origen obsoleto. Mantener tests de login existentes.

SQLite no demuestra bloqueos de filas de PostgreSQL. Añadir un chequeo de concurrencia reproducible con dos conexiones contra una base PostgreSQL exclusiva de pruebas: dos autoasignaciones deben dejar un ganador y una transición repetida una única entrada. No usar la base de demo para esta prueba. Completar revisión manual del panel con los tres roles y capturas para una futura PR.

## Risks / Trade-offs

- [Solicitantes acceden a registros ajenos mediante URL o payload] → aplicar consulta acotada, políticas y revalidación en acciones; probar accesos directos y relaciones.
- [Carreras de asignación y estados] → transacciones con bloqueo y precondiciones; comprobar concurrencia en PostgreSQL, además de pruebas SQLite.
- [Historial incompleto] → guardar transición e historial de forma atómica y probar reversión ante fallos.
- [Restricción de borrado de usuarios] → mantener autoría y documentar la decisión para el issue 04.
- [Crecimiento de conversaciones] → paginación estable e índices sin introducir un subsistema nuevo.
- [Sin reapertura ni correcciones posteriores] → comportamiento confirmado para esta primera versión; cualquier ampliación necesitará otro cambio.

## Migration Plan

1. Crear migraciones aditivas de incidencias y después de historial/comentarios, sin alterar ni borrar datos existentes.
2. Ejecutar migraciones y suite en el entorno de pruebas SQLite; validar esquema y concurrencia en PostgreSQL aislado.
3. Aplicar con `docker compose exec app php artisan migrate` y revisar panel con usuarios de demo. No ejecutar `migrate:fresh`.
4. Ante un problema de despliegue, retirar el código del recurso manteniendo tablas y datos. Un rollback de migraciones solo se hará sin datos útiles o con copia previa, en orden inverso de dependencias.
