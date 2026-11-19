---
title: Clasificación automática de incidencias con OpenRouter
labels: [feature, web]
depends_on: [1]
---

## Resumen

Al guardar una nueva incidencia, el backend solicita a un LLM mediante OpenRouter una propuesta de categoría y urgencia basada en la descripción. La respuesta debe ser JSON validado contra un esquema. Se permiten dos reintentos tras el primer fallo; si los tres intentos fallan, la incidencia se conserva como «Sin clasificar». El técnico puede corregir la propuesta desde Filament.

## Problema y oportunidad

La clasificación manual exige interpretar cada descripción y puede producir criterios inconsistentes. Una propuesta automática reduce ese trabajo inicial y mantiene la revisión humana durante la atención.

En la implementación actual existe `priority`, con los valores `low`, `medium`, `high` y `critical`, pero no existe una categoría. Este issue incorpora la categoría y utiliza la prioridad existente para representar la urgencia, evitando dos campos con el mismo significado.

## Objetivos

- Proponer categoría y urgencia automáticamente durante el alta.
- Aceptar únicamente resultados completos y válidos.
- Permitir registrar incidencias aunque OpenRouter no responda correctamente.
- Mantener la corrección manual por parte del personal técnico.
- Conservar la API Key exclusivamente en el backend.

## Usuarios afectados

- Solicitantes: describir el problema sin tener que elegir su clasificación.
- Técnicos y administradores: revisar y corregir la categoría y la urgencia en Filament conforme a sus permisos.

## Alcance

- Incorporación de la categoría al modelo y a las vistas pertinentes de Filament.
- Clasificación automática en el flujo de creación del backend, también cuando se invoque fuera del formulario de Filament.
- Integración con OpenRouter con el modelo configurado como `gpt-oss-120b`.
- Validación local mediante JSON Schema, límites de tiempo y dos reintentos.
- Persistencia y presentación del resultado o del estado «Sin clasificar».
- Edición manual de categoría y urgencia desde Filament.

## Fuera de alcance

- Reclasificación automática de incidencias existentes o al editar la descripción.
- Asignación automática a técnicos y cambios en el estado de atención.
- Entrenamiento de modelos, búsqueda documental y adjuntos como entrada del LLM.
- Gestión de credenciales desde el navegador o el panel de Filament.

## Requisitos de producto

### Alta y propuesta

- La descripción es la entrada para clasificar. El alta no exige seleccionar manualmente categoría o urgencia.
- El backend valida los datos y autoriza el alta antes de enviar información al proveedor.
- Una respuesta válida establece ambos valores como propuesta revisable.
- La urgencia se persiste en `priority` y conserva sus valores y etiquetas existentes: baja, media, alta y crítica. El formulario debe explicar su significado como urgencia.
- Como catálogo inicial propuesto, las categorías son hardware, software, red, accesos y otros. Confirmar este catálogo antes de implementar y mantener una única definición compartida por validación, esquema y opciones del formulario.
- «Otros» es una categoría válida; no equivale a un fallo de clasificación.
- Guardar o reintentar no debe duplicar la incidencia.

### Contrato JSON

El contenido generado debe ser un único objeto JSON que cumpla el siguiente esquema propuesto. Las claves y valores internos se mantienen en inglés y las etiquetas de interfaz se muestran en español.

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "properties": {
    "category": {
      "type": "string",
      "enum": ["hardware", "software", "network", "access", "other"]
    },
    "urgency": {
      "type": "string",
      "enum": ["low", "medium", "high", "critical"]
    }
  },
  "required": ["category", "urgency"],
  "additionalProperties": false
}
```

- El backend decodifica y valida el contenido contra el esquema antes de persistirlo, aunque el proveedor ofrezca salida estructurada.
- JSON mal formado, texto adicional, claves ausentes, valores nulos, tipos incorrectos, propiedades extra o valores fuera del catálogo se consideran fallos del intento.
- No se admiten resultados parciales ni se sustituyen valores inválidos por valores predeterminados que aparenten una clasificación correcta.
- La descripción se trata como contenido del usuario; las instrucciones incluidas en ella no pueden alterar el contrato ni el catálogo permitido.

### Fallos y reintentos

- Se realiza un intento inicial y, si falla, hasta dos reintentos adicionales: máximo tres llamadas por alta.
- Se consideran fallos los errores de conexión, los tiempos de espera agotados, las respuestas HTTP de error, las respuestas vacías y los incumplimientos del esquema.
- Se establecen tiempos de espera finitos y una espera breve y acotada entre intentos. Los valores se documentan para que el tiempo máximo del alta sea previsible.
- Si un intento tiene éxito, no se realizan más llamadas.
- Si fallan los tres intentos, el alta se completa y la incidencia queda «Sin clasificar», con categoría y urgencia sin propuesta. No se asigna automáticamente urgencia media para ocultar el fallo.
- «Sin clasificar» describe la clasificación y es independiente del estado de atención existente. El fallo del LLM no cambia el estado de atención.
- La ausencia de configuración válida permite guardar la incidencia como «Sin clasificar» y registra el motivo técnico sin realizar llamadas inviables.
- El diseño de persistencia debe evitar mantener una transacción de base de datos abierta durante las llamadas y sus esperas.

### Revisión en Filament

- La ficha muestra categoría, urgencia y si la incidencia está «Sin clasificar».
- Los técnicos y administradores con permiso de edición pueden corregir ambos valores, incluida una incidencia cuyo análisis haya fallado.
- Una clasificación manual completa elimina la indicación «Sin clasificar»; no se admite dejar una clasificación parcial.
- Los solicitantes no pueden modificar estos campos mediante peticiones manipuladas.
- La corrección manual y las ediciones posteriores no desencadenan nuevas llamadas ni sobrescriben la decisión del técnico.
- Las incidencias anteriores siguen siendo consultables; la migración no inventa categorías ni provoca llamadas masivas al LLM. Debe definirse cómo representar las que ya tienen prioridad pero carecen de categoría.

### Configuración y seguridad

- La API Key se obtiene de la configuración del servidor y nunca se incluye en JavaScript, HTML, respuestas al navegador, mensajes de error o logs.
- Documentar las variables necesarias para OpenRouter, modelo y tiempos de espera en `.env.example` sin secretos reales. No leer ni modificar archivos `.env` como parte de la implementación.
- El modelo configurado debe ser `gpt-oss-120b`. Verificar antes de implementar el identificador que OpenRouter acepta para ese modelo y documentar cualquier correspondencia necesaria sin sustituirlo silenciosamente.
- Enviar al proveedor únicamente la descripción y las instrucciones y opciones necesarias para clasificar; excluir datos de usuarios, comentarios y otros campos ajenos al objetivo.
- Registrar el resultado de cada intento y el fallo definitivo con un identificador de correlación, sin guardar credenciales, descripciones completas ni respuestas completas del proveedor en logs.

## Criterios de aceptación y validación

- [ ] Al crear una incidencia con una respuesta válida, se guardan la categoría y la urgencia propuestas y se muestran en Filament.
- [ ] El JSON se valida contra el esquema y `urgency` se traduce correctamente a `priority`.
- [ ] Las respuestas mal formadas o contrarias al esquema se rechazan sin persistir datos parciales.
- [ ] Un éxito en el segundo o tercer intento detiene los reintentos y guarda la propuesta.
- [ ] Tres fallos consecutivos producen exactamente tres llamadas y una única incidencia «Sin clasificar», sin alterar su estado de atención.
- [ ] La falta de configuración permite registrar la incidencia y deja un diagnóstico sin secretos.
- [ ] Un técnico autorizado puede corregir una propuesta o clasificar manualmente una incidencia fallida; la corrección se conserva tras nuevas ediciones.
- [ ] Un solicitante no puede cambiar la clasificación mediante una petición manipulada.
- [ ] Las incidencias existentes y las nuevas sin clasificar se pueden consultar y editar sin errores por valores ausentes.
- [ ] Las pruebas automatizadas simulan OpenRouter y cubren éxito, esquema inválido, errores HTTP, tiempo de espera, agotamiento de reintentos y permisos, sin llamadas reales ni API Key.
- [ ] La clave no aparece en los recursos enviados al navegador ni en los logs de los casos probados.

## Decisiones pendientes antes de implementar

- Confirmar el catálogo inicial de categorías propuesto.
- Verificar el identificador de OpenRouter y la disponibilidad de salida estructurada para el modelo indicado. La validación local del esquema es obligatoria en cualquier caso.
- Concretar los tiempos de espera por intento y entre reintentos.
- Definir la representación persistente de la clasificación y la transición de incidencias existentes que ya tienen prioridad, manteniendo separado el estado de atención.
