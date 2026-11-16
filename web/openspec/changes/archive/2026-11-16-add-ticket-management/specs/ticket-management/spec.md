# Spec Delta

## Purpose

Permitir comunicar y consultar incidencias en el panel, manteniendo una visibilidad y unas operaciones coherentes con el rol de cada usuario.

## ADDED Requirements

### Requirement: Alta de incidencias
El sistema SHALL permitir al usuario autenticado crear una incidencia con título de 3 a 200 caracteres, descripción de 10 a 10.000 y prioridad baja, media, alta o crítica. Título y descripción se validan tras retirar espacios exteriores. La prioridad inicial del formulario será media.

#### Scenario: Alta válida
- **WHEN** un usuario envía título y descripción válidos y prioridad media
- **THEN** se crea la incidencia y puede consultar su ficha con esos datos

#### Scenario: Datos inválidos
- **WHEN** falta un campo obligatorio, el texto incumple los límites o la prioridad no es válida
- **THEN** se muestran errores en los campos afectados y no se crea la incidencia

### Requirement: Identidad y valores iniciales
El sistema SHALL establecer el solicitante a partir del usuario conectado, el estado inicial propuesta, la fecha de creación automática y ningún técnico asignado. Los datos enviados por el cliente no podrán alterar esos valores de alta.

#### Scenario: Intento de suplantar solicitante o estado
- **WHEN** el alta incluye otro solicitante, un técnico o un estado diferente de propuesta
- **THEN** la incidencia creada conserva el usuario conectado como solicitante, estado propuesta y ningún técnico asignado

### Requirement: Visibilidad por rol
El sistema SHALL mostrar a los solicitantes únicamente sus incidencias y permitir a técnicos y administradores consultar todas. La restricción se aplicará en listados, fichas y operaciones relacionadas. El acceso sin autenticación requerirá iniciar sesión.

#### Scenario: Solicitante consulta el listado
- **WHEN** un solicitante abre el listado existiendo incidencias suyas y de otra persona
- **THEN** solo aparecen las suyas

#### Scenario: Acceso directo ajeno
- **WHEN** un solicitante intenta abrir o modificar la incidencia de otra persona mediante su identificador
- **THEN** se deniega el acceso sin exponer datos ni modificar la incidencia

#### Scenario: Consulta del equipo
- **WHEN** un técnico o administrador consulta el listado o una ficha
- **THEN** puede consultar incidencias de cualquier solicitante

#### Scenario: Acceso anónimo
- **WHEN** una persona sin sesión intenta abrir las incidencias
- **THEN** se le solicita iniciar sesión sin mostrar sus datos

### Requirement: Listado y filtros
El sistema SHALL ofrecer un listado paginado con identificador, título, estado, prioridad, solicitante, técnico asignado y fecha de alta. Los filtros por estado y prioridad serán combinables y mantendrán las restricciones de visibilidad.

#### Scenario: Filtro de propuestas
- **WHEN** el usuario selecciona el estado propuesta
- **THEN** el listado muestra únicamente incidencias visibles en propuesta

#### Scenario: Filtros combinados
- **WHEN** el usuario selecciona estado abierta y prioridad alta
- **THEN** el listado muestra únicamente incidencias visibles que cumplen ambas condiciones

#### Scenario: Sin resultados
- **WHEN** ningún registro visible coincide con los filtros
- **THEN** se muestra un listado vacío sin error ni datos de otros usuarios

### Requirement: Edición de datos básicos
El sistema SHALL permitir al solicitante editar título, descripción y prioridad de sus incidencias en propuesta o abierta; técnicos y administradores podrán hacerlo en cualquier incidencia no cerrada. Se aplicarán los límites del alta. Solicitante y fecha de alta no serán editables; no se ofrecerá eliminación de incidencias.

#### Scenario: Corrección del solicitante
- **WHEN** un solicitante modifica datos válidos de su incidencia en propuesta o abierta
- **THEN** se guardan manteniendo solicitante, fecha, estado y técnico

#### Scenario: Corrección no autorizada
- **WHEN** un solicitante intenta editar una incidencia en curso o cualquier usuario intenta editar una cerrada
- **THEN** se rechaza la modificación y se conservan los datos anteriores

#### Scenario: Corrección del equipo
- **WHEN** un técnico o administrador modifica datos básicos válidos de una incidencia resuelta
- **THEN** se guardan sin alterar el estado ni la asignación

### Requirement: Ficha de detalle
El sistema SHALL reunir en una ficha los datos de la incidencia, el solicitante, el técnico asignado o su ausencia, el historial de estados y los comentarios, mostrando únicamente las acciones permitidas al usuario.

#### Scenario: Consulta integrada
- **WHEN** un usuario autorizado abre una incidencia con seguimiento y comentarios
- **THEN** puede consultar ambos junto a los datos de la incidencia y las acciones disponibles para su rol
