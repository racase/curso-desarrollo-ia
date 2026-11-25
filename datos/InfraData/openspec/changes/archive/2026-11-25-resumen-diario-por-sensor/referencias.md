# Referencias del conjunto inicial

Valores consultados directamente en la base del curso, sin usar `daily_summary`.
La consulta independiente usa suma/recuento para contrastar la media de `AVG`.

```sql
SELECT s.code, s.unit, min(r.value), max(r.value),
       sum(r.value) / count(*) AS mean, count(*)
FROM readings r
JOIN sensors s ON s.id = r.sensor_id
WHERE s.code = %s
  AND r.time >= %s::date::timestamp AT TIME ZONE 'UTC'
  AND r.time < (%s::date + 1)::timestamp AT TIME ZONE 'UTC'
GROUP BY s.code, s.unit;
```

Los tres parámetros son código, fecha inicial y la misma fecha como final inclusiva.

| Sensor | Día UTC | Unidad | Mínimo | Máximo | Media | Lecturas | Esperadas |
|---|---|---|---|---|---|---|---|
| VAL-T01 | 2026-08-01 | °C | 18.6 | 27.46 | 23.013472222222234 | 288 | 288 |
| SAG-P02 | 2026-09-03 | bar | 6.11 | 7.27 | 6.66657142857143 | 210 | 288 |

Las pruebas fijan estas referencias; no vuelven a calcular las expectativas con la
función probada. La media se compara con tolerancia absoluta de 0.000001 y tolerancia
relativa cero. `Esperadas` es una referencia fija de la CLI, no una estadística de esta consulta.

El acceso a la base requirió salir del entorno aislado. Las referencias se obtuvieron
antes de ejecutar las pruebas del cálculo, aunque el módulo ya se había escrito mientras
se habilitaba dicho acceso.
