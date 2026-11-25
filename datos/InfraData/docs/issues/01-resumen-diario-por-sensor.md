---
title: Resumen diario por sensor
labels: [feature, datos]
depends_on: []
---

## Contexto
InfraData ya dispone de sensores, lecturas cargadas, conexión y el comando `check`, pero no ofrece analítica, informes ni API. El equipo de datos necesita consultar el comportamiento diario de los sensores sin revisar las lecturas individualmente.

## Historia de usuario
Como analista de datos, quiero consultar el resumen diario de cada sensor, para comparar su comportamiento entre días.

Como responsable de sede, quiero filtrar el resumen por sede, sensor y fechas, para revisar los equipos de mi ámbito.

## Alcance
- Consultar desde la línea de comandos del proyecto, igual que `check`.
- Mostrar por sensor y día UTC el mínimo, máximo, media y número de lecturas, junto con la unidad del sensor.
- Filtrar por sede, sensor y rango de fechas, combinando los filtros.
- Realizar las agregaciones en la base de datos.

## Fuera de alcance
- Detección de huecos o anomalías.
- Exportación de informes, API y resúmenes precalculados.

## Criterios de aceptación
1. Dado el conjunto fijo del curso, cuando se consulta un sensor y un día con lecturas, entonces el mínimo, máximo, media y recuento coinciden con los valores de referencia: <valor a obtener del conjunto de datos>, y se muestra su unidad.
2. Dadas lecturas próximas a medianoche UTC, cuando se consulta el resumen, entonces cada lectura se asigna al día UTC que le corresponde, independientemente de la zona horaria local.
3. Dado el conjunto fijo, cuando se combinan sede, sensor y rango de fechas, entonces solo aparecen resultados que cumplen todos los filtros.
4. Dado un día con lecturas ausentes, cuando se consulta su resumen, entonces el recuento refleja las lecturas disponibles y la media se calcula sobre ellas, sin inventar lecturas.

## Notas
- Cada sensor debería tener 288 lecturas al día; los días incompletos afectan a la media y a su comparación con días completos.
- Fijar en la especificación la inclusión de los extremos del rango, el comportamiento de los días sin lecturas y la precisión de presentación.
