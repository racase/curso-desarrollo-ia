---
title: Resumen diario por sensor
labels: [feature, datos]
depends_on: []
---

## Contexto
InfraData ya dispone de sensores, lecturas cargadas, conexión y el comando `check`, pero no ofrece analítica. El equipo de datos necesita consultar el comportamiento diario sin revisar las lecturas individualmente.

## Historia de usuario
Como analista de datos, quiero consultar el resumen diario por sensor, para comparar sus valores y la cantidad de lecturas recibidas.

## Alcance
- Consulta desde la línea de comandos del proyecto, igual que `check`.
- Mínimo, máximo, media y número de lecturas por sensor y día, con identificación del sensor y su unidad.
- Filtros por sede, sensor y rango de fechas, combinables.
- Días delimitados en UTC y cálculos sobre las lecturas disponibles.

## Fuera de alcance
- Detección de huecos o anomalías.
- Exportaciones, informes, API y resúmenes precalculados.

## Criterios de aceptación
1. Dado el conjunto fijo del curso, cuando se consulta un sensor y un día con lecturas, entonces su mínimo, máximo, media y recuento coinciden con los valores de referencia: <valor a obtener del conjunto de datos>, y se muestra la unidad del sensor.
2. Dado el conjunto fijo del curso, cuando se combinan los filtros de sede, sensor y fechas, entonces solo se muestran los resúmenes que cumplen todos los filtros.
3. Dadas las lecturas del conjunto fijo próximas a un cambio de día UTC, cuando se obtiene el resumen diario, entonces cada lectura pertenece al día UTC que le corresponde.
4. Dado un día incompleto del conjunto fijo, cuando se consulta su resumen, entonces el recuento refleja las lecturas disponibles y la media no incorpora lecturas inventadas.

## Notas
- Cada sensor debería tener 288 lecturas al día. Los días incompletos afectan a la media y a su interpretación.
- Fijar en la especificación los límites del rango de fechas, la precisión de salida y el tratamiento de días sin lecturas.
