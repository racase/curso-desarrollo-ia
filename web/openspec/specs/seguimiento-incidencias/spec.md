# seguimiento-incidencias Specification

## Purpose

Identificar al técnico responsable de cada incidencia y permitir seguir su avance mediante transiciones coherentes y un historial consultable.

## Requirements

### Requirement: Asignación administrativa
El sistema SHALL permitir a administradores asignar o reasignar una incidencia a un usuario existente con rol técnico, en cualquier estado y sin alterar el estado. SHALL permitir que una incidencia nueva permanezca sin asignación y mostrar el técnico asignado o la ausencia de asignación en la ficha. No SHALL ofrecer desasignación manual.

#### Scenario: Asignación y reasignación
- **WHEN** un administrador asigna una incidencia libre o sustituye su técnico por otro técnico
- **THEN** se muestra el nuevo responsable y se conserva el estado

#### Scenario: Destinatario inválido
- **WHEN** un administrador intenta asignar a un solicitante, administrador o usuario inexistente
- **THEN** se rechaza la operación sin modificar la incidencia

### Requirement: Autoasignación de técnicos
El sistema SHALL permitir a un técnico asignarse únicamente a sí mismo una incidencia sin responsable. SHALL impedir que técnicos reasignen incidencias o asignen a otra persona, y que solicitantes modifiquen la asignación. Ante autoasignaciones simultáneas SHALL aceptar solo una y comunicar el conflicto a la otra.

#### Scenario: Autoasignación válida
- **WHEN** un técnico se asigna una incidencia libre
- **THEN** figura como responsable sin alterar el estado

#### Scenario: Intento no autorizado
- **WHEN** un técnico intenta apropiarse de una incidencia ya asignada o asignar a otra persona, o un solicitante intenta asignar
- **THEN** se deniega la operación y permanece el responsable anterior

#### Scenario: Competencia por una incidencia libre
- **WHEN** dos técnicos intentan autoasignarse la misma incidencia libre simultáneamente
- **THEN** solo uno queda asignado y el otro recibe un aviso de conflicto sin sobrescribir al primero

### Requirement: Avance secuencial autorizado
El sistema SHALL permitir únicamente propuesta → abierta, abierta → en curso, en curso → resuelta y resuelta → cerrada, por administradores o el técnico asignado. SHALL exigir técnico asignado al pasar de abierta a en curso. SHALL rechazar saltos, retrocesos, reaperturas y cambios al mismo estado, sin alterar datos ni historial.

#### Scenario: Ciclo completo
- **WHEN** un administrador o técnico asignado ejecuta sucesivamente los cuatro avances sobre una incidencia asignada
- **THEN** la incidencia pasa por abierta, en curso y resuelta antes de quedar cerrada

#### Scenario: Apertura sin responsable
- **WHEN** un administrador pasa una incidencia propuesta sin técnico al estado abierta
- **THEN** se acepta el cambio y se registra la transición sin exigir asignación

#### Scenario: Inicio sin responsable
- **WHEN** un administrador intenta pasar a en curso una incidencia sin técnico
- **THEN** se rechaza el cambio y se indica que debe asignarse un técnico

#### Scenario: Transición inválida
- **WHEN** se intenta cerrar desde propuesta, abierta o en curso, retroceder, reabrir una cerrada o repetir el estado actual
- **THEN** se rechaza la operación sin cambios de estado ni nuevas entradas de historial

#### Scenario: Salto del estado abierta
- **WHEN** se intenta pasar directamente de propuesta a en curso o resuelta
- **THEN** se rechaza el salto sin modificar estado ni historial

#### Scenario: Actor sin permiso
- **WHEN** un solicitante o un técnico distinto del asignado intenta avanzar el estado
- **THEN** se deniega el cambio aunque pueda consultar la ficha

### Requirement: Historial íntegro y visible
El sistema SHALL guardar cada transición aceptada con estado anterior, nuevo, actor y fecha, de forma indivisible con el cambio de estado. SHALL mostrar el historial de más antiguo a más reciente solo a quienes puedan consultar la incidencia. La creación y la asignación no SHALL producir entradas de transición.

#### Scenario: Transición registrada
- **WHEN** se acepta un avance de estado
- **THEN** la ficha refleja el estado nuevo y exactamente una entrada con origen, destino, actor y fecha

#### Scenario: Fallo o petición obsoleta
- **WHEN** falla el registro del historial o se solicita una transición sobre un estado que ya cambió
- **THEN** la operación no deja cambios parciales ni entradas duplicadas y se informa del fallo o conflicto

#### Scenario: Historia consultable
- **WHEN** un usuario con acceso abre una ficha con varios cambios
- **THEN** ve todas las transiciones en orden cronológico con actor y fecha; un solicitante ajeno no puede consultarlas
