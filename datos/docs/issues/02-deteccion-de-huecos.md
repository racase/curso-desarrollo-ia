---
title: Detección de huecos de lecturas
labels: [feature, datos]
depends_on: []
---

## Contexto
El conjunto de datos contiene periodos sin lecturas que hoy no se pueden consultar como incidencias. El equipo de mantenimiento necesita conocer su duración para investigar interrupciones de envío.

## Historia de usuario
Como responsable de mantenimiento, quiero listar los huecos de lecturas de un sensor, para localizar periodos de interrupción.

Como responsable de sede, quiero filtrar los huecos por sede, para revisar los sensores de mi instalación.

## Alcance
- Listar todos los periodos sin lecturas del conjunto de datos que superen un umbral configurable de N minutos.
- Mostrar el sensor, el inicio, el fin y la duración de cada hueco en UTC.
- Filtrar por sede y por sensor, también de forma combinada.

## Fuera de alcance
- Inferir las causas de los huecos o generar alertas.
- Rellenar lecturas ausentes.
- Depender del resumen diario o de otros issues.

## Criterios de aceptación
1. Dado un sensor con dos lecturas separadas por más de N minutos, cuando se consulta con ese umbral, entonces aparece un hueco con su inicio, fin y duración; si la separación no supera N minutos, no aparece.

## Notas
- A completar por el equipo antes de implementar.
- La especificación debe decidir cómo se mide la duración de un hueco, por ejemplo, desde la última lectura recibida hasta la siguiente, y qué ocurre con los huecos al principio o al final del rango consultado.
- La verificación final debe comprobar que se detectan todos los periodos sin lecturas del conjunto fijo, sin anticipar aquí su ubicación ni su número.
