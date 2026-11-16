# Spec Delta

## Purpose

Mantener un catálogo compartido de sedes y activos que permita localizar los elementos afectados por las incidencias sin duplicar su contexto físico.

## ADDED Requirements

### Requirement: Consulta y mantenimiento del catálogo
El sistema SHALL permitir consultar sedes y activos a los tres roles autenticados y reservar su alta, edición y eliminación a administradores. Los listados serán paginados y mostrarán el código y nombre de cada registro y, para activos, su sede. Las restricciones se aplicarán a navegación, acceso directo y operaciones.

#### Scenario: Consulta del catálogo
- **WHEN** un solicitante, técnico o administrador consulta sedes o activos
- **THEN** puede identificar sus códigos y nombres y la sede de cada activo

#### Scenario: Mantenimiento administrativo
- **WHEN** un administrador crea o edita una sede o activo con datos válidos
- **THEN** los cambios se guardan y aparecen en el catálogo compartido

#### Scenario: Escritura no autorizada
- **WHEN** un técnico o solicitante intenta crear, editar o eliminar sedes o activos mediante una petición directa
- **THEN** se rechaza la operación sin modificar el catálogo

#### Scenario: Catálogo anónimo
- **WHEN** una persona sin sesión intenta consultar el catálogo
- **THEN** debe iniciar sesión sin recibir sus datos

### Requirement: Identificación de sedes y activos
El sistema SHALL exigir código único de 1 a 50 caracteres y nombre de 1 a 200 para sedes y activos, tras retirar espacios exteriores. Los códigos se normalizarán a mayúsculas y serán únicos dentro de cada catálogo; los nombres podrán repetirse. Un activo requerirá una sede existente.

#### Scenario: Identificación inequívoca
- **WHEN** existen activos con el mismo nombre en distintas sedes
- **THEN** sus códigos únicos y sedes permiten distinguirlos al consultar o seleccionar un activo

#### Scenario: Datos inválidos
- **WHEN** falta código o nombre, se superan los límites, se repite un código normalizado o el activo no tiene una sede existente
- **THEN** se muestran errores de validación y no se guarda el registro

#### Scenario: Edición conservando código
- **WHEN** se edita un registro manteniendo su propio código
- **THEN** se permite guardar sin considerar ese mismo registro como duplicado

### Requirement: Conservación de referencias del catálogo
El sistema SHALL impedir eliminar una sede que tenga activos o un activo vinculado a cualquier incidencia, incluso cerrada. Solo podrán eliminarse registros sin referencias. Se informará del motivo del rechazo sin eliminar registros relacionados ni vaciar asociaciones.

#### Scenario: Sede ocupada
- **WHEN** un administrador intenta eliminar una sede que contiene activos
- **THEN** se rechaza la eliminación y se conservan la sede y sus activos

#### Scenario: Activo utilizado
- **WHEN** se intenta eliminar un activo asociado a una incidencia
- **THEN** se rechaza la eliminación y la incidencia conserva su asociación

#### Scenario: Registro sin referencias
- **WHEN** un administrador elimina una sede sin activos o un activo sin incidencias
- **THEN** se elimina ese registro sin afectar a otros datos

### Requirement: Contexto actual del activo
El sistema SHALL reflejar los cambios de nombre, código o sede de un activo en las consultas de las incidencias que lo referencian. La sede se obtendrá del activo y no se registrará de forma independiente en la incidencia; no se mantendrá una instantánea histórica de la ubicación.

#### Scenario: Traslado de activo
- **WHEN** un administrador cambia un activo a otra sede existente
- **THEN** las incidencias asociadas muestran la nueva sede al volver a consultarlas
