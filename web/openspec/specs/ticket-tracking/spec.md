# ticket-tracking Specification

## Purpose

Identificar al técnico responsable de una incidencia y conservar una evolución coherente y trazable desde su apertura hasta el cierre.

## Requirements

### Requirement: Asignación a técnicos
El sistema SHALL permitir a técnicos y administradores asignar o reasignar una incidencia no cerrada a un único usuario con rol técnico. La asignación no cambiará el estado. No se permitirá retirar la asignación una vez iniciada la atención.

#### Scenario: Asignación inicial
- **WHEN** un técnico o administrador asigna una incidencia en propuesta a un técnico
- **THEN** la ficha muestra al responsable y la incidencia sigue en propuesta

#### Scenario: Reasignación
- **WHEN** un técnico o administrador reasigna una incidencia en curso a otro técnico
- **THEN** cambia el responsable sin cambiar el estado ni crear una transición en el historial

#### Scenario: Destinatario inválido
- **WHEN** se intenta asignar a un solicitante, administrador o usuario inexistente
- **THEN** se rechaza la operación y se conserva la asignación anterior

#### Scenario: Asignación no autorizada
- **WHEN** un solicitante intenta asignar una incidencia o cualquier usuario intenta reasignar una cerrada
- **THEN** se deniega la operación sin modificarla

#### Scenario: Retirada de asignación
- **WHEN** se intenta dejar sin responsable una incidencia en curso o resuelta
- **THEN** se rechaza el cambio y se mantiene el técnico asignado

### Requirement: Autorización para cambiar estados
El sistema SHALL permitir cambios de estado únicamente al técnico actualmente asignado y a los administradores. Consultar la incidencia no concederá permiso para cambiar su estado.

#### Scenario: Técnico responsable
- **WHEN** el técnico asignado solicita una transición válida
- **THEN** el sistema permite el cambio

#### Scenario: Usuario sin permiso
- **WHEN** un solicitante o un técnico distinto del asignado intenta cambiar el estado
- **THEN** se rechaza la operación y no se registra una transición

#### Scenario: Administrador
- **WHEN** un administrador solicita una transición válida en una incidencia asignada a otro usuario
- **THEN** el sistema permite el cambio

### Requirement: Flujo de estados
El sistema SHALL admitir únicamente propuesta → abierta → en curso → resuelta → cerrada. Pasar a en curso requerirá un técnico asignado. Se rechazarán saltos, retrocesos, repeticiones y reaperturas, también para administradores.

#### Scenario: Apertura de propuesta
- **WHEN** el técnico asignado o un administrador pasa una incidencia de propuesta a abierta
- **THEN** se guarda el estado abierta y se registra la transición con autor y fecha

#### Scenario: Apertura sin asignación
- **WHEN** un administrador pasa una incidencia sin técnico de propuesta a abierta
- **THEN** se guarda el estado abierta sin asignar automáticamente un técnico

#### Scenario: Solicitante intenta abrir su propuesta
- **WHEN** un solicitante intenta pasar su incidencia de propuesta a abierta
- **THEN** se rechaza la transición y se mantiene el estado propuesta

#### Scenario: Inicio de atención
- **WHEN** un usuario autorizado pasa una incidencia abierta y asignada a en curso
- **THEN** se guarda el nuevo estado

#### Scenario: Falta de responsable
- **WHEN** un administrador intenta pasar a en curso una incidencia sin técnico
- **THEN** se rechaza el cambio indicando que se necesita asignación

#### Scenario: Resolución y cierre
- **WHEN** un usuario autorizado pasa una incidencia en curso a resuelta y después a cerrada
- **THEN** ambas transiciones se guardan en ese orden

#### Scenario: Transición inválida
- **WHEN** se intenta pasar de propuesta directamente a en curso, resuelta o cerrada, cerrar desde abierta o en curso, retroceder, repetir el estado o reabrir una cerrada
- **THEN** se rechaza el cambio conservando el estado y el historial anteriores

### Requirement: Historial visible e inmutable
El sistema SHALL mostrar cada cambio de estado con estado anterior, nuevo estado, autor y fecha, del más antiguo al más reciente con orden estable. El alta se mostrará como origen separado. El historial tendrá la misma visibilidad que la incidencia y no admitirá edición ni eliminación.

#### Scenario: Evolución completa
- **WHEN** un usuario autorizado consulta una incidencia resuelta
- **THEN** ve el origen propuesta y las transiciones a abierta, en curso y resuelta con sus autores y fechas

#### Scenario: Acceso ajeno al historial
- **WHEN** un solicitante intenta consultar el historial de una incidencia de otra persona
- **THEN** se deniega el acceso sin mostrar sus entradas

### Requirement: Consistencia ante errores y concurrencia
El sistema SHALL guardar cada cambio de estado junto con una única entrada de historial o no guardar ninguno. Ante una modificación concurrente de estado o asignación, una operación basada en valores anteriores se rechazará y solicitará actualizar la ficha.

#### Scenario: Error de persistencia
- **WHEN** falla el guardado del historial durante una transición
- **THEN** se conserva el estado anterior y no se añade ninguna entrada parcial

#### Scenario: Operación desactualizada
- **WHEN** otro usuario modifica el estado o responsable antes de guardar una acción de seguimiento basada en la versión anterior
- **THEN** la acción desactualizada se rechaza sin sobrescribir el cambio previo ni duplicar el historial
