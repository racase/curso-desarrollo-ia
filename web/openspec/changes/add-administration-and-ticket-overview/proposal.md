# Proposal

## Why

InfraTicket ya permite gestionar incidencias, pero depende de usuarios precargados, no identifica el contexto físico de los problemas y carece de un resumen del trabajo pendiente. Los issues [#4](https://github.com/racase/curso-desarrollo-ia/issues/4), [#5](https://github.com/racase/curso-desarrollo-ia/issues/5) y [#6](https://github.com/racase/curso-desarrollo-ia/issues/6) completan estas necesidades sobre la gestión ya implementada.

## What Changes

- Gestión de usuarios exclusiva de administradores: listado, alta, edición, eliminación y asignación de los tres roles existentes. Nombre, correo único, rol y contraseña serán obligatorios al crear; cambiar la contraseña será opcional al editar.
- Protección de usuarios relacionados con incidencias, comentarios o historial frente a eliminación, y del último administrador frente a eliminación o cambio de rol.
- Gestión de sedes y activos reservada a administradores; consulta del catálogo para los tres roles. Cada activo pertenecerá a una sede.
- Asociación opcional de una incidencia con un activo, mostrando activo y sede en su ficha. Se conservarán las incidencias existentes sin activo y se podrá registrar un problema sin catalogarlo previamente.
- Widget para los tres roles con recuentos de propuesta, abierta, en curso y resuelta; excluye cerrada, muestra ceros y respeta la visibilidad de cada usuario.
- Se excluyen roles personalizados, invitaciones, recuperación de acceso, edición del perfil propio como función independiente, inventario económico, mapas, mantenimiento preventivo, historial propio de activos y navegación o métricas adicionales en el widget.

## Capabilities

### New Capabilities

- `user-management`: mantenimiento administrativo de usuarios, validación de credenciales y protección de referencias y del último administrador.
- `sites-and-assets`: catálogo de sedes y activos con pertenencia obligatoria, permisos y conservación de referencias.
- `ticket-overview`: recuentos de incidencias no cerradas por estado y visibilidad del usuario.

### Modified Capabilities

- `ticket-management`: selección opcional de activo durante alta y edición autorizada, y consulta de su sede en la ficha.

## Impact

Se añadirán recursos, políticas y acciones administrativas, modelos y migraciones de sedes y activos, una referencia nullable en incidencias y un widget del panel. Se ampliarán las acciones y pantallas existentes de incidencias y las pruebas PHPUnit/Livewire. Se reutilizan los roles, el flujo de estados, las restricciones de eliminación y la visibilidad existentes; no se prevén nuevas dependencias ni cambios en `datos/`.
