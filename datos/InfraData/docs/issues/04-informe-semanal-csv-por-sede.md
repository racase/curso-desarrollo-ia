---
title: Informe semanal CSV por sede
labels: [feature, datos]
depends_on: [1]
---

## Contexto
El resumen diario del issue 1 permitirá consultar los sensores, pero no proporcionará un informe semanal exportado. Cada responsable de sede necesita compartir un resumen de sus sensores en un fichero que pueda abrir en Excel en español.

## Historia de usuario
Como responsable de sede, quiero exportar a CSV el resumen semanal de mis sensores, para revisarlo y compartirlo en Excel en español.

## Alcance
- Exportar un CSV para una sede y una semana seleccionadas.
- Incluir el resumen de cada sensor de esa sede, coherente con el resumen diario del issue 1 y con sus unidades.
- Fijar en la especificación el formato verificable: columnas del fichero, separador, decimales y codificación.
- Permitir abrir el fichero correctamente en Excel en español.

## Fuera de alcance
- Otras sedes en el mismo informe, otros formatos y envío automático.
- Analíticas adicionales a las del resumen del issue 1.

## Criterios de aceptación
1. Dada una sede y una semana del conjunto fijo, cuando se exporta el informe, entonces incluye el resumen de sus sensores sin datos de otras sedes ni de otras semanas.
2. Dado el resumen del issue 1 para la misma sede y periodo, cuando se compara con el informe según la organización semanal especificada, entonces los resultados y las unidades coinciden.
3. Dado el contrato de formato aprobado, cuando se verifica el CSV exportado, entonces sus columnas, separador, decimales y codificación coinciden exactamente con ese contrato.
4. Dado el CSV exportado, cuando se abre en Excel en español, entonces los campos se separan correctamente, los números se reconocen como números y los nombres con tildes se muestran sin alteraciones.

## Notas
- Depende del issue 1.
- Excel en español espera punto y coma como separador y coma decimal; fijar también la codificación y verificar la apertura.
- Definir qué delimita una semana en UTC y cómo se organiza el resumen diario dentro del informe semanal, sin añadir métricas nuevas.
