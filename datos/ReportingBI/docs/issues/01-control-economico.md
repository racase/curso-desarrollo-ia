---
title: Informe de control económico de obra
labels: [feature, reporting, powerbi]
depends_on: []
---

# Informe de control económico de obra

## Contexto

Dirección revisa cada mes cómo va económicamente cada obra. Hoy lo hace con exportaciones
del ERP (Navision) a Excel que monta a mano cada jefe de obra, con criterios distintos y
con días de retraso. Queremos un informe de Power BI único, conectado a los datos del ERP,
que responda a una pregunta: **¿en qué obras estamos perdiendo dinero, en qué partida, y
estamos certificando lo que producimos?**

## Historia de usuario

Como **director de producción**, quiero ver para cada obra su presupuesto, lo que llevamos
gastado, lo que hemos producido y lo que hemos certificado al cliente, para detectar a tiempo
las obras y partidas que se desvían y la obra ejecutada que todavía no hemos cobrado.

## Alcance

- Indicadores por obra y por partida:
  - presupuesto de coste y presupuesto de venta;
  - coste real;
  - producción (obra ejecutada valorada a precio de venta, según el avance medido);
  - certificado (lo facturado al cliente);
  - obra pendiente de certificar (producción menos certificado);
  - margen previsto y margen real, en importe y en porcentaje;
  - desviación de coste: coste real frente a lo que se debería haber gastado para la obra
    producida.
- Una página resumen con los indicadores del conjunto de obras y una tabla por obra.
- Una página de detalle obra × partida que destaque visualmente las partidas con desviación.
- Evolución mensual de coste real, producción y certificado.
- Filtros por delegación, tipo de obra, jefe de obra y estado de la obra.
- Publicado en el servicio Power BI, en el área de trabajo de la demo.

## Fuera de alcance

- Plazos, hitos y avance planificado (issue 02).
- Previsiones a fin de obra.
- Seguridad por filas (que cada jefe de obra vea solo sus obras).
- Refresco programado en el servicio.

## Criterios de aceptación

- Dado el informe publicado, cuando lo abro sin filtros, entonces veo los totales de
  presupuesto, coste real, producción, certificado y margen real del conjunto de obras, y
  cuadran con los datos del ERP a la fecha de corte.
- Dada una obra con una partida que cuesta más de lo previsto para lo que lleva producido,
  cuando abro el detalle obra × partida, entonces esa partida aparece destacada y su
  desviación se ve en porcentaje.
- Dada una obra con obra ejecutada que no se ha certificado, cuando miro la tabla por obra,
  entonces veo el importe pendiente de certificar y puedo ordenar las obras por ese importe.
- Dada una obra terminada que ha perdido dinero, cuando miro la tabla por obra, entonces su
  margen real aparece en negativo y destacado.
- Dada una obra que todavía no ha empezado, cuando miro el informe, entonces aparece con su
  presupuesto y sin coste ni producción, sin errores.
- Dado cualquier filtro de delegación, tipo, jefe de obra o estado, cuando lo aplico, entonces
  todos los visuales de la página se filtran de forma coherente.
- Dado cualquier indicador del informe, cuando lo compruebo contra el ERP para una obra,
  entonces la cifra coincide al céntimo.

## Notas

- Los datos vienen de Navision. Cuál es la tabla y el campo de cada indicador, y cómo se
  tratan los signos y los valores vacíos, se decide en el proposal tras explorar la base.
- Las definiciones de producción, margen y desviación deben quedar escritas en la spec: son
  las que dirección va a usar a partir de ahora.
- Formato español: miles con punto, decimales con coma, importes en euros.
