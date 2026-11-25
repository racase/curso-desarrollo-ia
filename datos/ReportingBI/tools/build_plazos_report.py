"""Añade las páginas del issue 02. Ejecutar con Power BI Desktop cerrado.

Conserva los visuales existentes. Traslada el periodo económico del informe a sus
páginas originales para que el nuevo análisis pueda usar el corte dinámico del ERP.
"""
from copy import deepcopy
import json

import build_report as b


PAGES = [
    (b.uid('plazos-avance-obra'), 'Plazos y avance', '04 · Plazos y avance de obra'),
    (b.uid('hitos-obra'), 'Hitos', '05 · Hitos de obra'),
]


def filter_measure(visual, table, name, value=1):
    visual['filterConfig'] = {'filters': [{
        'name': b.uid(visual['name'] + ':visible'),
        'field': b.field(table, name, True), 'type': 'Advanced',
        'filter': {'Version': 2, 'From': [{'Name': 'm', 'Entity': table, 'Type': 0}],
                   'Where': [{'Condition': b.condition(b.field('m', name, True, True), 0, value)}]},
    }]}


def move_economic_period(metadata):
    report_path = b.REPORT / 'report.json'
    report = json.loads(report_path.read_text())
    filters = report.get('filterConfig', {}).get('filters', [])
    economic = [f for f in filters if f.get('field') == b.field('DimFecha', 'Fecha')]
    if not economic:
        return
    managed = {p[0] for p in PAGES}
    for page in metadata['pageOrder']:
        if page in managed:
            continue
        path = b.REPORT / 'pages' / page / 'page.json'
        data = json.loads(path.read_text())
        existing = data.setdefault('filterConfig', {}).setdefault('filters', [])
        for f in economic:
            if not any(item.get('name') == f['name'] for item in existing):
                existing.append(deepcopy(f))
        b.save(path, data)
    report['filterConfig']['filters'] = [f for f in filters if f not in economic]
    b.save(report_path, report)


def build():
    b.MEASURES.update({
        **dict.fromkeys(['Corte de plazos', 'Avance planificado', 'Desviación de avance',
                         'Índice de plazo', 'Mostrar mes de plazos'], 'FactPlanificacion'),
        'Avance real': 'FactMedicionAvance',
        **dict.fromkeys(['Estado de hito', 'Días de retraso del hito', 'Hitos retrasados',
                         'Máximo retraso pendiente', 'Hitos cumplidos tarde',
                         'Hitos cumplidos a tiempo', 'Hitos pendientes'], 'FactHito'),
    })
    metadata = json.loads((b.REPORT / 'pages/pages.json').read_text())
    move_economic_period(metadata)
    inventory = []
    for page, display, title in PAGES:
        data = {'$schema': b.SCHEMA + 'page/2.1.0/schema.json', 'name': page,
                'displayName': display, 'displayOption': 'FitToPage', 'width': 1920, 'height': 1080,
                'objects': {'background': [b.obj({'color': b.fill('#F3F6FA'), 'transparency': b.expr(0)})]}}
        visuals = b.header(page, title)
        visuals[1] = b.textbox(page, 'subtitulo',
            'CONSTRUCTORA DEMO · Avances ponderados por venta · Situación a la fecha de corte seleccionada',
            (36, 80, 1848, 28), 16, '#526779')
        if display == 'Plazos y avance':
            names = ['Avance planificado', 'Avance real', 'Desviación de avance',
                     'Índice de plazo', 'Hitos retrasados', 'Corte de plazos']
            for i, name in enumerate(names):
                visuals.append(b.card(page, name, (36+i*310, 240, 298, 112)))
            curve = b.chart(page, 'curva-s', 'Curva S · avances acumulados al cierre de cada mes',
                [('Avance planificado', 'Planificado'), ('Avance real', 'Real')], (36, 376, 1848, 304))
            axis = curve['visual']['objects']['valueAxis'][0]['properties']
            axis['titleText'] = b.expr('Avance (%)')
            axis['start'], axis['end'] = b.expr(0), b.expr(1)
            curve['visual']['objects']['dataPoint'][0]['properties']['fill'] = b.fill('#526779')
            filter_measure(curve, 'FactPlanificacion', 'Mostrar mes de plazos')
            visuals.append(curve)
            projections = [b.col('DimObra', 'ObraClave', 'Código'), b.col('DimObra', 'Obra')]
            projections += [b.measure(n) for n in ['Avance planificado', 'Avance real',
                'Desviación de avance', 'Índice de plazo', 'Hitos retrasados', 'Máximo retraso pendiente']]
            ranking = b.table(page, 'ranking', 'Ranking · mayor retraso de avance primero', projections,
                (36, 700, 1848, 272), b.measure('Desviación de avance'))
            b.sort(ranking, b.measure('Desviación de avance'), False)
            ranking['visual']['query']['sortDefinition']['sort'].append({
                'field': b.field('DimObra', 'ObraClave'), 'direction': 'Ascending'})
            b.color_rule(ranking, b.measure('Desviación de avance'), b.measure('Desviación de avance'), 3, 0, b.RED)
            visuals.append(ranking)
            visuals.append(b.textbox(page, 'exclusion',
                'El avance planificado excluye las líneas sin fecha del acumulado y de su base total; el presupuesto económico las conserva.',
                (36, 988, 1848, 28), 17))
            visuals.append(b.textbox(page, 'criterio',
                'Real: última medición por partida, ponderada por venta. Desviación en puntos porcentuales. Índice vacío si el planificado es cero.',
                (36, 1022, 1848, 28), 17, '#526779'))
        else:
            for i, name in enumerate(['Hitos retrasados', 'Máximo retraso pendiente',
                'Hitos cumplidos tarde', 'Hitos cumplidos a tiempo', 'Hitos pendientes', 'Corte de plazos']):
                visuals.append(b.card(page, name, (36+i*310, 240, 298, 112)))
            visuals.append(b.textbox(page, 'leyenda',
                'Retrasado: pendiente con fecha planificada anterior al corte. Cumplidos tarde y pendientes vencidos se cuentan por separado.',
                (36, 374, 1848, 34), 17))
            projections = [b.col('DimObra', 'ObraClave', 'Obra'), b.col('FactHito', 'HitoCodigo', 'Código'),
                b.col('FactHito', 'Hito'), b.col('FactHito', 'Secuencia'),
                b.col('FactHito', 'FechaPlanificada', 'Fecha planificada'), b.col('FactHito', 'FechaReal', 'Fecha real'),
                b.measure('Estado de hito', 'Estado al corte'), b.measure('Días de retraso del hito', 'Días de retraso')]
            detail = b.table(page, 'detalle-hitos', 'Hitos · situación histórica a la fecha de corte', projections,
                (36, 430, 1848, 542), b.col('DimObra', 'ObraClave'))
            b.sort(detail, b.col('DimObra', 'ObraClave'), False)
            detail['visual']['query']['sortDefinition']['sort'].append({
                'field': b.field('FactHito', 'Secuencia'), 'direction': 'Ascending'})
            detail['visual']['objects']['total'][0]['properties']['totals'] = b.expr(False)
            for target in [b.measure('Estado de hito'), b.measure('Días de retraso del hito')]:
                b.color_rule(detail, target, b.measure('Estado de hito'), 0, 'Retrasado', '#FCE4E8', True)
                b.color_rule(detail, target, b.measure('Estado de hito'), 0, 'Cumplido con retraso', '#FFF2CC', True)
            visuals.append(detail)
            visuals.append(b.textbox(page, 'dias',
                'Días por hito: hasta la fecha real conocida al corte o hasta el propio corte. El máximo pendiente no suma retrasos solapados.',
                (36, 988, 1848, 28), 17))
            visuals.append(b.textbox(page, 'historico',
                'Una fecha real posterior al corte todavía no supone cumplimiento. Las fechas reales no informadas se muestran vacías.',
                (36, 1022, 1848, 28), 17, '#526779'))
        b.save(b.REPORT / 'pages' / page / 'page.json', data)
        for v in visuals:
            b.save(b.REPORT / 'pages' / page / 'visuals' / v['name'] / 'visual.json', v)
        inventory.append({'pagina': display, 'id': page, 'visuales': len(visuals)})
    for page, _, _ in PAGES:
        if page not in metadata['pageOrder']:
            metadata['pageOrder'].append(page)
    metadata['activePageName'] = PAGES[0][0]
    b.save(b.REPORT / 'pages/pages.json', metadata)
    print(json.dumps(inventory, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    build()
