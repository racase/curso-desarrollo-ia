"""Genera la base de datos NAVDEMO: un Navision (NAV clásico) simulado de una constructora ficticia.

Uso (desde datos/ReportingBI/):

    python tools/generate_seed.py

Escribe:
    db/init/01_schema.sql              esquema con el aspecto de una base de NAV
    db/init/02_seed.sql                datos
    docs/formador/VALORES_ESPERADOS.md cifras de control para el formador

Es determinista: con la misma semilla genera exactamente los mismos ficheros.
Solo usa la biblioteca estándar de Python (3.10 o superior).
"""

from __future__ import annotations

import calendar
import random
from dataclasses import dataclass, field
from datetime import date, timedelta
from decimal import ROUND_HALF_UP, Decimal
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SEED = "navdemo-2026"
DATABASE = "NAVDEMO"
COMPANY = "CONSTRUCTORA DEMO"
TEST_COMPANY = "CONSTRUCTORA DEMO PRUEBAS"
CUTOFF = date(2026, 10, 31)  # último día con datos
BLANK_DATE = date(1753, 1, 1)  # «fecha vacía» en NAV
INDIRECT_SHARE = 0.06  # costes indirectos sobre el presupuesto de venta

DEC = "decimal(38,20)"

# ---------------------------------------------------------------------------
# Esquema: tablas de NAV (nombres de columna tal como los deja NAV en SQL Server:
# puntos y % se convierten en "_", todo NOT NULL, opciones como int).
# ---------------------------------------------------------------------------

TABLES: dict[str, tuple[list[tuple[str, str]], list[str]]] = {
    "Customer": (
        [
            ("No_", "nvarchar(20)"),
            ("Name", "nvarchar(50)"),
            ("Search Name", "nvarchar(50)"),
            ("City", "nvarchar(30)"),
            ("VAT Registration No_", "nvarchar(20)"),
            ("Blocked", "int"),
        ],
        ["No_"],
    ),
    "Resource": (
        [
            ("No_", "nvarchar(20)"),
            ("Type", "int"),
            ("Name", "nvarchar(50)"),
            ("Base Unit of Measure", "nvarchar(10)"),
            ("Unit Cost", DEC),
            ("Blocked", "tinyint"),
        ],
        ["No_"],
    ),
    "Dimension Value": (
        [
            ("Dimension Code", "nvarchar(20)"),
            ("Code", "nvarchar(20)"),
            ("Name", "nvarchar(50)"),
            ("Dimension Value Type", "int"),
            ("Blocked", "tinyint"),
        ],
        ["Dimension Code", "Code"],
    ),
    "Job": (
        [
            ("No_", "nvarchar(20)"),
            ("Search Description", "nvarchar(50)"),
            ("Description", "nvarchar(50)"),
            ("Description 2", "nvarchar(50)"),
            ("Bill-to Customer No_", "nvarchar(20)"),
            ("Creation Date", "datetime"),
            ("Starting Date", "datetime"),
            ("Ending Date", "datetime"),
            ("Status", "int"),
            ("Person Responsible", "nvarchar(20)"),
            ("Global Dimension 1 Code", "nvarchar(20)"),
            ("Global Dimension 2 Code", "nvarchar(20)"),
            ("Job Posting Group", "nvarchar(20)"),
            ("Blocked", "int"),
        ],
        ["No_"],
    ),
    "Job Task": (
        [
            ("Job No_", "nvarchar(20)"),
            ("Job Task No_", "nvarchar(20)"),
            ("Description", "nvarchar(50)"),
            ("Job Task Type", "int"),
            ("Indentation", "int"),
            ("Totaling", "nvarchar(250)"),
        ],
        ["Job No_", "Job Task No_"],
    ),
    "Job Planning Line": (
        [
            ("Job No_", "nvarchar(20)"),
            ("Job Task No_", "nvarchar(20)"),
            ("Line No_", "int"),
            ("Line Type", "int"),
            ("Planning Date", "datetime"),
            ("Document No_", "nvarchar(20)"),
            ("Type", "int"),
            ("No_", "nvarchar(20)"),
            ("Description", "nvarchar(50)"),
            ("Quantity", DEC),
            ("Unit of Measure Code", "nvarchar(10)"),
            ("Unit Cost (LCY)", DEC),
            ("Total Cost (LCY)", DEC),
            ("Unit Price (LCY)", DEC),
            ("Line Amount (LCY)", DEC),
        ],
        ["Job No_", "Job Task No_", "Line No_"],
    ),
    "Job Ledger Entry": (
        [
            ("Entry No_", "int"),
            ("Job No_", "nvarchar(20)"),
            ("Posting Date", "datetime"),
            ("Document No_", "nvarchar(20)"),
            ("Type", "int"),
            ("No_", "nvarchar(20)"),
            ("Description", "nvarchar(50)"),
            ("Quantity", DEC),
            ("Unit Cost (LCY)", DEC),
            ("Total Cost (LCY)", DEC),
            ("Unit Price (LCY)", DEC),
            ("Total Price (LCY)", DEC),
            ("Line Amount (LCY)", DEC),
            ("Job Task No_", "nvarchar(20)"),
            ("Entry Type", "int"),
            ("Global Dimension 1 Code", "nvarchar(20)"),
            ("Global Dimension 2 Code", "nvarchar(20)"),
            ("Unit of Measure Code", "nvarchar(10)"),
        ],
        ["Entry No_"],
    ),
    # Tablas a medida del partner (rango 50000).
    "Job Progress Entry": (
        [
            ("Entry No_", "int"),
            ("Job No_", "nvarchar(20)"),
            ("Job Task No_", "nvarchar(20)"),
            ("Measurement Date", "datetime"),
            ("Completion _", DEC),
            ("Measured By", "nvarchar(20)"),
        ],
        ["Entry No_"],
    ),
    "Job Milestone": (
        [
            ("Job No_", "nvarchar(20)"),
            ("Code", "nvarchar(10)"),
            ("Sequence", "int"),
            ("Description", "nvarchar(50)"),
            ("Planned Date", "datetime"),
            ("Actual Date", "datetime"),
        ],
        ["Job No_", "Code"],
    ),
}

# ---------------------------------------------------------------------------
# Datos maestros ficticios
# ---------------------------------------------------------------------------

DIMENSIONS = [
    ("DELEGACION", "NORTE", "Delegación Norte"),
    ("DELEGACION", "CENTRO", "Delegación Centro"),
    ("DELEGACION", "SUR", "Delegación Sur"),
    ("TIPOOBRA", "EDIF", "Edificación"),
    ("TIPOOBRA", "REHAB", "Rehabilitación"),
    ("TIPOOBRA", "URB", "Urbanización"),
    ("TIPOOBRA", "IND", "Industrial"),
    ("TIPOOBRA", "CIVIL", "Obra civil"),
]

CUSTOMERS = [
    ("C00010", "Promociones Almendros S.L.", "Villanueva", "B00000010"),
    ("C00020", "Logística Demo S.A.", "Puerto Nuevo", "A00000020"),
    ("C00030", "Inmobiliaria Mirador S.L.", "Villanueva", "B00000030"),
    ("C00040", "Comunidad de Propietarios Calle Mayor 12", "Villanueva", "H00000040"),
    ("C00050", "Ayuntamiento de Villanueva", "Villanueva", "P00000050"),
    ("C00060", "Servicio Regional de Salud", "Ciudad Capital", "Q00000060"),
    ("C00070", "Consejería de Educación", "Ciudad Capital", "S00000070"),
    ("C00080", "Oficinas Centrales Demo S.A.", "Puerto Nuevo", "A00000080"),
]

# (No_, Type 0=Persona 1=Máquina, Nombre, Unidad, Coste unitario)
RESOURCES = [
    ("JO01", 0, "Laura Martín (jefa de obra)", "HORA", 42.00),
    ("JO02", 0, "Javier Ortega (jefe de obra)", "HORA", 42.00),
    ("JO03", 0, "Carmen Ruiz (jefa de obra)", "HORA", 42.00),
    ("JO04", 0, "Andrés Molina (jefe de obra)", "HORA", 42.00),
    ("OF1", 0, "Oficial de primera", "HORA", 28.50),
    ("PEON", 0, "Peón", "HORA", 22.00),
    ("EXC", 1, "Excavadora de cadenas", "HORA", 65.00),
    ("GRUA", 1, "Grúa torre", "HORA", 48.00),
]
RESOURCE_COST = {r[0]: r[4] for r in RESOURCES}

# (código, descripción, inicio, fin) como fracción del plazo de la obra
CHAPTERS = [
    ("01", "Movimiento de tierras", 0.00, 0.12),
    ("02", "Cimentación", 0.06, 0.22),
    ("03", "Estructura", 0.15, 0.48),
    ("04", "Albañilería y cerramientos", 0.35, 0.72),
    ("05", "Instalaciones", 0.45, 0.90),
    ("06", "Acabados", 0.62, 1.00),
    ("07", "Urbanización exterior", 0.80, 1.00),
    ("08", "Seguridad y salud", 0.00, 1.00),
    ("09", "Gestión de residuos", 0.00, 1.00),
]
INDIRECT_TASK = ("10", "Costes indirectos")

WEIGHTS = {
    "EDIF": [4, 10, 24, 18, 20, 16, 4, 2, 2],
    "REHAB": [1, 4, 14, 22, 24, 30, 1, 2, 2],
    "IND": [8, 14, 32, 14, 16, 10, 2, 2, 2],
    "URB": [30, 6, 4, 4, 22, 6, 24, 2, 2],
    "CIVIL": [30, 20, 30, 2, 8, 2, 4, 2, 2],
}

MILESTONES = [
    ("HIT01", 1, "Acta de replanteo", None),
    ("HIT02", 2, "Fin de cimentación", "02"),
    ("HIT03", 3, "Fin de estructura", "03"),
    ("HIT04", 4, "Fin de cerramientos", "04"),
    ("HIT05", 5, "Recepción de obra", "ALL"),
]


@dataclass
class JobDef:
    no: str
    description: str
    customer: str
    delegation: str
    kind: str
    manager: str
    start: date  # primer día del mes de inicio
    months: int  # plazo planificado en meses
    sale: float  # presupuesto de venta (sin indirectos)
    margin: float  # margen previsto sobre la venta
    delay: float = 1.0  # >1: la obra avanza más despacio de lo planificado
    overrun: dict[str, float] = field(default_factory=dict)  # sobrecoste por capítulo
    overrun_all: float = 1.0
    cert_ratio: float = 1.0  # <1: se certifica menos de lo producido
    cert_ratio_from: int = 0  # mes desde el que aplica cert_ratio
    planning_only: bool = False
    extra_planning_line: tuple[str, str, float, float] | None = None  # sin fecha


JOBS = [
    JobDef("OB-25001", "Residencial Los Almendros (48 viv.)", "C00010", "NORTE", "EDIF",
           "JO01", date(2025, 1, 1), 20, 6_800_000, 0.11, delay=1.03),
    JobDef("OB-25002", "Nave logística Parque Empresarial Sur", "C00020", "SUR", "IND",
           "JO02", date(2025, 2, 1), 12, 2_400_000, 0.08, overrun_all=1.15),
    JobDef("OB-25003", "Residencial Mirador del Parque (32 viv.)", "C00030", "CENTRO", "EDIF",
           "JO03", date(2025, 7, 1), 19, 5_100_000, 0.12, overrun={"03": 1.32}),
    JobDef("OB-25004", "Rehabilitación edificio Calle Mayor 12", "C00040", "CENTRO", "REHAB",
           "JO03", date(2025, 4, 1), 14, 1_600_000, 0.13,
           extra_planning_line=("06", "Revisión de precios (fecha pendiente)", 28_000, 35_000)),
    JobDef("OB-25005", "Urbanización Sector Norte UE-3", "C00050", "NORTE", "URB",
           "JO01", date(2025, 9, 1), 16, 3_900_000, 0.10, cert_ratio=0.72, cert_ratio_from=6),
    JobDef("OB-25006", "Centro de salud Barrio Este", "C00060", "SUR", "EDIF",
           "JO04", date(2025, 6, 1), 16, 4_200_000, 0.10, delay=1.40),
    JobDef("OB-25007", "Colector de pluviales Avenida del Puerto", "C00050", "NORTE", "CIVIL",
           "JO02", date(2025, 9, 1), 10, 1_200_000, 0.09),
    JobDef("OB-26001", "Ampliación instituto de secundaria", "C00070", "CENTRO", "EDIF",
           "JO04", date(2026, 1, 1), 14, 3_200_000, 0.10),
    JobDef("OB-26002", "Oficinas Avenida Central", "C00080", "SUR", "EDIF",
           "JO02", date(2026, 3, 1), 18, 4_400_000, 0.12, delay=1.02),
    JobDef("OB-26003", "Pasarela peatonal sobre el río", "C00050", "NORTE", "CIVIL",
           "JO01", date(2026, 12, 1), 8, 900_000, 0.11, planning_only=True),
]

# ---------------------------------------------------------------------------
# Utilidades
# ---------------------------------------------------------------------------


def money(x: float) -> Decimal:
    return Decimal(str(x)).quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)


def month_end(start: date, k: int) -> date:
    """Último día del mes k (k=1 es el mes de inicio)."""
    y = start.year + (start.month - 1 + k - 1) // 12
    m = (start.month - 1 + k - 1) % 12 + 1
    return date(y, m, calendar.monthrange(y, m)[1])


def day_in_month(d: date, day: int) -> date:
    return date(d.year, d.month, min(day, calendar.monthrange(d.year, d.month)[1]))


def smoothstep(x: float) -> float:
    x = max(0.0, min(1.0, x))
    return x * x * (3 - 2 * x)


def planned_progress(t: float, a: float, b: float) -> float:
    return smoothstep((t - a) / (b - a))


def lit(v: object) -> str:
    if isinstance(v, str):
        return "N'" + v.replace("'", "''") + "'"
    if isinstance(v, date):
        return "'" + v.strftime("%Y%m%d") + "'"
    if isinstance(v, (int, Decimal)):
        return str(v)
    if isinstance(v, float):
        return str(money(v))
    raise TypeError(f"Tipo no soportado: {type(v)!r}")


def split_amount(total: Decimal, shares: list[float]) -> list[Decimal]:
    """Reparte un importe en partes que suman exactamente el total."""
    parts = [money(float(total) * s) for s in shares[:-1]]
    parts.append(total - sum(parts, Decimal("0")))
    return parts


# ---------------------------------------------------------------------------
# Simulación
# ---------------------------------------------------------------------------


@dataclass
class Rows:
    tables: dict[str, list[tuple]] = field(default_factory=dict)

    def add(self, table: str, row: tuple) -> None:
        self.tables.setdefault(table, []).append(row)


@dataclass
class JobResult:
    job: JobDef
    status: int
    budget_cost: Decimal = Decimal("0")
    budget_sale: Decimal = Decimal("0")
    planned_sale_to_cutoff: Decimal = Decimal("0")
    actual_cost: Decimal = Decimal("0")
    certified: Decimal = Decimal("0")
    production: Decimal = Decimal("0")
    chapter_cost_budget: dict[str, Decimal] = field(default_factory=dict)
    chapter_cost_actual: dict[str, Decimal] = field(default_factory=dict)
    milestones_late: int = 0
    max_delay_days: int = 0
    finished_on: date | None = None


def simulate(rows: Rows) -> list[JobResult]:
    results = []
    entry_no = 0
    progress_entry_no = 0

    for job in JOBS:
        rng = random.Random(f"{SEED}-{job.no}")
        weights = WEIGHTS[job.kind]
        total_w = sum(weights)
        indirect_budget = money(job.sale * INDIRECT_SHARE)
        direct_cost_total = job.sale * (1 - job.margin) - float(indirect_budget)
        ch_sale = {c[0]: money(job.sale * w / total_w) for c, w in zip(CHAPTERS, weights)}
        ch_cost = {c[0]: money(direct_cost_total * w / total_w) for c, w in zip(CHAPTERS, weights)}
        res = JobResult(job, status=2)
        res.chapter_cost_budget = dict(ch_cost)
        res.chapter_cost_budget["10"] = indirect_budget

        # --- Tareas (capítulos)
        rows.add("Job Task", (job.no, "00", "PRESUPUESTO DE EJECUCIÓN", 3, 0, ""))
        for code, desc, _a, _b in CHAPTERS:
            rows.add("Job Task", (job.no, code, desc, 0, 1, ""))
        rows.add("Job Task", (job.no, INDIRECT_TASK[0], INDIRECT_TASK[1], 0, 1, ""))
        rows.add("Job Task", (job.no, "99", "TOTAL OBRA", 4, 0, "01..10"))

        # --- Presupuesto planificado (Job Planning Line), repartido por meses
        line_no = 0
        for code, desc, a, b in CHAPTERS:
            incs = []
            for k in range(1, job.months + 1):
                inc = planned_progress(k / job.months, a, b) - planned_progress((k - 1) / job.months, a, b)
                if inc > 1e-6:
                    incs.append((k, inc))
            total_inc = sum(i for _, i in incs)
            shares = [i / total_inc for _, i in incs]
            costs = split_amount(ch_cost[code], shares)
            sales = split_amount(ch_sale[code], shares)
            for (k, _), cost, sale in zip(incs, costs, sales):
                line_no += 10000
                pdate = month_end(job.start, k)
                rows.add("Job Planning Line", (
                    job.no, code, line_no, 2, pdate, f"PRES-{job.no}", 2, "7000000",
                    f"{desc} - {pdate:%m/%Y}", Decimal("1"), "UD", cost, cost, sale, sale,
                ))
                if pdate <= CUTOFF:
                    res.planned_sale_to_cutoff += sale
        # Costes indirectos: solo presupuesto de coste (Line Type 0 = Budget), no facturable
        ind_parts = split_amount(indirect_budget, [1 / job.months] * job.months)
        for k, cost in enumerate(ind_parts, start=1):
            line_no += 10000
            pdate = month_end(job.start, k)
            rows.add("Job Planning Line", (
                job.no, "10", line_no, 0, pdate, f"PRES-{job.no}", 2, "6290000",
                f"Costes indirectos - {pdate:%m/%Y}", Decimal("1"), "UD", cost, cost,
                Decimal("0"), Decimal("0"),
            ))
        if job.extra_planning_line:
            code, desc, cost, sale = job.extra_planning_line
            line_no += 10000
            rows.add("Job Planning Line", (
                job.no, code, line_no, 2, BLANK_DATE, f"PRES-{job.no}", 2, "7000000",
                desc, Decimal("1"), "UD", money(cost), money(cost), money(sale), money(sale),
            ))
            res.chapter_cost_budget[code] += money(cost)
            ch_sale_extra = money(sale)
        else:
            ch_sale_extra = Decimal("0")

        res.budget_cost = sum(res.chapter_cost_budget.values(), Decimal("0"))
        res.budget_sale = sum(ch_sale.values(), Decimal("0")) + ch_sale_extra

        # --- Hitos: fechas planificadas
        def planned_finish(chapter: str | None) -> date:
            if chapter is None:
                return job.start
            if chapter == "ALL":
                return month_end(job.start, job.months)
            b = next(c[3] for c in CHAPTERS if c[0] == chapter)
            k = max(1, min(job.months, round(b * job.months + 0.4999)))
            return month_end(job.start, k)

        if job.planning_only:
            res.status = 0
            for code, seq, desc, chapter in MILESTONES:
                rows.add("Job Milestone", (job.no, code, seq, desc, planned_finish(chapter), BLANK_DATE))
            results.append(res)
            continue

        # --- Ejecución real mes a mes
        progress = {c[0]: 0.0 for c in CHAPTERS}
        history: list[dict[str, float]] = [dict(progress)]
        finished_k = None
        chapter_done: dict[str, date] = {}
        res.chapter_cost_actual = {c[0]: Decimal("0") for c in CHAPTERS}
        res.chapter_cost_actual["10"] = Decimal("0")
        k = 0
        while True:
            k += 1
            mend = month_end(job.start, k)
            if mend > CUTOFF:
                break
            t_act = k / (job.months * job.delay)
            new = {}
            for code, _desc, a, b in CHAPTERS:
                base = planned_progress(t_act, a, b)
                noisy = base if base >= 1 or base <= 0 else min(1.0, base * (1 + rng.uniform(-0.04, 0.04)))
                new[code] = max(progress[code], round(noisy, 4))
            # coste directo
            for code, desc, _a, _b in CHAPTERS:
                inc = new[code] - progress[code]
                if inc <= 0:
                    continue
                factor = job.overrun.get(code, job.overrun_all) * rng.uniform(0.97, 1.03)
                amount = money(float(ch_cost[code]) * inc * factor)
                if amount < 1:
                    continue
                labor, labor2, mat, sub = split_amount(amount, [0.18, 0.12, 0.40, 0.30])
                lines = [
                    (0, "OF1", "Mano de obra oficial", labor, 28),
                    (0, "PEON", "Mano de obra peón", labor2, 28),
                    (2, "6010000", "Compras de materiales", mat, 15),
                    (2, "6070000", "Trabajos de subcontratas", sub, 25),
                ]
                if code == "01":
                    lines[2] = (0, "EXC", "Maquinaria: excavadora", mat, 15)
                elif code == "03":
                    lines[2] = (0, "GRUA", "Maquinaria: grúa torre", mat, 15)
                for typ, no, d, amt, day in lines:
                    if amt <= 0:
                        continue
                    entry_no += 1
                    pdate = day_in_month(mend, day)
                    unit = Decimal(str(RESOURCE_COST[no])) if typ == 0 else amt
                    qty = (amt / unit).quantize(Decimal("0.01")) if typ == 0 else Decimal("1")
                    rows.add("Job Ledger Entry", (
                        entry_no, job.no, pdate, f"CONS-{job.no[3:]}-{k:02d}", typ, no, d,
                        qty, unit, amt, Decimal("0"), Decimal("0"), Decimal("0"), code, 0,
                        job.delegation, job.kind, "HORA" if typ == 0 else "UD",
                    ))
                    res.actual_cost += amt
                    res.chapter_cost_actual[code] += amt
            # coste indirecto: corre mientras la obra está abierta
            ind = money(float(indirect_budget) / job.months * rng.uniform(0.95, 1.05))
            ind_res, ind_gl = split_amount(ind, [0.45, 0.55])
            for typ, no, d, amt in (
                (0, job.manager, "Jefatura de obra", ind_res),
                (2, "6290000", "Gastos generales de obra", ind_gl),
            ):
                entry_no += 1
                unit = Decimal(str(RESOURCE_COST[no])) if typ == 0 else amt
                qty = (amt / unit).quantize(Decimal("0.01")) if typ == 0 else Decimal("1")
                rows.add("Job Ledger Entry", (
                    entry_no, job.no, mend, f"CONS-{job.no[3:]}-{k:02d}", typ, no, d,
                    qty, unit, amt, Decimal("0"), Decimal("0"), Decimal("0"), "10", 0,
                    job.delegation, job.kind, "HORA" if typ == 0 else "UD",
                ))
                res.actual_cost += amt
                res.chapter_cost_actual["10"] += amt
            # mediciones de avance (tabla a medida)
            for code, _desc, _a, _b in CHAPTERS:
                if new[code] > 0 and (progress[code] < 1 or k == 1):
                    progress_entry_no += 1
                    rows.add("Job Progress Entry", (
                        progress_entry_no, job.no, code, mend,
                        Decimal(str(round(new[code] * 100, 2))), job.manager,
                    ))
                if new[code] >= 1 and code not in chapter_done:
                    chapter_done[code] = day_in_month(mend, rng.randint(8, 27))
            progress = new
            history.append(dict(progress))
            if all(v >= 1 for v in progress.values()):
                finished_k = k
                break

        # --- Certificaciones (Job Ledger Entry de tipo Venta, importes en negativo)
        cert_done = {c[0]: 0.0 for c in CHAPTERS}
        n_cert = 0
        last_k = (finished_k + 1) if finished_k else k
        for kk in range(2, last_k + 1):
            mend = month_end(job.start, kk)
            if mend > CUTOFF:
                break
            src = history[min(kk - 1, len(history) - 1)]
            ratio = job.cert_ratio if kk >= job.cert_ratio_from else 1.0
            any_line = False
            for code, desc, _a, _b in CHAPTERS:
                target = src[code] * ratio
                inc = target - cert_done[code]
                if inc <= 0.0001:
                    continue
                amount = money(float(ch_sale[code]) * inc)
                if amount < 1:
                    continue
                if not any_line:
                    n_cert += 1
                    any_line = True
                entry_no += 1
                rows.add("Job Ledger Entry", (
                    entry_no, job.no, mend, f"CERT-{job.no[3:]}-{n_cert:02d}", 2, "7050000",
                    f"Certificación {n_cert:02d} - {desc}", Decimal("-1"), Decimal("0"),
                    Decimal("0"), amount, -amount, -amount, code, 1,
                    job.delegation, job.kind, "UD",
                ))
                cert_done[code] = target
                res.certified += amount

        res.production = sum(
            (money(float(ch_sale[c]) * progress[c]) for c in ch_sale), Decimal("0")
        )
        if finished_k:
            res.status = 3
            res.finished_on = month_end(job.start, finished_k)

        # --- Hitos
        for code, seq, desc, chapter in MILESTONES:
            planned = planned_finish(chapter)
            if chapter is None:
                actual = job.start + timedelta(days=rng.randint(2, 9))
            elif chapter == "ALL":
                actual = (day_in_month(res.finished_on, rng.randint(8, 27))
                          if res.finished_on else BLANK_DATE)
            else:
                actual = chapter_done.get(chapter, BLANK_DATE)
            rows.add("Job Milestone", (job.no, code, seq, desc, planned, actual))
            late_days = 0
            if actual == BLANK_DATE and planned < CUTOFF:
                late_days = (CUTOFF - planned).days
            elif actual != BLANK_DATE and actual > planned:
                late_days = (actual - planned).days
            if late_days > 0:
                res.milestones_late += 1
                res.max_delay_days = max(res.max_delay_days, late_days)

        results.append(res)
    return results


def build_rows() -> tuple[Rows, list[JobResult]]:
    rows = Rows()
    for dim, code, name in DIMENSIONS:
        rows.add("Dimension Value", (dim, code, name, 0, 0))
    for no, name, city, vat in CUSTOMERS:
        rows.add("Customer", (no, name, name.upper()[:50], city, vat, 0))
    for no, typ, name, uom, cost in RESOURCES:
        rows.add("Resource", (no, typ, name, uom, Decimal(str(cost)), 0))
    results = simulate(rows)
    for res in results:
        j = res.job
        rows.add("Job", (
            j.no, j.description.upper()[:50], j.description, "", j.customer,
            j.start - timedelta(days=45), j.start, month_end(j.start, j.months),
            res.status, j.manager, j.delegation, j.kind, "OBRA", 0,
        ))
    return rows, results


def test_company_rows() -> Rows:
    """Empresa de pruebas: datos basura que no deben salir en los informes."""
    rows = Rows()
    rows.add("Job", ("PRUEBA-01", "OBRA DE PRUEBA FORMACION", "Obra de prueba formación", "",
                     "C00010", date(2025, 1, 1), date(2025, 1, 1), date(2025, 12, 31), 2,
                     "JO01", "NORTE", "EDIF", "OBRA", 0))
    rows.add("Job Task", ("PRUEBA-01", "01", "Movimiento de tierras", 0, 1, ""))
    for n in range(1, 4):
        rows.add("Job Ledger Entry", (
            n, "PRUEBA-01", date(2025, n, 28), f"TEST-{n}", 2, "6010000", "Prueba",
            Decimal("1"), Decimal("1000"), Decimal("1000"), Decimal("0"), Decimal("0"),
            Decimal("0"), "01", 0, "NORTE", "EDIF", "UD",
        ))
    return rows


# ---------------------------------------------------------------------------
# Escritura de ficheros
# ---------------------------------------------------------------------------

HEADER = "-- Generado por tools/generate_seed.py. No editar a mano: cambia el generador y vuelve a ejecutarlo.\n"


def table_name(company: str, table: str) -> str:
    return f"[dbo].[{company}${table}]"


def schema_sql() -> str:
    out = [HEADER, "SET NOCOUNT ON;", "GO", ""]
    out += [
        f"IF DB_ID(N'{DATABASE}') IS NOT NULL",
        "BEGIN",
        f"    ALTER DATABASE [{DATABASE}] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;",
        f"    DROP DATABASE [{DATABASE}];",
        "END",
        "GO",
        f"CREATE DATABASE [{DATABASE}] COLLATE Modern_Spanish_CI_AS;",
        "GO",
        f"USE [{DATABASE}];",
        "GO",
        "",
        "-- Tabla de empresas de NAV",
        "CREATE TABLE [dbo].[Company] (",
        "    [timestamp] rowversion NOT NULL,",
        "    [Name] nvarchar(30) NOT NULL,",
        "    [Display Name] nvarchar(250) NOT NULL,",
        "    CONSTRAINT [Company$0] PRIMARY KEY CLUSTERED ([Name])",
        ");",
        "GO",
        "",
    ]
    for company in (COMPANY, TEST_COMPANY):
        out.append(f"-- Tablas de la empresa {company}")
        for table, (cols, pk) in TABLES.items():
            out.append(f"CREATE TABLE {table_name(company, table)} (")
            out.append("    [timestamp] rowversion NOT NULL,")
            for name, typ in cols:
                out.append(f"    [{name}] {typ} NOT NULL,")
            pk_cols = ", ".join(f"[{c}]" for c in pk)
            out.append(f"    CONSTRAINT [{company}${table}$0] PRIMARY KEY CLUSTERED ({pk_cols})")
            out.append(");")
        out.append("GO")
        out.append("")
    out += [
        "-- Esquema para las vistas de reporting (lo rellena el agente en la demo)",
        "CREATE SCHEMA [rpt] AUTHORIZATION [dbo];",
        "GO",
    ]
    return "\n".join(out) + "\n"


def inserts(company: str, rows: Rows) -> list[str]:
    out = []
    for table, (cols, _pk) in TABLES.items():
        data = rows.tables.get(table, [])
        if not data:
            continue
        col_list = ", ".join(f"[{c}]" for c, _ in cols)
        out.append(f"-- {company}${table}: {len(data)} filas")
        for i in range(0, len(data), 500):
            chunk = data[i : i + 500]
            values = ",\n".join("(" + ", ".join(lit(v) for v in r) + ")" for r in chunk)
            out.append(f"INSERT INTO {table_name(company, table)} ({col_list}) VALUES\n{values};")
            out.append("GO")
    return out


def seed_sql(rows: Rows, test_rows: Rows) -> str:
    out = [HEADER, "SET NOCOUNT ON;", f"USE [{DATABASE}];", "GO", ""]
    out.append("INSERT INTO [dbo].[Company] ([Name], [Display Name]) VALUES")
    out.append(f"({lit(COMPANY)}, {lit('Constructora Demo S.A.')}),")
    out.append(f"({lit(TEST_COMPANY)}, {lit('Constructora Demo - empresa de pruebas')});")
    out.append("GO")
    out += inserts(COMPANY, rows)
    out += inserts(TEST_COMPANY, test_rows)
    return "\n".join(out) + "\n"


def fmt(x: Decimal) -> str:
    s = f"{x:,.2f}"
    return s.replace(",", "X").replace(".", ",").replace("X", ".")


def pct(x: Decimal | float) -> str:
    return f"{float(x) * 100:.1f} %".replace(".", ",")


def expected_md(results: list[JobResult], rows: Rows) -> str:
    status_txt = {0: "Planificación", 2: "Abierta", 3: "Terminada"}
    out = [
        "# Valores esperados (solo para el formador)",
        "",
        "Generado por `tools/generate_seed.py`. Sirve para comprobar las vistas, las medidas y el",
        f"informe. Fecha de corte de los datos: {CUTOFF:%d/%m/%Y}. Solo empresa `{COMPANY}`.",
        "",
        "Definiciones usadas aquí (el proposal de la demo puede definirlas de otra forma;",
        "si lo hace, las cifras cambian y hay que razonar por qué):",
        "",
        "- **Presupuesto coste**: suma de `Total Cost (LCY)` de `Job Planning Line` (todos los tipos de línea).",
        "- **Presupuesto venta**: suma de `Line Amount (LCY)` de `Job Planning Line` con `Line Type` 1 o 2.",
        "- **Coste real**: suma de `Total Cost (LCY)` de `Job Ledger Entry` con `Entry Type` = 0 (consumo).",
        "- **Certificado**: suma de `Line Amount (LCY)` de `Job Ledger Entry` con `Entry Type` = 1, cambiada de signo.",
        "- **Producción**: por capítulo, último `Completion _` de `Job Progress Entry` × presupuesto de venta",
        "  del capítulo (sin la línea sin fecha).",
        "- **Avance planificado a la fecha de corte**: presupuesto de venta con `Planning Date` ≤ corte",
        "  (sin la línea sin fecha) / presupuesto de venta.",
        "- **Hitos retrasados**: cumplidos después de la fecha planificada, o no cumplidos con la fecha",
        "  planificada ya pasada.",
        "",
        "## Económico por obra",
        "",
        "| Obra | Estado | Ppto. coste | Ppto. venta | Coste real | Certificado | Producción | Pte. certificar | Margen real (cert. − coste) |",
        "|---|---|---:|---:|---:|---:|---:|---:|---:|",
    ]
    tot = [Decimal("0")] * 6
    for r in results:
        pend = r.production - r.certified
        vals = [r.budget_cost, r.budget_sale, r.actual_cost, r.certified, r.production, pend]
        tot = [a + b for a, b in zip(tot, vals)]
        out.append(
            f"| {r.job.no} | {status_txt[r.status]} | " + " | ".join(fmt(v) for v in vals)
            + f" | {fmt(r.certified - r.actual_cost)} |"
        )
    out.append("| **Total** | | " + " | ".join(f"**{fmt(v)}**" for v in tot)
               + f" | **{fmt(tot[3] - tot[2])}** |")
    out += [
        "",
        "## Plazos y avance por obra",
        "",
        "| Obra | Inicio | Fin planificado | Fin real | Avance planificado | Avance real (producción / ppto.) | Hitos retrasados | Máx. días de retraso |",
        "|---|---|---|---|---:|---:|---:|---:|",
    ]
    for r in results:
        j = r.job
        base_sale = r.budget_sale - (money(j.extra_planning_line[3]) if j.extra_planning_line else 0)
        planned = r.planned_sale_to_cutoff / base_sale if base_sale else 0
        real = r.production / base_sale if base_sale else 0
        out.append(
            f"| {j.no} | {j.start:%d/%m/%Y} | {month_end(j.start, j.months):%d/%m/%Y} | "
            f"{r.finished_on:%d/%m/%Y} | " if r.finished_on else
            f"| {j.no} | {j.start:%d/%m/%Y} | {month_end(j.start, j.months):%d/%m/%Y} | — | "
        )
        out[-1] += f"{pct(planned)} | {pct(real)} | {r.milestones_late} | {r.max_delay_days} |"
    out += [
        "",
        "## Coste por capítulo en las obras con sobrecoste",
        "",
        "| Obra | Capítulo | Ppto. coste | Coste real | Desviación |",
        "|---|---|---:|---:|---:|",
    ]
    for r in results:
        if r.job.no not in ("OB-25002", "OB-25003"):
            continue
        for code, budget in sorted(r.chapter_cost_budget.items()):
            actual = r.chapter_cost_actual.get(code, Decimal("0"))
            dev = (actual - budget) / budget if budget else 0
            out.append(f"| {r.job.no} | {code} | {fmt(budget)} | {fmt(actual)} | {pct(dev)} |")
    out += [
        "",
        "## Recuento de filas",
        "",
        "| Tabla | Filas |",
        "|---|---:|",
    ]
    for table in TABLES:
        out.append(f"| `{COMPANY}${table}` | {len(rows.tables.get(table, []))} |")
    return "\n".join(out) + "\n"


def write(path: Path, text: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8", newline="\n")
    print(f"Escrito {path.relative_to(ROOT)}")


def main() -> None:
    rows, results = build_rows()
    write(ROOT / "db" / "init" / "01_schema.sql", schema_sql())
    write(ROOT / "db" / "init" / "02_seed.sql", seed_sql(rows, test_company_rows()))
    write(ROOT / "docs" / "formador" / "VALORES_ESPERADOS.md", expected_md(results, rows))


if __name__ == "__main__":
    main()
