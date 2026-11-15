# Design

## Context

Las lecturas tienen marca temporal UTC y sensor. El issue no depende de otros cálculos.

## Goals / Non-Goals

**Goals:** detectar intervalos de una misma serie ordenada temporalmente y mostrar todos los que superan el umbral.

**Non-Goals:** inferir ausencias antes de la primera lectura o después de la última.

## Decisions

- Medir el hueco desde la última lectura recibida hasta la siguiente; no restar los cinco minutos esperados. Esto permite comprobar directamente el umbral estricto.
- Comparar lecturas consecutivas mediante una función de ventana en SQL y devolver solo los huecos. Así no se trasladan todas las lecturas a Python.
- El umbral predeterminado es 5 minutos y debe ser positivo. Con el conjunto regular, cualquier intervalo superior detecta una interrupción.

## Risks / Trade-offs

- Un umbral menor que la cadencia normal reportará intervalos regulares → documentar la cadencia de cinco minutos y permitir configurar el umbral.
