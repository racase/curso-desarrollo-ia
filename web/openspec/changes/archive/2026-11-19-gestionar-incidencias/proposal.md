# Proposal

## Why

InfraTicket permite iniciar sesión, pero todavía no registrar ni atender problemas. Los issues 01, 02 y 03 de `docs/issues/` definen el primer flujo completo: comunicar una incidencia, asignar su atención, seguir su evolución y compartir aclaraciones.

## What Changes

- Alta para cualquier usuario autenticado en su propio nombre, con título, descripción y prioridad; estado inicial propuesta y prioridad media por defecto.
- Listado, ficha y filtros por estado y prioridad: solicitantes ven solo sus incidencias; técnicos y administradores ven todas.
- Asignación y reasignación por administradores a usuarios técnicos; autoasignación de incidencias libres por técnicos.
- Avance secuencial propuesta → abierta → en curso → resuelta → cerrada por administrador o técnico asignado, con técnico obligatorio al pasar a en curso y sin reapertura.
- Historial visible de cambios de estado con actor, fecha y estados anterior y nuevo.
- Comentarios en cualquier estado por usuarios con acceso, con autor y fecha, sin edición ni eliminación.
- Autorización en servidor y pruebas de aislamiento, transiciones y participación.

Las reglas anteriores se han confirmado con el usuario para resolver las preguntas abiertas del backlog. Quedan fuera gestión de usuarios (04), sedes y activos (05), widget (06), adjuntos, notificaciones, comentarios internos, reparto automático, historial de asignaciones y edición o eliminación de incidencias.

## Capabilities

### New Capabilities

- `incidencias`: alta, validación, consulta y filtrado con visibilidad por rol (issue 01).
- `seguimiento-incidencias`: asignación, transiciones válidas e historial de estados (issue 02).
- `comentarios-incidencias`: publicación y consulta ordenada de comentarios vinculados a incidencias accesibles (issue 03).

### Modified Capabilities

Ninguna: no existen especificaciones principales previas.

## Impact

Se añadirán modelos, enums, políticas, acciones de dominio, migraciones y factories en las carpetas existentes de Laravel, y un recurso con páginas de Filament en `app/Filament/`. Se ampliarán las relaciones de `User` y las pruebas PHPUnit/Livewire. Habrá nuevas tablas en PostgreSQL compatibles con SQLite para pruebas; se conservan los usuarios de demo y la autenticación. No se prevén paquetes nuevos ni API pública.
