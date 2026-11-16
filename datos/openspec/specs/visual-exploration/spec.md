# visual-exploration Specification

## Purpose
Permite explorar visualmente los datos fijos del curso dentro de VS Code.

## Requirements

### Requirement: Cuaderno Python ejecutable

El sistema SHALL ofrecer un fichero `.py` con celdas `# %%` que se ejecutan en orden en la ventana interactiva de VS Code y muestran los dos gráficos.

#### Scenario: Ejecución completa

- **WHEN** se ejecutan todas las celdas con la base de datos preparada y las dependencias opcionales instaladas
- **THEN** aparece el mapa de completitud y la serie del primer sensor dentro de VS Code sin errores

### Requirement: Completitud por sensor y día UTC

El sistema SHALL mostrar una fila por cada sensor y una columna por cada día UTC del 1 de agosto al 30 de octubre de 2026. Cada celda SHALL representar `100 × recuento / 288`, con el valor exacto accesible al pasar el cursor. Los días sin fila en el resumen SHALL aparecer como 0 %.

#### Scenario: Día parcial

- **WHEN** un sensor registra 210 lecturas en un día
- **THEN** la celda muestra 72,92 %

#### Scenario: Día sin lecturas

- **WHEN** un sensor no tiene resumen para un día del periodo
- **THEN** la celda existe y muestra 0 %

### Requirement: Uso del resumen precalculado

El sistema SHALL obtener los recuentos del mapa mediante el resumen diario precalculado del issue 1, sin agrupar todas las lecturas originales.

#### Scenario: Construcción del mapa

- **WHEN** se carga la matriz de completitud
- **THEN** sus recuentos proceden de `daily_summaries`

### Requirement: Serie seleccionable con zoom

El sistema SHALL permitir seleccionar cualquiera de los sensores del conjunto y ampliar un intervalo de su serie temporal en un gráfico interactivo.

#### Scenario: Cambio de sensor

- **WHEN** se selecciona otro sensor
- **THEN** se consultan únicamente sus lecturas y se presenta su serie temporal

#### Scenario: Ampliación

- **WHEN** se arrastra sobre una zona del gráfico
- **THEN** el eje temporal se amplía a esa zona
