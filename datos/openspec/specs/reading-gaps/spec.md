# reading-gaps Specification

## Purpose
Permite localizar interrupciones del envío de lecturas por sensor y conocer sus límites y duración para su revisión.

## Requirements

### Requirement: Detección de intervalos
El sistema SHALL listar todos los pares de lecturas consecutivas de un sensor separados por más minutos que el umbral indicado.

#### Scenario: Intervalo mayor que el umbral
- **WHEN** dos lecturas consecutivas están separadas por más de N minutos
- **THEN** se muestra un hueco desde la primera lectura hasta la segunda con duración en minutos

#### Scenario: Intervalo igual al umbral
- **WHEN** la separación entre lecturas es exactamente N minutos
- **THEN** no se muestra como hueco

### Requirement: Filtros de sensores
El sistema SHALL permitir filtrar los huecos por sede y código de sensor de forma combinada.

#### Scenario: Filtros combinados
- **WHEN** se selecciona una sede y un sensor
- **THEN** solo se muestran los huecos de ese sensor en esa sede

### Requirement: Límites de la serie
El sistema SHALL informar únicamente de intervalos delimitados por dos lecturas recibidas.

#### Scenario: Inicio o fin sin lectura vecina
- **WHEN** no existe una lectura anterior o posterior en la serie
- **THEN** no se presenta un hueco abierto sin ambos límites
