## 1. Consulta de huecos en PostgreSQL

- [x] 1.1 Añadir el resultado tipado y `reading_gaps` en `analytics.py`, con validación previa a conexión de fechas y umbral entero no negativo.
- [x] 1.2 Implementar en una consulta parametrizada la ventana global sin filtros, selección de sensores, timestamps distintos y huecos interiores descontando 5 minutos.
- [x] 1.3 Incorporar candidatos iniciales, finales y de sensores sin lecturas; recortar al rango efectivo, conservar indicadores de apertura y recorte, aplicar umbral estricto después del recorte y ordenar resultados en SQL.

## 2. Comando y presentación

- [x] 2.1 Añadir `gaps` a la CLI con sede, sensor, fechas inclusivas UTC y `--threshold-minutes` entero no negativo con valor por defecto 15; mantener los comandos existentes.
- [x] 2.2 Presentar las nueve columnas tabuladas de la spec, tiempos UTC, duración con tres decimales e indicadores `si`/`no`; gestionar salida vacía y errores con códigos 0/1/2 sin tabla parcial.
- [x] 2.3 Añadir pruebas unitarias de argumentos, validación antes de conectar, propagación de filtros, ayuda, presentación y errores PostgreSQL.

## 3. Verificación de datos y extremos

- [x] 3.1 Iniciar PostgreSQL antes de validar SQL y añadir pruebas marcadas `integration` del seed: exactamente tres huecos, duraciones 50/390/3240, timestamps completos, filtros y umbral igual a la duración.
- [x] 3.2 Añadir pruebas del seed para PAT-L01 sin lecturas dentro del rango: 2880 minutos para el 12–13 de octubre y 360 para el día 14; verificar indicadores y aplicación del umbral tras el recorte.
- [x] 3.3 Añadir casos de integración transaccionales con rollback para huecos de 15 y 20 minutos, umbral cero, duplicados, extremos abiertos inicial y final y sensor existente sin lecturas; verificar también un hueco recortado por ambos lados y apertura conservada tras recorte.
- [x] 3.4 Verificar ventana global independiente de filtros, límites únicos, rango exterior, base sin lecturas, lectura de cierre en el fin exclusivo, ausencia de falso hueco final y resultados invariantes con sesión `Europe/Madrid`.

## 4. Documentación y comprobaciones finales

- [x] 4.1 Documentar `gaps` en README y completar los criterios de issue02 con referencias exactas, cadencia, umbral estricto, recortes, indicadores, ventana inferida y supuesto de actividad común.
- [x] 4.2 Ejecutar `uv run pytest` con PostgreSQL disponible y comprobar que los casos de integración se ejecutan; corregir fallos y conservar compatibilidad de `check` y `daily-summary`.
- [x] 4.3 Ejecutar `uv run ruff check .` y `uv run ruff format --check .`; revisar que no se hayan añadido dependencias ni cambios de esquema o seed.
