---
title: Informe de plazos y avance de obra
labels: [feature, reporting, powerbi]
depends_on: [01-control-economico]
---

# Informe de plazos y avance de obra

## Contexto

Además del dinero, dirección quiere saber qué obras van con retraso. Hoy el avance real lo
mide cada jefe de obra a final de mes y los hitos se apuntan en el ERP, pero nadie los cruza
con la planificación. Este informe amplía el modelo del informe de control económico: usa
las mismas obras, filtros y calendario.

## Historia de usuario

Como **director de producción**, quiero comparar el avance real de cada obra con el
planificado y ver qué hitos se han retrasado, para actuar sobre las obras que no van a llegar
a tiempo antes de que el cliente lo reclame.

## Alcance

- Avance planificado de cada obra a cada fecha, según el presupuesto planificado en el tiempo.
- Avance real de cada obra a cada fecha, según las mediciones de avance.
- Desviación de avance (real menos planificado, en puntos) e índice de plazo (real entre
  planificado).
- Curva S: avance planificado frente a real acumulado por mes, para una obra o para el
  conjunto.
- Hitos de cada obra con su fecha planificada y real, y su estado: cumplido a tiempo,
  cumplido con retraso, pendiente o retrasado.
- Número de hitos retrasados y días de retraso.
- Ranking de obras por desviación de avance.
- Mismos filtros que el informe de control económico. Publicado en la misma área de trabajo.

## Fuera de alcance

- Planificación detallada por actividades (diagrama de Gantt).
- Recalcular la planificación o prever la fecha de fin.
- Cambios en la parte económica del informe 01, salvo lo necesario para compartir el modelo.

## Criterios de aceptación

- Dada una obra que avanza más despacio de lo planificado, cuando miro su curva S, entonces
  la línea real queda por debajo de la planificada y la desviación de avance aparece en
  negativo.
- Dada una obra con hitos cuya fecha planificada ya ha pasado sin cumplirse, cuando miro la
  tabla de hitos, entonces aparecen como retrasados con los días de retraso hasta la fecha
  de corte.
- Dado un hito cumplido después de su fecha planificada, cuando miro la tabla de hitos,
  entonces aparece como cumplido con retraso y con los días de diferencia.
- Dado un hito sin fecha real, cuando miro la tabla de hitos, entonces su fecha real aparece
  vacía, no como una fecha antigua.
- Dada una línea de presupuesto sin fecha de planificación, cuando calculo el avance
  planificado, entonces el informe la trata de la forma decidida en la spec y lo explica.
- Dada una obra que todavía no ha empezado, cuando miro el informe, entonces su avance real
  es 0 % y no cuenta como retrasada.
- Dado el ranking de obras, cuando lo ordeno por desviación de avance, entonces la obra más
  retrasada aparece la primera.
- Dado cualquier indicador, cuando lo compruebo contra el ERP para una obra, entonces la cifra
  coincide.

## Notas

- El avance real y los hitos están en tablas que no son estándar de Navision: las añadió el
  partner que implantó el ERP. Hay que localizarlas al explorar la base.
- El modelo semántico es el mismo que el del issue 01: este cambio lo amplía, no crea otro.
- La fecha de corte de los datos es la del último movimiento registrado en el ERP.
