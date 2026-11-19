# Spec Delta

## MODIFIED Requirements

### Requirement: Alta de incidencias
El sistema SHALL permitir al usuario autenticado crear una incidencia con título de 3 a 200 caracteres y descripción de 10 a 10.000, validados tras retirar espacios exteriores. El formulario no SHALL solicitar categoría ni prioridad manuales; el backend SHALL proponerlas mediante la clasificación automática y permitir el alta «Sin clasificar» cuando esta falle.

#### Scenario: Alta válida
- **WHEN** un usuario envía título y descripción válidos sin categoría ni prioridad
- **THEN** se crea la incidencia y puede consultar su ficha con la clasificación obtenida o con «Sin clasificar»

#### Scenario: Datos inválidos
- **WHEN** falta un texto obligatorio o incumple los límites
- **THEN** se muestran errores en los campos afectados y no se crea la incidencia ni se llama al proveedor

### Requirement: Edición de datos básicos
El sistema SHALL permitir al solicitante editar título, descripción y activo opcional de sus incidencias en propuesta o abierta; técnicos y administradores podrán editar esos datos y la clasificación en cualquier incidencia no cerrada. Se aplicarán los límites del alta y el activo deberá existir si se indica. Solicitante y fecha de alta no serán editables; no se ofrecerá eliminación de incidencias.

#### Scenario: Corrección del solicitante
- **WHEN** un solicitante modifica título, descripción o activo válidos de su incidencia en propuesta o abierta
- **THEN** se guardan manteniendo solicitante, fecha, estado, técnico y clasificación, sin llamar al proveedor

#### Scenario: Corrección no autorizada
- **WHEN** un solicitante intenta editar una incidencia en curso o cualquier usuario intenta editar una cerrada
- **THEN** se rechaza la modificación y se conservan los datos anteriores

#### Scenario: Corrección del equipo
- **WHEN** un técnico o administrador modifica datos básicos válidos de una incidencia resuelta
- **THEN** se guardan sin alterar el estado ni la asignación y sin llamar al proveedor

#### Scenario: Sustituir o retirar activo
- **WHEN** un usuario con permiso de edición selecciona otro activo existente o vacía expresamente la selección
- **THEN** se actualiza o retira la asociación sin alterar el seguimiento ni los comentarios

#### Scenario: Activo inválido en edición
- **WHEN** un usuario autorizado intenta guardar un activo que no existe
- **THEN** se rechaza la edición sin modificar los datos anteriores

#### Scenario: Clasificación protegida del solicitante
- **WHEN** un solicitante intenta cambiar categoría o prioridad mediante una petición manipulada
- **THEN** se rechaza el cambio en el backend sin modificar el registro

### Requirement: Listado y filtros
El sistema SHALL ofrecer un listado paginado con identificador, título, estado, categoría, prioridad, solicitante, técnico asignado y fecha de alta. SHALL mostrar «Sin clasificar» cuando falte una clasificación completa, tolerar prioridades nulas y conservar visibles las históricas. Los filtros por estado y prioridad serán combinables y mantendrán las restricciones de visibilidad.

#### Scenario: Filtro de propuestas
- **WHEN** el usuario selecciona el estado propuesta
- **THEN** el listado muestra únicamente incidencias visibles en propuesta

#### Scenario: Filtros combinados
- **WHEN** el usuario selecciona estado abierta y prioridad alta
- **THEN** el listado muestra únicamente incidencias visibles que cumplen ambas condiciones, incluidas las históricas de prioridad alta aunque carezcan de categoría

#### Scenario: Sin resultados
- **WHEN** ningún registro visible coincide con los filtros
- **THEN** se muestra un listado vacío sin error ni datos de otros usuarios

#### Scenario: Clasificación ausente en listado
- **WHEN** se lista una incidencia visible con categoría y prioridad nulas
- **THEN** aparece «Sin clasificar» sin error ni una prioridad media ficticia

### Requirement: Ficha de detalle
El sistema SHALL reunir en una ficha los datos de la incidencia, categoría, urgencia representada por la prioridad, indicación de clasificación ausente, solicitante, técnico asignado o su ausencia, activo con código, nombre y sede o su ausencia, historial de estados y comentarios, mostrando únicamente las acciones permitidas al usuario.

#### Scenario: Consulta integrada
- **WHEN** un usuario autorizado abre una incidencia con seguimiento y comentarios
- **THEN** puede consultar ambos junto a los datos de la incidencia, categoría y urgencia y las acciones disponibles para su rol

#### Scenario: Contexto físico visible
- **WHEN** un usuario autorizado abre una incidencia vinculada a un activo
- **THEN** identifica el código y nombre del activo y su sede actual

#### Scenario: Ficha ajena
- **WHEN** un solicitante intenta consultar la ficha de una incidencia ajena que tiene un activo
- **THEN** se mantiene la denegación de acceso a la incidencia aunque el catálogo sea consultable

#### Scenario: Clasificación ausente en ficha
- **WHEN** un usuario autorizado consulta una incidencia cuya clasificación automática falló
- **THEN** ve «Sin clasificar», sin confundirlo con el estado de atención ni con la categoría «Otros»
