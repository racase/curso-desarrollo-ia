---
title: Informe semanal CSV por sede
labels: [feature, datos]
depends_on: [1]
---

## Contexto
InfraData todavía no genera informes. Una vez disponible el resumen diario del issue 1, cada responsable de sede necesita un fichero semanal que pueda revisar en Excel en español.

## Historia de usuario
Como responsable de sede, quiero exportar el resumen semanal de los sensores de mi sede a CSV, para revisar y compartir sus resultados en Excel en español.

## Alcance
- Exportar un CSV para una sede y una semana, con el resumen de cada uno de sus sensores a partir del issue 1.
- Incluir los valores del resumen y las unidades correspondientes, con periodos delimitados en UTC.
- Fijar un formato verificable: contenido y orden de columnas, separador, representación decimal y codificación.
- Apertura correcta del fichero en Excel en español.

## Fuera de alcance
- Otras exportaciones, gráficos, API o envío automático del informe.
- Detección de huecos y anomalías.

## Criterios de aceptación
1. Dado el conjunto fijo y una sede y semana seleccionadas, cuando se exporta el informe, entonces contiene el resumen de todos sus sensores y no incluye sensores de otras sedes ni lecturas ajenas al periodo.
2. Dados los resúmenes del issue 1 para el mismo periodo, cuando se compara el CSV con la regla semanal acordada, entonces los valores coinciden con la referencia: <valor a obtener del conjunto de datos>.
3. Dado el contrato de formato fijado en la especificación, cuando se verifica el CSV generado, entonces cumple el contenido y orden de columnas, separador, decimales y codificación acordados.
4. Dado el CSV generado con los datos del curso, cuando se abre en Excel en español según el procedimiento acordado, entonces los campos quedan separados correctamente y los números y caracteres se interpretan sin correcciones manuales.

## Notas
- Excel en español espera punto y coma como separador y coma decimal; fijar también la codificación y la precisión.
- Decidir el inicio y fin de semana, el tratamiento de semanas incompletas y si el informe conserva el detalle diario o consolida la semana. Si se consolida, acordar cómo se pondera la media con días incompletos.
