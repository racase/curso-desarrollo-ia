# Spec Delta

## Purpose

Identifica lecturas que merecen revisión por su valor, persistencia o cambio respecto a lecturas cercanas del mismo sensor.

## ADDED Requirements

### Requirement: Rango posible por tipo
El sistema SHALL señalar como fuera de rango los valores de temperatura fuera de [-20, 80] °C, presión fuera de [0, 20] bar, caudal fuera de [0, 200] m3/h y nivel fuera de [0, 100] %.

#### Scenario: Valor fuera de rango
- **WHEN** una lectura supera uno de los límites de su tipo
- **THEN** aparece con el motivo «fuera de rango»

#### Scenario: Cero de presión
- **WHEN** una lectura de presión vale 0 bar
- **THEN** no se clasifica como fuera de rango solo por ese valor

### Requirement: Valor fijo prolongado
El sistema SHALL señalar cada lectura de una secuencia de valores idénticos del mismo sensor que dure al menos 15 minutos entre su primera y última lectura, con separación de cinco minutos entre lecturas consecutivas.

#### Scenario: Tramo fijo
- **WHEN** un sensor repite exactamente el valor durante al menos 15 minutos sin interrupción
- **THEN** las lecturas del tramo aparecen con el motivo «valor fijo»

### Requirement: Salto brusco
El sistema SHALL señalar una lectura cuando difiera de la anterior del mismo sensor en más de 20 °C para temperatura, 3 bar para presión, 25 m3/h para caudal o 25 puntos porcentuales para nivel, siempre que las lecturas estén separadas cinco minutos.

#### Scenario: Cambio mayor que el umbral
- **WHEN** el valor cambia más que el umbral de su tipo frente a la lectura anterior inmediata
- **THEN** la lectura nueva aparece con el motivo «salto brusco»

#### Scenario: Lectura después de un hueco
- **WHEN** la lectura anterior está separada por más de cinco minutos
- **THEN** no se clasifica un salto a través del hueco

### Requirement: Resultado consultable
El sistema SHALL mostrar el sensor, sede, instante UTC, valor, unidad y todos los motivos aplicables, con filtros combinables de sede y sensor.

#### Scenario: Lectura con varios motivos
- **WHEN** una lectura cumple varias reglas
- **THEN** aparece una sola vez con todos sus motivos
