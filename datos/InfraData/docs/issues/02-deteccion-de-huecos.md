---
title: Detección de huecos
labels: [feature, datos]
depends_on: []
---

## Contexto
Las lecturas del curso incluyen periodos sin datos de forma intencionada. El equipo de mantenimiento necesita localizarlos para revisar cuándo los sensores dejaron de enviar lecturas.

## Historia de usuario
Como responsable de mantenimiento, quiero listar los periodos sin lecturas que superan un umbral, para identificar interrupciones de los sensores.

## Alcance
- Listar por sensor el inicio, fin y duración de cada periodo de más de N minutos sin lecturas.
- Permitir configurar el umbral y filtrar por sede y sensor.
- Encontrar todos los periodos sin lecturas del conjunto fijo con un umbral adecuado a su cadencia de cinco minutos.
- Resolver la detección de forma independiente de los demás issues.

## Fuera de alcance
- Explicar las causas de las interrupciones o rellenar lecturas ausentes.
- Detectar valores anómalos y generar alertas.

## Criterios de aceptación
1. Dado un periodo sin lecturas del conjunto fijo cuya duración supera el umbral configurado, cuando se ejecuta la detección para ese sensor, entonces aparece con inicio, fin y duración iguales a <valor a obtener del conjunto de datos>.

A completar por el equipo antes de implementar

## Notas
- Decidir cómo se mide la duración: por ejemplo, desde la última lectura recibida hasta la siguiente, distinguiéndola del tiempo de lecturas esperadas ausentes.
- Decidir qué ocurre con los huecos al principio o al final del rango consultado, incluidos los periodos sin una lectura anterior o posterior disponible, y fijar cómo se delimita ese rango.
- Completar los criterios para verificar exhaustividad, filtros, umbral y casos de frontera antes de implementar, sin anticipar dónde están los huecos ni cuántos hay.
