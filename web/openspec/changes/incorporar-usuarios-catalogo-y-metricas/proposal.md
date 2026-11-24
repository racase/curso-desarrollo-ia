# Proposal

## Why

InfraTicket ya permite registrar y atender incidencias, pero carece de gestión administrativa de usuarios, contexto físico del problema y un resumen del trabajo abierto. Los issues [4](https://github.com/racase/curso-desarrollo-ia/issues/4), [5](https://github.com/racase/curso-desarrollo-ia/issues/5) y [6](https://github.com/racase/curso-desarrollo-ia/issues/6) cubren esas necesidades.

## Changes

- Incorporar listado, alta, edición, eliminación y asignación de los tres roles existentes, exclusivamente para administradores. Exigir nombre, email único y contraseña inicial; proteger la eliminación propia, la continuidad de al menos un administrador y las referencias históricas.
- Incorporar un catálogo fijo de exactamente 6 sedes y 5 activos por sede, identificados sin ambigüedad y precargados de forma repetible. Por decisión del usuario, prevalece el alcance local de `docs/issues/05-sedes-y-activos.md` sobre la gestión del catálogo planteada en GitHub: no habrá CRUD de sedes ni activos.
- **BREAKING**: exigir un activo válido en toda nueva incidencia. Mantener las anteriores como «Sin activo» hasta su vinculación administrativa, sin inventar relaciones ni reiniciar datos.
- Mostrar sede y activo en listado y ficha conservando la visibilidad actual. Permitir al administrador vincular o corregir el activo mediante una acción específica.
- Añadir al dashboard un widget «Incidencias no cerradas» con Propuesta, Abierta, En curso y Resuelta, incluidos ceros, respetando el alcance de cada rol. Actualizarlo al cargar y cada 30 segundos mientras esté visible.
- Mantener la identidad, accesibilidad y adaptación móvil de `DESIGN.md` y de la especificación visual vigente.

Quedan fuera el registro público, invitaciones, recuperación de acceso, permisos configurables, inventario editable, mapas, métricas históricas, exportaciones y navegación desde el widget.

## Capabilities

### New Capabilities

- `gestion-usuarios`: mantenimiento administrativo de cuentas, credenciales y roles con protecciones de integridad.
- `sedes-activos`: catálogo fijo, pertenencia de activos a sedes y vinculación administrativa de incidencias.
- `resumen-incidencias`: recuentos por estado no cerrado según la visibilidad del usuario.

### Modified Capabilities

- `incidencias`: activo obligatorio en nuevas altas, validación de su existencia y consulta de sede y activo, con compatibilidad para registros anteriores.

## Impact

Se modificarán `User`, `Incident`, `CreateIncident`, el recurso de incidencias y la configuración del dashboard; se añadirán políticas, acciones, recursos de usuarios, modelos de catálogo, migraciones, seeders, factories y pruebas. Se conservarán las restricciones de borrado de las claves foráneas de usuarios, los cinco estados y las reglas de acceso existentes. No se prevén nuevas dependencias ni APIs públicas.

Este es un cambio nuevo e independiente de `gestionar-usuarios-sedes-y-resumen`. Los issues figuran cerrados en GitHub, pero las capacidades descritas no están presentes en el código inspeccionado; esta propuesta no modifica su estado.
