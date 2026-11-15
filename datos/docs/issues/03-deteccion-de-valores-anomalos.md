---
title: Detección de valores anómalos
labels: [feature, datos]
depends_on: []
---

## Contexto
Algunas lecturas del conjunto de datos son anómalas y no existe una forma de señalarlas. Los analistas y el equipo de mantenimiento necesitan distinguir los casos que requieren revisión.

## Historia de usuario
Como analista de datos, quiero identificar lecturas anómalas por sensor y motivo, para evaluar la calidad de los datos.

Como responsable de mantenimiento, quiero revisar lecturas sospechosas, para investigar posibles fallos del sensor o de la instalación.

## Alcance
- Señalar valores fuera del rango posible para el tipo de sensor.
- Señalar valores que permanecen fijos durante demasiado tiempo.
- Señalar saltos bruscos respecto a las lecturas vecinas.
- Identificar el sensor, la lectura afectada y el tipo de anomalía.

## Fuera de alcance
- Corregir o eliminar lecturas.
- Diagnosticar la causa de una anomalía o emitir alertas.
- Depender de otros issues.

## Criterios de aceptación
1. Dada una lectura que excede el rango definido para su tipo de sensor, cuando se revisan las anomalías, entonces se señala como valor fuera de rango.
2. Dada una secuencia de lecturas iguales que supera el tiempo máximo definido, cuando se revisan las anomalías, entonces se señala como valor fijo prolongado.
3. Dada una lectura cuyo cambio respecto a sus vecinas supera el umbral definido, cuando se revisan las anomalías, entonces se señala como salto brusco.
4. Dadas las lecturas del curso, cuando se revisan las anomalías de cada sensor, entonces los resultados identifican las lecturas y los motivos conforme a los límites y umbrales aprobados en la especificación.

## Notas
- Antes de programar, la especificación debe definir los rangos posibles y los límites y umbrales de cada tipo de anomalía, además del tratamiento de extremos de serie y periodos sin lecturas.
- Un mismo valor puede ser normal en un sensor y anómalo en otro; por ejemplo, una presión de 0 bar puede ser normal con la instalación parada. La especificación debe decidir cómo se distinguen esos casos.
