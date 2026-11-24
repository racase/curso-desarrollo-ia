# sedes-activos Specification

## Purpose

Situar las incidencias en un activo identificable y en su sede mediante un catálogo común y fijo de demostración.

## Requirements

### Requirement: Catálogo fijo e identificable
El sistema SHALL disponer de exactamente 6 sedes y 5 activos por sede, 30 activos en total. Cada sede y activo SHALL tener un código único y un nombre legible; cada activo SHALL pertenecer obligatoriamente a una sede. La preparación repetida de los datos SHALL conservar identidades y vínculos sin duplicar registros. No SHALL ofrecer operaciones de alta, edición o eliminación del catálogo en el panel.

#### Scenario: Instalación inicial
- **WHEN** se prepara una instalación nueva
- **THEN** existen las sedes S01 a S06, denominadas Sede 01 a Sede 06, y los activos S01-A01 a S06-A05, denominados Activo 01 a Activo 05 dentro de cada sede

#### Scenario: Preparación repetida
- **WHEN** se vuelven a preparar los datos con el catálogo ya instalado y con incidencias vinculadas
- **THEN** siguen existiendo 6 sedes y 30 activos con los mismos identificadores y las incidencias conservan sus vínculos

#### Scenario: Catálogo no editable
- **WHEN** cualquier rol utiliza el panel
- **THEN** puede reconocer el catálogo al seleccionar o consultar activos y no dispone de operaciones de mantenimiento de sedes ni activos

### Requirement: Selección con contexto de sede
El sistema SHALL permitir a todos los roles seleccionar un activo existente al registrar una incidencia, identificándolo mediante su código, nombre y sede. SHALL permitir acceder a las 6 sedes sin restricciones nuevas por ubicación. La sede de la incidencia SHALL derivarse del activo, sin aceptar un vínculo contradictorio enviado por el cliente.

#### Scenario: Activos de nombre similar
- **WHEN** un usuario encuentra varios activos llamados Activo 01
- **THEN** puede diferenciarlos mediante su código único y la sede indicada antes de enviar el alta

#### Scenario: Sede manipulada
- **WHEN** se envía un activo válido junto con una sede distinta
- **THEN** la incidencia conserva como sede la correspondiente al activo seleccionado

### Requirement: Vinculación y corrección administrativa
El sistema SHALL permitir únicamente a administradores vincular o corregir el activo de cualquier incidencia existente mediante una acción específica, con activo existente obligatorio. SHALL conservar los demás datos, asignación, estado, comentarios e historial de transiciones. No SHALL habilitar edición general de la incidencia.

#### Scenario: Incidencia anterior sin activo
- **WHEN** un administrador vincula una incidencia anterior a un activo válido
- **THEN** la ficha y el listado muestran su activo y sede y desaparece la indicación «Sin activo»

#### Scenario: Corrección en cualquier estado
- **WHEN** un administrador sustituye el activo de una incidencia, incluso cerrada
- **THEN** se muestran el nuevo activo y su sede sin cambiar el estado ni añadir entradas al historial de transiciones

#### Scenario: Corrección inválida o no autorizada
- **WHEN** un técnico o solicitante intenta corregir el activo, o un administrador envía un activo vacío o inexistente
- **THEN** se rechaza la operación sin cambios y se informa del permiso insuficiente o del campo inválido
