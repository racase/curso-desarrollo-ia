---
title: Detección de valores anómalos
labels: [feature, datos]
depends_on: []
---

## Contexto
El conjunto del curso contiene valores anómalos intencionados y todavía no hay una funcionalidad que los señale. El equipo de datos y mantenimiento necesita distinguir lecturas sospechosas antes de utilizarlas para evaluar los sensores.

## Historia de usuario
Como analista de datos, quiero señalar lecturas anómalas por sensor, para valorar la fiabilidad de los datos.

Como responsable de mantenimiento, quiero distinguir el tipo de anomalía detectada, para orientar la revisión del sensor.

## Alcance
- Señalar lecturas fuera del rango posible para el tipo de sensor.
- Señalar valores que permanecen fijos durante demasiado tiempo.
- Señalar saltos bruscos respecto a las lecturas vecinas.
- Identificar el sensor, el instante UTC y el motivo de cada señalamiento.
- Definir en la especificación los límites y umbrales de los tres tipos antes de programar.
- Resolver la detección sin depender de otros issues.

## Fuera de alcance
- Corregir o eliminar lecturas, diagnosticar averías y generar alertas.
- Incorporar tipos de anomalías distintos de los tres indicados.

## Criterios de aceptación
1. Dadas las reglas documentadas para cada tipo de sensor y el conjunto fijo, cuando se detectan valores fuera del rango posible, entonces las lecturas señaladas coinciden con la referencia: <valor a obtener del conjunto de datos>.
2. Dados los umbrales documentados de permanencia y el conjunto fijo, cuando se detectan valores fijos, entonces las lecturas señaladas coinciden con la referencia: <valor a obtener del conjunto de datos>.
3. Dados los umbrales documentados de salto y el conjunto fijo, cuando se comparan lecturas vecinas, entonces las lecturas señaladas coinciden con la referencia: <valor a obtener del conjunto de datos>.
4. Dadas lecturas del conjunto fijo clasificadas como normales según la especificación, cuando se ejecuta la detección, entonces no se señalan como anómalas y cada anomalía detectada identifica sensor, instante y motivo.

## Notas
- Un mismo valor puede ser normal en un sensor y anómalo en otro; por ejemplo, una presión de 0 bar con la instalación parada. La especificación debe decidir cómo se distinguen estos casos con la información disponible.
- Definir tolerancias para valores fijos, vecindad ante huecos, límites inclusivos o exclusivos y tratamiento de lecturas con varios motivos.
- Los tres tipos deben entregarse en un único cambio; si la definición de sus reglas hace el issue demasiado grande, revisar su alcance antes de implementar.
