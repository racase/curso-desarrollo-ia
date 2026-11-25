---
title: Valoración de la resolución
labels: [feature, web]
depends_on: [1, 2]
---

## Resumen
Permitir que el solicitante valore la atención recibida con entre 1 y 5 estrellas cuando su incidencia esté cerrada y mostrar la media de valoraciones de cada técnico en el panel del administrador.

## Problema y oportunidad
El cierre de una incidencia refleja que su atención ha terminado, pero no recoge la satisfacción del solicitante. Una valoración sencilla permite conocer su experiencia y ofrece al administrador una referencia sobre la atención de cada técnico.

## Objetivos
- Recoger la satisfacción del solicitante tras el cierre de su incidencia.
- Facilitar al administrador la consulta de la valoración media de cada técnico.

## Usuarios afectados
- Solicitantes: puntuar la atención recibida en sus propias incidencias cerradas.
- Administradores: consultar las medias de valoraciones por técnico.
- Técnicos: su atención queda reflejada en las valoraciones agregadas.

## Alcance
- Valoración opcional de 1 a 5 estrellas desde la ficha de una incidencia cerrada.
- Consulta de la puntuación enviada en la propia ficha por el solicitante.
- Resumen por técnico en el panel del administrador con la media y el número de valoraciones recibidas.

## Fuera de alcance
- Reseñas de texto, encuestas adicionales y respuestas a las valoraciones.
- Notificaciones o recordatorios para solicitar una valoración.
- Rankings, exportaciones y análisis de evolución temporal.
- Cambios en el flujo de resolución y cierre de incidencias.

## Requisitos de producto
- Solo el solicitante de la incidencia puede valorarla, y únicamente cuando su estado sea Cerrada; estar Resuelta no habilita la valoración.
- La puntuación debe ser un número entero entre 1 y 5, representado mediante estrellas y con una alternativa textual comprensible.
- Valorar es voluntario: una incidencia puede permanecer cerrada sin puntuación.
- Cada incidencia aporta como máximo una valoración al cálculo de la media del técnico al que se atribuya la atención.
- La media es la media aritmética de las puntuaciones recibidas; las incidencias sin valorar no cuentan como cero ni entran en el divisor.
- El resumen identifica a cada técnico y muestra su media sobre 5 junto con el número de valoraciones. Si no tiene valoraciones, indica «Sin valoraciones».
- El resumen de medias es accesible exclusivamente para administradores; los permisos se aplican también al acceso directo a los datos y al envío de puntuaciones.
- La interacción con las estrellas debe ser utilizable mediante teclado y lector de pantalla y respetar las pautas visuales de `DESIGN.md`.

## Criterios de éxito
- El solicitante puede puntuar una incidencia propia cerrada y reconocer la puntuación registrada.
- No se admiten valoraciones de incidencias ajenas, pendientes de cierre o con puntuaciones fuera de la escala.
- El administrador puede identificar la media de cada técnico y cuántas valoraciones la sustentan.
- Las medias corresponden a las puntuaciones recibidas sin incluir incidencias no valoradas ni duplicar aportaciones de una misma incidencia.

## Riesgos y preguntas abiertas
- ¿Se permite modificar una valoración enviada? Si se permite, ¿durante cuánto tiempo?
- La asignación actual permite cambiar de técnico incluso después del cierre. ¿La valoración se atribuye al técnico asignado al cerrar o al asignado al valorar? ¿Cómo se conserva esa atribución ante reasignaciones posteriores?
- ¿Cómo se trata una incidencia cerrada sin técnico asignado y qué ocurre con sus valoraciones si el técnico cambia de rol o se elimina?
- ¿Cuántos decimales se muestran en la media y cuándo se actualiza el resumen del administrador?
- ¿Las incidencias que ya estaban cerradas al incorporar esta capacidad también pueden valorarse?
