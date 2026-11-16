# Tema visual del panel

El panel `/admin` adapta `DESIGN.md` mediante `resources/css/filament/admin/theme.css`, registrado en `AdminPanelProvider` y compilado con Vite. Abarca acceso, dashboard, incidencias, usuarios, sedes y activos. Conserva componentes, rutas, permisos, validaciones y el selector de tema de Filament.

## Decisiones de adaptación

- Claro: lienzo y superficies blancos, texto `#1f1633`, botones principales `#150f23` con blanco. Oscuro: lienzo `#1f1633`, superficies `#150f23`, texto blanco y botones principales blancos con texto violeta.
- La escala gris del panel se tiñe de violeta para alcanzar también tablas, menús, relaciones y estados vacíos. El violeta `#6a5fc1` se usa como color primario semántico para enlaces y selecciones; los botones principales usan la polaridad indicada arriba.
- Los hexadecimales del front matter prevalecen sobre sus equivalentes contradictorios de la prosa. El texto secundario claro se oscurece a `#655773`; en oscuro se conserva `#bdb8c0`. Los límites de campos usan `#79628c` y `#927da5` para mantener contraste. Los bordes de tarjetas son decorativos y conservan hairlines suaves.
- El foco es violeta `#422082` en claro y azul `#9dc1f5` en oscuro. Se mantienen los colores semánticos de peligro, error, aviso y éxito de Filament junto a sus etiquetas; la limitación de acentos comerciales no oculta el significado de las operaciones.
- El lima `#c2ef4e` se reserva al pequeño marcador de la marca; no aparece como fondo de botones ni texto de cuerpo. No se añaden mascotas, texturas ni páginas comerciales.
- Rubik variable, pesos 400–700, se empaqueta desde `@fontsource-variable/rubik` con `font-display: swap` y alternativas del sistema. No se solicitan fuentes remotas desde el panel. La licencia se conserva en `docs/licenses/rubik.txt`.
- Se usan tamaños funcionales: títulos 30px, cuerpo 16px/1.5, botones y secundarios 14px. Las mayúsculas de botones son visuales; las etiquetas originales permanecen en español. Campos, botones y tarjetas tienen radios de 6, 8 y 12px.
- Se conserva la navegación responsive y el desplazamiento local de tablas de Filament. Los controles tienen una altura mínima de 44px; se respeta movimiento reducido y el modo de colores forzados.

## Compilación y despliegue

Instalar dependencias PHP y ejecutar `npm ci` y `npm run build`. El tema necesita los CSS del paquete `filament/filament` en `vendor/`. Si PHP vive en un volumen Docker/Podman, copiar sus dependencias a `vendor/` antes de compilar con Node en el host, o compilar donde ambas dependencias estén disponibles. Desplegar conjuntamente código y `public/build/`.

El tema no requiere migraciones. Para revertir, restaurar el proveedor y las entradas Vite previas y recompilar los assets. Las comprobaciones usan el contenedor de pruebas y SQLite aislado; nunca la base PostgreSQL de desarrollo.

## Verificación

- `npm run build`: correcto; el tema y Rubik quedan incluidos en los assets locales. El navegador confirmó la carga de Rubik y ninguna petición a servicios externos de fuentes.
- `php vendor/bin/phpunit` en el contenedor aislado: 83 pruebas y 628 aserciones correctas.
- Laravel Pint para `AdminPanelProvider.php`: correcto.
- `openspec validate apply-design-system --strict`: correcto.
- Contraste de los tokens: texto principal y secundario por encima de 4.5:1; límites de campos y foco por encima de 3:1 en ambos modos. Esta medición verifica los pares de tokens, no constituye una auditoría completa de accesibilidad.
- Revisión automática en navegador: acceso, fuente local y dashboard claro y oscuro, incluida persistencia de la selección al recargar. El dashboard a 1440px no presenta desbordamiento horizontal. Capturas: [tema claro](images/panel-theme-light.png) y [tema oscuro](images/panel-theme-dark.png).
- El usuario confirmó expresamente que había validado todas las tareas y que estaban completas, incluida la revisión manual de las interacciones y adaptación descritas en OpenSpec. Las capturas automáticas anteriores corresponden a escritorio; la validación restante se registra como validación del usuario.
