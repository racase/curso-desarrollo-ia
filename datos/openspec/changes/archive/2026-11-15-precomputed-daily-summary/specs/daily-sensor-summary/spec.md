# Spec Delta

## ADDED Requirements

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
