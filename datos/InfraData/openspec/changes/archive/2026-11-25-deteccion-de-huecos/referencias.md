# Referencias independientes del issue 02

El usuario aporta estos resultados obtenidos con una consulta propia. La consulta también
se ejecutó en la sesión contra la base local y devolvió las mismas tres filas. Se conservan
como expectativas fijas para el conjunto inicial del curso. Todas las fechas son UTC.

| Sensor | Última lectura | Siguiente lectura | Duración | Minutos |
|---|---|---|---|---:|
| VAL-F01 | 2026-08-20 22:10:00 | 2026-08-20 23:05:00 | 55 min | 55 |
| SAG-P02 | 2026-09-03 07:55:00 | 2026-09-03 14:30:00 | 6 h 35 min | 395 |
| PAT-L01 | 2026-10-11 23:55:00 | 2026-10-14 06:00:00 | 54 h 5 min | 3245 |

Consulta de referencia, conservada como evidencia documental, no como implementación:

```sql
SELECT s.code, r.anterior AS ultima_lectura, r.time AS siguiente,
       r.time - r.anterior AS duracion
FROM (SELECT sensor_id, time,
             lag(time) OVER (PARTITION BY sensor_id ORDER BY time) AS anterior
      FROM readings) r JOIN sensors s ON s.id = r.sensor_id
WHERE r.time - r.anterior > interval '15 minutes' ORDER BY 2;
```

La duración es el intervalo entre lecturas recibidas; no se descuentan cinco minutos.
La consulta excluye intervalos de exactamente 15 minutos y solo acredita huecos internos
del histórico completo. No acredita extremos abiertos ni el resultado con umbral de 5.
Los extremos abiertos requieren los escenarios controlados de la especificación.

Antes de implementar, el 2026-11-25 se repitió esta consulta independiente cambiando
únicamente el intervalo a `interval '5 minutes'`. Devolvió exactamente las mismas tres
filas, sin otras interrupciones internas. Se verificaron además 313760 lecturas, mínimo
`2026-08-01 00:00:00+00` y máximo `2026-10-30 23:55:00+00`. Las pruebas usarán las mismas
expectativas fijas para ambos umbrales, sin calcularlas con la implementación.
