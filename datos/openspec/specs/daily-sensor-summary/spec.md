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

### Requirement: Resumen histórico precalculado
El sistema SHALL consultar los resúmenes de días históricos desde resultados precalculados sin agrupar todas las lecturas originales en cada petición.

#### Scenario: Consultas repetidas
- **WHEN** se repite una consulta de un intervalo histórico
- **THEN** las métricas se obtienen del resumen precalculado y no se recorren todas las lecturas para recalcularlo

### Requirement: Equivalencia de resultados
El sistema SHALL devolver las mismas métricas, recuentos, unidades, días UTC y filtros que la consulta diaria original.

#### Scenario: Conjunto del curso
- **WHEN** se comparan todos los resúmenes del conjunto fijo con la agregación directa de las lecturas
- **THEN** coinciden exactamente para cada sensor y día

#### Scenario: Filtros combinados
- **WHEN** se aplican sede, sensor y rango de fechas
- **THEN** aparecen los mismos resúmenes que con la consulta original

### Requirement: Actualización de lecturas
El sistema SHALL incluir inmediatamente las lecturas del día UTC actual y actualizar los días anteriores mediante un refresco programado cada minuto; se admite hasta dos minutos de retraso en condiciones normales para los días históricos.

#### Scenario: Lectura del día actual
- **WHEN** se recibe una lectura del día actual
- **THEN** el resumen consultado incorpora esa lectura sin esperar al refresco histórico

#### Scenario: Lectura de un día anterior
- **WHEN** se recibe una lectura de un día anterior
- **THEN** su resumen coincide con la agregación directa tras el siguiente refresco programado

### Requirement: Instalación reproducible
El sistema SHALL ofrecer un comando idempotente para crear y preparar el resumen precalculado sin recrear el volumen de datos.

#### Scenario: Migración repetida
- **WHEN** se ejecuta la migración por segunda vez
- **THEN** el resumen sigue disponible sin duplicar objetos ni perder datos
