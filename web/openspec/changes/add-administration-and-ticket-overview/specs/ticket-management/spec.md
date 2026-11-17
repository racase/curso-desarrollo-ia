# Spec Delta

## ADDED Requirements

### Requirement: Asociación opcional de activo
El sistema SHALL permitir seleccionar un único activo existente al crear una incidencia, identificándolo por código, nombre y sede. Podrá omitirse sin impedir el alta. Una referencia inexistente será rechazada y las incidencias existentes sin activo conservarán su validez.

#### Scenario: Alta con activo
- **WHEN** un usuario crea una incidencia válida seleccionando un activo existente
- **THEN** la incidencia queda vinculada a ese activo y su ficha muestra el activo y su sede

#### Scenario: Problema sin catalogar
- **WHEN** un usuario crea una incidencia válida sin seleccionar un activo
- **THEN** se permite el alta y la ficha indica que no hay activo asociado

#### Scenario: Referencia inválida
- **WHEN** se envía un identificador de activo inexistente o eliminado antes de confirmar el alta
- **THEN** se rechaza el alta con un error de validación sin crear una incidencia parcial

#### Scenario: Incidencia anterior al catálogo
- **WHEN** se consulta una incidencia creada antes de incorporar activos
- **THEN** sigue siendo accesible y muestra que no tiene activo asociado

## MODIFIED Requirements

### Requirement: Edición de datos básicos
El sistema SHALL permitir al solicitante editar título, descripción, prioridad y activo opcional de sus incidencias en propuesta o abierta; técnicos y administradores podrán hacerlo en cualquier incidencia no cerrada. Se aplicarán los límites del alta y el activo deberá existir si se indica. Solicitante y fecha de alta no serán editables; no se ofrecerá eliminación de incidencias.

#### Scenario: Corrección del solicitante
- **WHEN** un solicitante modifica datos válidos de su incidencia en propuesta o abierta
- **THEN** se guardan manteniendo solicitante, fecha, estado y técnico

#### Scenario: Corrección no autorizada
- **WHEN** un solicitante intenta editar una incidencia en curso o cualquier usuario intenta editar una cerrada
- **THEN** se rechaza la modificación y se conservan los datos anteriores

#### Scenario: Corrección del equipo
- **WHEN** un técnico o administrador modifica datos básicos válidos de una incidencia resuelta
- **THEN** se guardan sin alterar el estado ni la asignación

#### Scenario: Sustituir o retirar activo
- **WHEN** un usuario con permiso de edición selecciona otro activo existente o vacía expresamente la selección
- **THEN** se actualiza o retira la asociación sin alterar el seguimiento ni los comentarios

#### Scenario: Activo inválido en edición
- **WHEN** un usuario autorizado intenta guardar un activo que no existe
- **THEN** se rechaza la edición sin modificar los datos anteriores

### Requirement: Ficha de detalle
El sistema SHALL reunir en una ficha los datos de la incidencia, el solicitante, el técnico asignado o su ausencia, el activo con código, nombre y sede o su ausencia, el historial de estados y los comentarios, mostrando únicamente las acciones permitidas al usuario.

#### Scenario: Consulta integrada
- **WHEN** un usuario autorizado abre una incidencia con seguimiento y comentarios
- **THEN** puede consultar ambos junto a los datos de la incidencia y las acciones disponibles para su rol

#### Scenario: Contexto físico visible
- **WHEN** un usuario autorizado abre una incidencia vinculada a un activo
- **THEN** identifica el código y nombre del activo y su sede actual

#### Scenario: Ficha ajena
- **WHEN** un solicitante intenta consultar la ficha de una incidencia ajena que tiene un activo
- **THEN** se mantiene la denegación de acceso a la incidencia aunque el catálogo sea consultable
