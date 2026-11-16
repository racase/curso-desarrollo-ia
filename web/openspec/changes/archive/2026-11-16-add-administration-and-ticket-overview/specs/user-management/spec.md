# Spec Delta

## Purpose

Permitir a los administradores mantener las personas que acceden a InfraTicket, preservando sus referencias y la continuidad del acceso administrativo.

## ADDED Requirements

### Requirement: Gestión exclusiva de administradores
El sistema SHALL reservar a administradores el listado, alta, edición y eliminación de usuarios y la asignación de roles. El listado será paginado e identificará nombre, correo y rol. La restricción se aplicará también a accesos directos y operaciones enviadas manualmente.

#### Scenario: Consulta administrativa
- **WHEN** un administrador abre la gestión de usuarios
- **THEN** puede consultar sus nombres, correos y roles y acceder a las operaciones de mantenimiento

#### Scenario: Acceso no autorizado
- **WHEN** un técnico o solicitante intenta consultar o ejecutar una operación de gestión de usuarios por URL o petición directa
- **THEN** se deniega el acceso sin revelar datos ni modificar usuarios

#### Scenario: Acceso anónimo
- **WHEN** una persona sin sesión intenta abrir la gestión de usuarios
- **THEN** debe iniciar sesión antes de acceder a sus datos

### Requirement: Datos y credenciales válidos
El sistema SHALL exigir nombre de 1 a 255 caracteres, correo válido único de hasta 255, uno de los roles administrador, técnico o solicitante y contraseña de al menos 12 caracteres al crear un usuario. Se retirarán espacios exteriores de nombre y correo y se normalizará el correo a minúsculas. La contraseña no se mostrará ni almacenará en texto plano.

#### Scenario: Alta válida
- **WHEN** un administrador crea un usuario con todos los datos válidos
- **THEN** el usuario puede iniciar sesión con su contraseña y recibe el rol seleccionado

#### Scenario: Alta inválida
- **WHEN** falta un dato obligatorio, el nombre está vacío tras normalizar, el correo no es válido o está repetido, el rol es desconocido o la contraseña es demasiado corta
- **THEN** se muestran errores en los campos correspondientes y no se crea el usuario

### Requirement: Edición de usuarios
El sistema SHALL permitir modificar nombre, correo y rol con las validaciones del alta. La contraseña será opcional: omitirla o dejarla vacía conservará la anterior y una nueva contraseña deberá cumplir los límites del alta. Las contraseñas existentes nunca aparecerán en los formularios.

#### Scenario: Conservar contraseña
- **WHEN** un administrador cambia el nombre de un usuario y deja vacía la contraseña
- **THEN** se guarda el nombre y el usuario conserva su contraseña anterior

#### Scenario: Cambiar contraseña y rol
- **WHEN** un administrador guarda una contraseña válida y un nuevo rol permitido
- **THEN** la nueva contraseña permite iniciar sesión, la anterior deja de ser válida y los permisos del usuario reflejan su nuevo rol en la siguiente petición

#### Scenario: Mantener correo propio
- **WHEN** se edita un usuario conservando su correo actual
- **THEN** la validación de unicidad permite guardar sin considerar ese mismo usuario como duplicado

### Requirement: Eliminación sin pérdida de referencias
El sistema SHALL permitir eliminar únicamente usuarios sin referencias como solicitante, técnico asignado, autor de comentarios o autor de cambios de estado. Si existen referencias, se rechazará la eliminación con una explicación y se conservarán el usuario y toda la información relacionada.

#### Scenario: Usuario relacionado
- **WHEN** se intenta eliminar un usuario referenciado por una incidencia, comentario o cambio de estado, incluso de una incidencia cerrada
- **THEN** la operación se rechaza y no se elimina información relacionada

#### Scenario: Usuario sin referencias
- **WHEN** un administrador elimina un usuario sin referencias y permanece al menos otro administrador
- **THEN** se elimina el usuario y ya no puede acceder al panel

### Requirement: Continuidad de la administración
El sistema SHALL rechazar la eliminación o cambio a otro rol del último administrador, incluso ante operaciones concurrentes. Las operaciones concurrentes que afecten a administradores deberán conservar al menos uno al finalizar.

#### Scenario: Último administrador
- **WHEN** se intenta eliminar o degradar al único administrador
- **THEN** se rechaza la operación indicando que debe permanecer un administrador

#### Scenario: Otro administrador disponible
- **WHEN** se cambia el rol de un administrador y existe otro administrador que permanecerá como tal
- **THEN** se permite el cambio y su siguiente petición respeta el nuevo rol

#### Scenario: Operaciones simultáneas
- **WHEN** dos operaciones concurrentes intentan eliminar o degradar a los últimos administradores
- **THEN** no pueden completarse ambas dejando el sistema sin administradores

### Requirement: Coherencia de técnicos asignados
El sistema SHALL impedir cambiar a otro rol a un técnico con incidencias no cerradas asignadas. El administrador deberá reasignarlas o cerrarlas previamente; las referencias históricas de incidencias cerradas se conservarán.

#### Scenario: Técnico con trabajo pendiente
- **WHEN** se intenta cambiar el rol de un técnico asignado a una incidencia no cerrada
- **THEN** se rechaza el cambio y se indica que debe resolver las asignaciones pendientes

#### Scenario: Técnico con incidencias cerradas
- **WHEN** se cambia el rol de un técnico cuyas asignaciones corresponden únicamente a incidencias cerradas
- **THEN** se permite el cambio sin eliminar sus referencias históricas
