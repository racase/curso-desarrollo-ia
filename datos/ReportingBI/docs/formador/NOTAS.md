# Notas del formador

Contiene las respuestas de los ejercicios: no lo enseñes en clase. Las cifras exactas están
en [`VALORES_ESPERADOS.md`](VALORES_ESPERADOS.md), generado junto con los datos.

## Situaciones sembradas en los datos

| Obra | Situación | Dónde se tiene que ver |
|---|---|---|
| OB-25003 Residencial Mirador del Parque | Sobrecoste de un 30 % en el capítulo 03 Estructura | Issue 01, matriz obra × capítulo |
| OB-25005 Urbanización Sector Norte UE-3 | Desde el sexto mes se certifica solo el 72 % de lo producido: unos 750.000 € pendientes de certificar | Issue 01, tabla por obra |
| OB-25002 Nave logística | Terminada con sobrecoste en todos los capítulos (≈ 15 %): margen real negativo | Issue 01, tabla por obra |
| OB-25006 Centro de salud Barrio Este | Avanza un 40 % más despacio de lo planificado: fin planificado pasado y avance real ≈ 80 % | Issue 02, curva S, hitos y ranking |
| OB-26003 Pasarela peatonal | En planificación: presupuesto sin movimientos ni avance | Issues 01 y 02, sin errores ni división por cero |
| OB-25004 Rehabilitación Calle Mayor 12 | Una línea de presupuesto («Revisión de precios») con fecha vacía `1753-01-01` | Issue 02, avance planificado |

## Trampas de NAV que el agente debería encontrar

- **Dos empresas.** `CONSTRUCTORA DEMO PRUEBAS` tiene una obra de prueba con movimientos. Si
  una vista no filtra la empresa (o une las dos), los totales no cuadran.
- **Signos.** Las certificaciones (`Entry Type` = 1) tienen `Line Amount (LCY)` negativo.
- **Opciones como enteros.** `Job.Status`: 0 Planificación, 2 Abierta, 3 Terminada.
  `Job Ledger Entry.Entry Type`: 0 Consumo, 1 Venta. `Job Planning Line.Line Type`: 0 Presupuesto,
  2 Presupuesto y facturable. `Job Task.Job Task Type`: 0 Registro, 3 Inicio total, 4 Fin total.
- **Capítulos que no son capítulos.** Las tareas 00 y 99 son de inicio y fin de total: no
  llevan movimientos y no deben salir como capítulos.
- **Costes indirectos.** El capítulo 10 solo tiene presupuesto de coste (`Line Type` = 0) y no
  se certifica: entra en el margen pero no en la producción.
- **Fechas vacías.** `1753-01-01` en `Job Milestone.Actual Date` (hito no cumplido) y en una
  línea de presupuesto.
- **Tablas a medida.** `Job Progress Entry` (avance medido, columna `Completion _`, que en NAV
  era `Completion %`) y `Job Milestone` (hitos). No son estándar.
- **Producción frente a certificado.** El certificado va un mes por detrás de la producción.
  Un «margen» calculado como certificado menos coste sale negativo en obras sanas en curso:
  es una buena discusión para corregir el proposal.
- **Desviación en obras abiertas.** Comparar el coste real con el presupuesto total hace
  que todas las obras en curso parezcan ahorrar. La desviación tiene que compararse con el
  coste presupuestado de lo producido (presupuesto × avance).

## Regenerar los datos

```bash
python tools/generate_seed.py
docker compose down -v && docker compose up -d
```

El generador es determinista; si cambias algo en él, revisa este documento y
`VALORES_ESPERADOS.md`.

## Antes de publicar el repositorio a los alumnos

Esta carpeta (`docs/formador/`) desvela las respuestas. Valora quitarla de la rama que se
entrega a los alumnos.
