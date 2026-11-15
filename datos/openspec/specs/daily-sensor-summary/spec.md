# daily-sensor-summary Specification

## Purpose
Permite consultar métricas diarias de los sensores con fechas UTC y filtros para revisar el comportamiento de cada instalación.

## Requirements

### Requirement: Resumen por sensor y día
El sistema SHALL devolver mínimo, máximo, media, número de lecturas y unidad de cada sensor y día UTC con lecturas.

#### Scenario: Día completo
- **WHEN** se consulta un sensor durante un día con 288 lecturas
- **THEN** se muestran las métricas calculadas con esas 288 lecturas y la unidad del sensor

#### Scenario: Día incompleto
- **WHEN** se consulta un día con lecturas ausentes
- **THEN** el recuento y la media consideran solamente las lecturas recibidas

### Requirement: Filtros combinables
El sistema SHALL limitar los resúmenes por sede, código de sensor y rango inclusivo de días UTC cuando se indiquen filtros.

#### Scenario: Filtros combinados
- **WHEN** se indican sede, sensor y fechas
- **THEN** solo se devuelven los días del sensor elegido dentro de la sede y el rango

### Requirement: Consulta desde CLI
El sistema SHALL ofrecer el resumen desde la línea de comandos y señalar una consulta sin resultados.

#### Scenario: Sin resultados
- **WHEN** ningún sensor tiene lecturas para los filtros indicados
- **THEN** el comando informa de que no hay resultados sin mostrar datos ajenos
