# weekly-site-csv Specification

## Purpose
Permite compartir los resúmenes diarios de todos los sensores de una sede durante una semana en un CSV compatible con Excel en español.

## Requirements

### Requirement: Selección de sede y semana
El sistema SHALL exportar los resúmenes diarios de una sede para una semana de lunes a domingo en UTC, indicada por la fecha del lunes.

#### Scenario: Semana válida
- **WHEN** se solicita una sede y una fecha de lunes
- **THEN** el fichero contiene solamente los resúmenes de esa sede desde el lunes hasta el domingo inclusive

#### Scenario: Día no válido
- **WHEN** la fecha indicada no es un lunes
- **THEN** se informa del error y no se crea el fichero

### Requirement: Formato CSV estable
El sistema SHALL escribir UTF-8 con BOM, separador punto y coma, coma decimal, dos decimales y encabezados `fecha;sede;codigo_sensor;nombre_sensor;unidad;minimo;maximo;media;lecturas`.

#### Scenario: Fichero para Excel
- **WHEN** se abre el fichero en Excel en español
- **THEN** los campos y los valores numéricos aparecen en columnas separadas y con decimales reconocibles

### Requirement: Concordancia con el resumen diario
El sistema SHALL utilizar las mismas métricas que la consulta diaria para cada sensor y día con lecturas.

#### Scenario: Comparación diaria
- **WHEN** se compara una fila del CSV con el resumen diario del mismo sensor y fecha
- **THEN** las métricas y el recuento coinciden tras aplicar la presentación a dos decimales

### Requirement: Protección del fichero existente
El sistema SHALL evitar sobrescribir un fichero existente.

#### Scenario: Destino ocupado
- **WHEN** ya existe un fichero en la ruta solicitada
- **THEN** se informa del error y el contenido existente no cambia
