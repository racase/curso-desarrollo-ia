# gestion-usuarios Specification

## Purpose

Permitir a administradores mantener cuentas y roles desde el panel, conservando el acceso administrativo y las referencias de las incidencias.

## Requirements

### Requirement: Gestión exclusiva de administradores
El sistema SHALL permitir únicamente a administradores listar, crear, editar y eliminar usuarios y asignar los roles administrador, técnico o solicitante. SHALL aplicar la autorización tanto en navegación como en accesos directos y operaciones enviadas al servidor.

#### Scenario: Administración disponible
- **WHEN** un administrador abre la gestión de usuarios
- **THEN** ve nombre, email y rol de las cuentas y puede iniciar las operaciones de mantenimiento permitidas

#### Scenario: Acceso denegado a otros roles
- **WHEN** un técnico o solicitante intenta abrir la gestión o enviar una operación directamente
- **THEN** el sistema deniega el acceso sin revelar cuentas ni modificar datos

#### Scenario: Acceso sin sesión
- **WHEN** una persona sin autenticar accede a la gestión
- **THEN** se requiere iniciar sesión antes de mostrar datos o aceptar operaciones

### Requirement: Datos y credenciales de cuenta
El sistema SHALL exigir nombre no vacío de hasta 255 caracteres, email válido único de hasta 255 caracteres y uno de los tres roles. SHALL exigir una contraseña confirmada de al menos 12 caracteres al crear; al editar, una contraseña vacía SHALL conservar la actual y una nueva SHALL cumplir las mismas reglas. SHALL almacenar contraseñas mediante hash y no mostrarlas ni precargarlas.

#### Scenario: Alta válida
- **WHEN** un administrador proporciona nombre, email disponible, rol y contraseña confirmada válidos
- **THEN** se crea la cuenta con ese rol y puede iniciar sesión con sus credenciales

#### Scenario: Validación de cuenta
- **WHEN** se envía nombre vacío o de espacios, email duplicado o inválido, un rol desconocido o una contraseña insuficiente o sin confirmación coincidente
- **THEN** aparecen errores asociados a los campos y no se guarda parcialmente la operación

#### Scenario: Edición sin cambio de contraseña
- **WHEN** un administrador cambia nombre, email o rol válidos dejando la contraseña vacía
- **THEN** los cambios se guardan y la contraseña anterior sigue funcionando

#### Scenario: Sustitución de contraseña
- **WHEN** un administrador guarda una nueva contraseña confirmada válida
- **THEN** la nueva permite acceder, la anterior deja de hacerlo y ninguna se muestra en el listado ni en la edición

### Requirement: Eliminación con integridad
El sistema SHALL solicitar confirmación para eliminar una cuenta. SHALL impedir eliminar la propia cuenta o cualquier usuario referenciado como solicitante, técnico, autor de comentario o actor del historial. SHALL explicar el bloqueo sin borrar ni modificar información relacionada.

#### Scenario: Cuenta sin referencias
- **WHEN** un administrador confirma eliminar otra cuenta sin referencias y la operación conserva un administrador
- **THEN** la cuenta desaparece del listado y ya no permite iniciar sesión

#### Scenario: Eliminación propia
- **WHEN** un administrador intenta eliminar su cuenta
- **THEN** se rechaza la operación incluso mediante petición directa

#### Scenario: Referencias protegidas
- **WHEN** se intenta eliminar una cuenta vinculada a una incidencia, asignación, comentario o transición
- **THEN** se conserva la cuenta y toda la información asociada y se indica el motivo

### Requirement: Continuidad administrativa y de asignación
El sistema SHALL impedir eliminar o cambiar el rol del último administrador, incluso ante operaciones simultáneas. SHALL impedir cambiar a otro rol un técnico que siga asignado a incidencias; SHALL indicar que es necesario reasignarlas previamente. Los cambios de rol aceptados SHALL aplicarse en la siguiente operación autorizada de la sesión afectada.

#### Scenario: Último administrador
- **WHEN** se intenta retirar el rol o eliminar al único administrador
- **THEN** se rechaza la operación y permanece al menos una cuenta administradora

#### Scenario: Operaciones simultáneas
- **WHEN** se intenta retirar simultáneamente a los últimos administradores
- **THEN** no se aceptan cambios que dejen el sistema sin administradores y el intento incompatible recibe un error

#### Scenario: Técnico asignado
- **WHEN** se intenta cambiar de rol a un técnico asignado a una incidencia de cualquier estado
- **THEN** se rechaza el cambio sin alterar la asignación; tras reasignar todas sus incidencias a otro técnico se permite cambiar su rol

#### Scenario: Permisos actualizados
- **WHEN** un administrador pasa a técnico o solicitante mientras mantiene una sesión abierta
- **THEN** su siguiente operación de gestión de usuarios se deniega con arreglo a su nuevo rol
