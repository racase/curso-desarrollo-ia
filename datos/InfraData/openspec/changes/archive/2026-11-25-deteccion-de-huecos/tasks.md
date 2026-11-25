## 1. Referencias y casos controlados

- [x] 1.1 Convertir las tres filas de referencias.md en expectativas fijas de integración con umbral explícito de 15 minutos, sin obtener las expectativas llamando a la implementación.
- [x] 1.2 Verificar independientemente el conjunto inicial con umbral de 5 minutos y registrar su resultado antes de implementar; no deducirlo de la referencia de 15 minutos.
- [x] 1.3 Preparar casos aislados para igualdad con el umbral, una sola lectura ausente, extremos abiertos, sensores sin lecturas y huecos que atraviesan un rango vacío; revertir sus datos.

## 2. Consulta reutilizable

- [x] 2.1 Definir resultado tipado, estados, validación del umbral y resolución de fechas con los límites predeterminados del curso.
- [x] 2.2 Implementar detección SQL por sensor, contexto fuera del rango, selección por solapamiento y comparación estricta, mediante connect() y valores parametrizados.
- [x] 2.3 Resolver extremos abiertos y duraciones mínimas, incluyendo sensores del catálogo sin lecturas; recuperar solo resultados finales.

## 3. CLI

- [x] 3.1 Añadir huecos con --umbral-minutos, --sede, --sensor, --desde y --hasta; validar antes de conectar y conservar los comandos existentes.
- [x] 3.2 Presentar UTC, horas acumuladas, estados, extremos desconocidos, cotas mínimas, orden y mensaje vacío según el contrato; gestionar los códigos 0, 1 y 2.

## 4. Pruebas

- [x] 4.1 Cubrir argumentos, salida, errores, mensaje vacío y regresión de check y resumen-diario con monkeypatch y capsys.
- [x] 4.2 Marcar como integration las pruebas de las tres referencias, exhaustividad con ambos umbrales, filtros individuales y combinados y casos controlados.
- [x] 4.3 Verificar solapamiento y contactos exactos con los límites, duración completa frente a mínima, fechas parciales, rangos vacíos y sesiones UTC y Europe/Madrid.
- [x] 4.4 Conservar las verificaciones de datos iniciales e hypertables; aislar los datos sintéticos mediante rollback, sin alterar el conjunto permanente.

## 5. Documentación y validación

- [x] 5.1 Documentar en README.md el comando, umbral predeterminado, diferencia entre duración y lecturas ausentes, fechas UTC, límites del conjunto y tratamiento de extremos abiertos.
- [x] 5.2 Ejecutar uv run pytest -m "not integration", uv run ruff check . y uv run ruff format --check .; con base disponible, ejecutar uv run pytest -m integration y registrar resultados u omisiones.
- [x] 5.3 Ejecutar openspec validate deteccion-de-huecos --strict y comprobar que los escenarios de aceptación tienen verificación correspondiente.
