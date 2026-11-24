# Spec Delta

## Purpose

Base común de los informes de obra sobre el ERP: de dónde salen los datos, qué obras y
capítulos hay, sus atributos, el calendario, la fecha de corte y los filtros que comparten
todos los informes.

## ADDED Requirements

### Requirement: Conexión parametrizada al ERP
El modelo MUST leer el servidor y la base de datos de dos parámetros, `Servidor` y `BaseDatos`, y no de valores escritos en cada consulta. Ningún fichero del proyecto MUST contener credenciales.

#### Scenario: Cambio de servidor
- **WHEN** se cambia el valor del parámetro `Servidor` y se actualiza el modelo
- **THEN** todas las tablas se cargan desde el nuevo servidor sin editar ninguna consulta

#### Scenario: Sin credenciales en el repositorio
- **WHEN** se buscan contraseñas o cadenas de conexión con usuario en los ficheros del proyecto
- **THEN** no aparece ninguna

### Requirement: Solo la empresa real
El modelo MUST mostrar únicamente los datos de la empresa `CONSTRUCTORA DEMO`. Los datos de cualquier otra empresa de la base, como `CONSTRUCTORA DEMO PRUEBAS`, MUST quedar fuera.

#### Scenario: Empresa de pruebas excluida
- **WHEN** se abre el informe sin filtros
- **THEN** no aparece ninguna obra ni importe de la empresa `CONSTRUCTORA DEMO PRUEBAS`

### Requirement: Obras y sus atributos
Cada obra MUST mostrarse con su código, descripción, cliente, delegación, tipo de obra, jefe de obra, estado y fechas de inicio y fin. Delegación, tipo y jefe de obra MUST mostrarse por su nombre, no por su código. Una fecha vacía en el ERP MUST mostrarse en blanco.

#### Scenario: Atributos con nombre de negocio
- **WHEN** se mira cualquier obra en el informe
- **THEN** su delegación aparece como «Delegación Norte», «Delegación Centro» o «Delegación Sur», su tipo como nombre (por ejemplo, «Edificación») y su jefe de obra con nombre y apellido

#### Scenario: Todas las obras presentes
- **WHEN** se abre el informe sin filtros
- **THEN** aparecen todas las obras de la empresa, también las que no tienen movimientos

### Requirement: Estado de la obra
El estado MUST mostrarse con su nombre: Planificación, Oferta, Abierta o Terminada.

#### Scenario: Obra terminada
- **WHEN** una obra está cerrada en el ERP
- **THEN** su estado en el informe es «Terminada»

### Requirement: Capítulos de imputación
Los indicadores por capítulo MUST usar solo los capítulos en los que se imputan presupuesto y costes. Los capítulos totalizadores del ERP (cabecera y total de obra) MUST quedar fuera, para que ningún importe se cuente dos veces.

#### Scenario: Sin doble conteo
- **WHEN** se suma un indicador de todos los capítulos de una obra
- **THEN** el resultado es igual al indicador de la obra

### Requirement: Calendario
El modelo MUST tener una tabla de calendario propia, marcada como tabla de fechas, que cubra todos los meses con presupuesto, movimientos o mediciones. El modelo MUST NOT usar las tablas de fechas automáticas de Power BI.

#### Scenario: Filtro por mes
- **WHEN** se filtra el informe por un año o un mes del calendario
- **THEN** los movimientos y la producción se filtran por esa fecha

### Requirement: Fecha de corte
La fecha de corte de los datos MUST ser la fecha del último movimiento registrado en el ERP y MUST verse en todas las páginas del informe.

#### Scenario: Fecha de corte visible
- **WHEN** se abre cualquier página del informe
- **THEN** se ve la fecha de corte, igual a la fecha del último movimiento de la empresa

### Requirement: Filtros compartidos
Todas las páginas MUST ofrecer filtros por delegación, tipo de obra, jefe de obra y estado de la obra, y cada filtro MUST aplicarse a todos los visuales de la página.

#### Scenario: Filtro coherente
- **WHEN** se elige una delegación
- **THEN** las tarjetas, las tablas y los gráficos de la página muestran solo las obras de esa delegación y sus totales cuadran entre sí

### Requirement: Formato español
Importes y porcentajes MUST mostrarse en formato español: miles con punto, decimales con coma, importes en euros (`#.##0,00 €`) y porcentajes con un decimal (`0,0 %`).

#### Scenario: Formato de un importe
- **WHEN** se muestra un importe de un millón doscientos mil euros con cincuenta céntimos
- **THEN** aparece como «1.200.000,50 €»
