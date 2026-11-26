---
title: Detección de valores anómalos
labels: [feature, datos]
depends_on: []
---

## Contexto
El conjunto del curso contiene valores anómalos que InfraData todavía no identifica. Los equipos de datos y mantenimiento necesitan distinguir lecturas sospechosas antes de interpretar el comportamiento de los sensores.

## Historia de usuario
Como analista de datos, quiero identificar las lecturas anómalas y su tipo de anomalía, para valorar la calidad de los datos.

Como responsable de mantenimiento, quiero reconocer valores imposibles, valores fijos y saltos bruscos, para orientar la revisión de los sensores.

## Alcance
- Señalar por sensor las lecturas fuera del rango posible para su tipo: temperatura, presión, caudal o nivel.
- Señalar lecturas que permanecen fijas demasiado tiempo y saltos bruscos respecto a lecturas vecinas.
- Identificar el sensor, el instante UTC, el valor y el tipo de anomalía detectada.
- Definir en la especificación todos los límites y umbrales antes de programar.
- Funcionalidad independiente de los demás issues.

## Fuera de alcance
- Corregir o eliminar lecturas.
- Diagnosticar averías, enviar alertas o entrenar modelos predictivos.

## Criterios de aceptación
1. Dado el conjunto fijo y los rangos posibles acordados para cada tipo de sensor, cuando se evalúan sus lecturas, entonces se señalan las que incumplen esos rangos según la referencia: <valor a obtener del conjunto de datos>.
2. Dado el conjunto fijo y el umbral acordado de permanencia en un valor, cuando se evalúan las lecturas consecutivas, entonces las señaladas coinciden con la referencia: <valor a obtener del conjunto de datos>.
3. Dado el conjunto fijo y la definición acordada de salto brusco, cuando se comparan las lecturas vecinas, entonces las anomalías señaladas coinciden con la referencia: <valor a obtener del conjunto de datos>.
4. Dadas las lecturas de referencia consideradas normales según las reglas acordadas, cuando se ejecuta la detección, entonces no se señalan como anómalas.

## Notas
- Un mismo valor puede ser normal en un sensor y anómalo en otro; por ejemplo, una presión de 0 bar con la instalación parada. La especificación debe decidir cómo se distinguen estos casos con la información disponible.
- Acordar tolerancias, duración de valores fijos, significado de lecturas vecinas ante huecos y tratamiento de anomalías solapadas.
- Si los tres tipos de detección exceden un único cambio, revisar el alcance con el equipo antes de implementar.
