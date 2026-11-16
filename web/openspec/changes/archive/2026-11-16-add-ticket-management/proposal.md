# Proposal

## Why

InfraTicket permite iniciar sesión, pero todavía no registrar ni atender incidencias. Los issues 01, 02 y 03 proporcionan un recorrido completo para comunicar un problema, asignar su atención, seguir su evolución y conservar la conversación.

## What Changes

- Incorporar alta, listado y ficha de incidencias con título, descripción, prioridad, estado y solicitante; filtros por estado y prioridad.
- Limitar al solicitante a sus incidencias y permitir a técnicos y administradores consultar todas.
- Crear las incidencias a nombre del usuario conectado, en estado propuesta, con prioridad media por defecto y sin técnico asignado.
- Permitir a técnicos y administradores asignar o reasignar a un técnico. El técnico asignado y los administradores podrán avanzar el estado por el flujo propuesta → abierta → en curso → resuelta → cerrada, sin saltos ni reapertura.
- Conservar un historial de estados con autor y fecha, visible en la ficha.
- Incorporar comentarios con autor y fecha, sin edición ni eliminación. Las incidencias cerradas quedarán en consulta.
- Las reglas anteriores fueron confirmadas para completar esta propuesta. El diseño concreta los límites de contenido y las operaciones restantes.
- Quedan fuera los issues 04–06, adjuntos, notificaciones, comentarios privados, eliminación de incidencias, asignación automática e historial de reasignaciones.

## Capabilities

### New Capabilities

- `ticket-management`: alta, consulta, filtros, edición básica y visibilidad de incidencias; corresponde al issue 01.
- `ticket-tracking`: asignación a técnicos, transiciones e historial; corresponde al issue 02 y depende de `ticket-management`.
- `ticket-comments`: publicación y consulta de comentarios; corresponde al issue 03 y depende de `ticket-management`.

### Modified Capabilities

Ninguna. No existen especificaciones principales previas.

## Impact

Se añadirán persistencia de incidencias, historial y comentarios, relaciones con los usuarios existentes, autorización por rol y pantallas en el panel Filament. Afectará a `app/`, `database/` y `tests/`, manteniendo Laravel 13, Filament 5 y PostgreSQL; las pruebas habituales seguirán utilizando SQLite en memoria.

No se prevén nuevas dependencias ni una API pública. El inicio de sesión y los roles actuales se conservan. Las migraciones serán aditivas y las pruebas existentes de acceso al panel deberán seguir pasando.
