---
title: Detección de huecos
labels: [feature, datos]
depends_on: []
---

## Contexto
Las lecturas del curso contienen periodos sin datos que todavía no se pueden consultar en InfraData. El equipo de mantenimiento necesita identificarlos para revisar la continuidad de cada sensor.

## Historia de usuario
Como responsable de mantenimiento, quiero listar los periodos sin lecturas de un sensor, para investigar las interrupciones de envío.

## Alcance
- Listado de periodos en que un sensor permanece más de N minutos sin enviar lecturas.
- Identificación del sensor, inicio, fin y duración de cada periodo, con tiempos en UTC.
- Umbral configurable y filtros combinables por sede y sensor.
- Detección de todos los periodos del conjunto fijo que superen el umbral acordado.
- Funcionalidad independiente de los demás issues.

## Fuera de alcance
- Rellenar lecturas ausentes o corregir datos.
- Explicar la causa de una interrupción o detectar valores anómalos.
- Alertas e informes.

## Criterios de aceptación
1. Dado el conjunto fijo del curso y un sensor con un intervalo sin lecturas superior al umbral configurado, cuando se buscan sus huecos, entonces se muestra el periodo con su inicio, fin y duración conforme a la definición acordada: <valor a obtener del conjunto de datos>.

A completar por el equipo antes de implementar.

## Notas
- Acordar cómo se mide la duración de un hueco; por ejemplo, desde la última lectura recibida hasta la siguiente, y si se descuenta el intervalo esperado de 5 minutos.
- Decidir cómo se tratan los huecos al principio o al final del rango consultado, qué rango se considera y cómo se representan los extremos sin una lectura que los cierre.
- Completar los criterios antes de implementar, incluyendo exhaustividad, filtros y comparación estricta con el umbral.
