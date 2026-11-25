"""Genera los visuales PBIR del issue 01. Ejecutar con Power BI Desktop cerrado."""
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REPORT = ROOT / 'ObrasModel/Obras.Report/definition'
SCHEMA = 'https://developer.microsoft.com/json-schemas/fabric/item/report/definition/'
NAVY, TEAL, RED, AMBER = '#102B3F', '#007F82', '#B83246', '#9B6500'
SUMMARY_PAGE = 'cd4b6ae76f9576c0d15f'


def save(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


def uid(text):
    return hashlib.sha256(text.encode()).hexdigest()[:20]


def lit(value):
    if isinstance(value, bool):
        value = str(value).lower()
    elif isinstance(value, (float, int)):
        value = str(value) + 'D'
    else:
        value = "'" + value.replace("'", "''") + "'"
    return {'Literal': {'Value': value}}


def expr(value):
    return {'expr': lit(value)}


def fill(color):
    return {'solid': {'color': expr(color)}}


def field(table, name, measure=False, source=False):
    return {'Measure' if measure else 'Column': {
        'Expression': {'SourceRef': {'Source' if source else 'Entity': table}}, 'Property': name}}


def col(table, name, label=None):
    return {'field': field(table, name), 'queryRef': table + '.' + name,
            'nativeQueryRef': name, 'displayName': label or name}


MEASURES = {
    'Presupuesto de coste': 'FactPresupuesto', 'Presupuesto de venta': 'FactPresupuesto',
    'Margen previsto': 'FactPresupuesto', 'Margen previsto %': 'FactPresupuesto',
    'Coste real': 'FactMovimiento', 'Coste real mensual': 'FactMovimiento',
    'Importe certificado': 'FactMovimiento', 'Certificado mensual': 'FactMovimiento',
    'Pendiente de certificar': 'FactMovimiento', 'Margen real': 'FactMovimiento',
    'Margen real %': 'FactMovimiento', 'Producción': 'FactAvance',
    'Producción mensual': 'FactAvance', 'Coste previsto': 'FactAvance',
    'Desviación de coste': 'FactAvance', 'Desviación de coste %': 'FactAvance',
    'Estado de desviación': 'FactAvance', 'Destacar partida': 'FactAvance',
}


def measure(name, label=None):
    t = MEASURES[name]
    return {'field': field(t, name, True), 'queryRef': t + '.' + name,
            'nativeQueryRef': name, 'displayName': label or name}


def obj(properties, selector=None):
    result = {'properties': properties}
    if selector is not None:
        result['selector'] = selector
    return result


def chrome(title=None):
    result = {
        'background': [obj({'show': expr(True), 'color': fill('#FFFFFF'), 'transparency': expr(0)})],
        'border': [obj({'show': expr(True), 'color': fill('#DAE3ED'), 'radius': expr(8)})],
        'padding': [obj({side: expr(12) for side in ['top', 'bottom', 'left', 'right']})],
        'title': [obj({'show': expr(bool(title)), 'text': expr(title or ''),
                       'fontColor': fill(NAVY), 'fontSize': expr(14)})],
    }
    return result


def visual(page, key, kind, position, roles=None, title=None):
    name = uid(page + ':' + key)
    x, y, w, h = position
    data = {'$schema': SCHEMA + 'visualContainer/2.9.0/schema.json', 'name': name,
            'position': {'x': x, 'y': y, 'width': w, 'height': h, 'z': y * 10 + x,
                         'tabOrder': y * 10 + x},
            'visual': {'visualType': kind, 'visualContainerObjects': chrome(title)}}
    if roles:
        data['visual']['query'] = {'queryState': {r: {'projections': p} for r, p in roles.items()}}
    return data


def textbox(page, key, text, position, size=16, color=NAVY):
    v = visual(page, key, 'textbox', position)
    v['visual']['visualContainerObjects'] = {'background': [obj({'show': expr(False)})],
        'border': [obj({'show': expr(False)})],
        'padding': [obj({s: expr(0) for s in ['top', 'bottom', 'left', 'right']})]}
    v['visual']['objects'] = {'general': [obj({'paragraphs': [{'textRuns': [{
        'value': text, 'textStyle': {'fontFamily': 'Segoe UI', 'fontSize': str(size) + 'px',
                                   'color': color}}]}]})]}
    return v


def card(page, name, position, label=None):
    v = visual(page, 'kpi:' + name, 'cardVisual', position, {'Data': [measure(name, label)]})
    v['visual']['objects'] = {
        'value': [obj({'fontSize': expr(26), 'fontColor': fill(TEAL)}, {'id': 'default'})],
        'label': [obj({'fontSize': expr(12), 'fontColor': fill(NAVY)}, {'id': 'default'})],
        'padding': [obj({'paddingUniform': expr(8)}, {'id': 'default'})],
        'layout': [obj({'paddingUniform': expr(0)}, {'id': 'default'})],
    }
    return v


def sort(v, projection, descending=True):
    v['visual']['query']['sortDefinition'] = {'sort': [{'field': projection['field'],
        'direction': 'Descending' if descending else 'Ascending'}], 'isDefaultSort': False}


def condition(left, operator, right):
    return {'Comparison': {'ComparisonKind': operator, 'Left': left, 'Right': lit(right)}}


def color_rule(v, target, driver, operator, value, color, background=False):
    rule = {'Conditional': {'Cases': [{'Condition': condition(driver['field'], operator, value),
                                      'Value': lit(color)}],
                            'DefaultValue': lit('#FFFFFF' if background else NAVY)}}
    selector = {'data': [{'dataViewWildcard': {'matchingOption': 1}}], 'metadata': target['queryRef']}
    prop = 'backColor' if background else 'fontColor'
    for existing in v['visual']['objects']['values']:
        if existing.get('selector') == selector and prop in existing['properties']:
            existing['properties'][prop]['solid']['color']['expr']['Conditional']['Cases'].extend(rule['Conditional']['Cases'])
            return
    v['visual']['objects']['values'].append(obj({prop: {'solid': {'color': {'expr': rule}}}}, selector))


def table(page, key, title, projections, position, sort_by):
    v = visual(page, key, 'tableEx', position, {'Values': projections}, title)
    v['visual']['objects'] = {
        'columnHeaders': [obj({'fontSize': expr(11), 'fontColor': fill(NAVY),
                              'backColor': fill('#EAF0F6'), 'autoSizeColumnWidth': expr(True),
                              'columnAdjustment': expr('growToFit')})],
        'values': [obj({'fontSize': expr(11), 'backColorPrimary': fill('#FFFFFF'),
                       'backColorSecondary': fill('#F5F8FB'),
                       'fontColorPrimary': fill(NAVY), 'fontColorSecondary': fill(NAVY)})],
        'grid': [obj({'rowPadding': expr(8), 'gridVertical': expr(False), 'gridHorizontal': expr(True)})],
        'total': [obj({'totals': expr(True), 'fontSize': expr(11)})],
    }
    sort(v, sort_by)
    if any(p['nativeQueryRef'] == 'Margen real' for p in projections):
        color_rule(v, measure('Margen real'), measure('Margen real'), 3, 0, RED)
    if any(p['nativeQueryRef'] == 'Desviación de coste %' for p in projections):
        color_rule(v, measure('Desviación de coste %'), measure('Destacar partida'), 0, 1, RED, True)
    return v


def filter_config(name, table_name, column, lower=None, upper=None, dates=False):
    left = field('s', column, source=True)
    conditions = []
    for value, op in [(lower, 2), (upper, 4)]:
        if value is not None:
            right = {'Literal': {'Value': "datetime'" + value + "T00:00:00'"}} if dates else lit(value)
            conditions.append({'Comparison': {'ComparisonKind': op, 'Left': left, 'Right': right}})
    where = conditions[0] if len(conditions) == 1 else {'And': {'Left': conditions[0], 'Right': conditions[1]}}
    return {'filters': [{'name': uid(name), 'field': field(table_name, column),
        'type': 'Advanced', 'howCreated': 'User',
        'filter': {'Version': 2, 'From': [{'Name': 's', 'Entity': table_name, 'Type': 0}],
                   'Where': [{'Condition': where}]}}]}


def header(page, title):
    vs = [textbox(page, 'titulo', title, (36, 22, 1848, 50), 32),
          textbox(page, 'subtitulo', 'CONSTRUCTORA DEMO  ·  Importes en euros  ·  Ejecución hasta la fecha seleccionada',
                  (36, 80, 1848, 28), 16, '#526779')]
    for i, (label, t, c) in enumerate([
        ('Delegación', 'DimObra', 'Delegacion'), ('Tipo de obra', 'DimObra', 'TipoObra'),
        ('Jefe de obra', 'DimObra', 'JefeObra'), ('Estado', 'DimObra', 'Estado'),
        ('Obra', 'DimObra', 'Obra'), ('Hasta fecha', 'DimFecha', 'Fecha')]):
        v = visual(page, 'filtro:' + c, 'slicer', (36 + i * 310, 128, 298, 88),
                   {'Values': [col(t, c, label)]})
        v['visual']['syncGroup'] = {'groupName': 'Filtro_' + c, 'fieldChanges': True, 'filterChanges': True}
        v['visual']['objects'] = {
            'data': [obj({'mode': expr('Before' if c == 'Fecha' else 'Dropdown')})],
            'header': [obj({'show': expr(True), 'text': expr(label), 'fontSize': expr(12)})],
            'selection': [obj({'singleSelect': expr(False), 'selectAllCheckboxEnabled': expr(True)})],
        }
        vs.append(v)
    return vs


def chart(page, key, title, series, position):
    v = visual(page, key, 'lineChart', position,
               {'Category': [col('DimFecha', 'AñoMes', 'Mes')], 'Y': [measure(m, label) for m, label in series]}, title)
    sort(v, col('DimFecha', 'AñoMes'), False)
    v['visual']['objects'] = {
        'legend': [obj({'show': expr(True), 'position': expr('Top'), 'fontSize': expr(12)})],
        'categoryAxis': [obj({'axisType': expr('Categorical'), 'fontSize': expr(11)})],
        'valueAxis': [obj({'fontSize': expr(11), 'showAxisTitle': expr(True), 'titleText': expr('Euros')})],
        'dataPoint': [obj({'fill': fill(color)}, {'metadata': MEASURES[m] + '.' + m})
                      for (m, _), color in zip(series, [RED, TEAL, '#386CB0'])],
    }
    return v


def build():
    existing = json.loads((REPORT / 'pages/pages.json').read_text())
    pages = [(SUMMARY_PAGE, 'Resumen', '01 · Resumen económico'),
             (uid('detalle-partidas'), 'Detalle por partida', '02 · Detalle de obra y partidas'),
             (uid('evolucion-mensual'), 'Evolución mensual', '03 · Evolución mensual')]
    inventory = []
    for page, display, title in pages:
        data = {'$schema': SCHEMA + 'page/2.1.0/schema.json', 'name': page, 'displayName': display,
                'displayOption': 'FitToPage', 'width': 1920, 'height': 1080,
                'objects': {'background': [obj({'color': fill('#F3F6FA'), 'transparency': expr(0)})]}}
        vs = header(page, title)
        if display == 'Resumen':
            names = ['Presupuesto de coste', 'Presupuesto de venta', 'Coste real', 'Producción',
                     'Importe certificado', 'Pendiente de certificar', 'Margen previsto', 'Margen real']
            for i, name in enumerate(names):
                vs.append(card(page, name, (36 + i % 4 * 466, 240 + i // 4 * 144, 450, 128)))
            projections = [col('DimObra', 'Obra'), col('DimObra', 'Estado')] + [measure(m) for m in [
                'Presupuesto de coste', 'Presupuesto de venta', 'Coste real', 'Producción', 'Importe certificado',
                'Pendiente de certificar', 'Margen previsto', 'Margen previsto %', 'Margen real',
                'Margen real %', 'Desviación de coste %']]
            vs.append(table(page, 'obras', 'Obras · ordenadas por pendiente de certificar', projections,
                            (36, 540, 1848, 500), measure('Pendiente de certificar')))
        elif display == 'Detalle por partida':
            for i, name in enumerate(['Coste real', 'Coste previsto', 'Desviación de coste', 'Desviación de coste %']):
                vs.append(card(page, name, (36 + i * 466, 240, 450, 128)))
            vs.append(textbox(page, 'leyenda', 'Rojo: desviación superior al 5 % · Ámbar: coste sin avance · Un 5 % exacto no activa la alerta.',
                              (36, 386, 1848, 36), 17))
            projections = [col('DimPartida', 'ObraClave', 'Obra'), col('DimPartida', 'PartidaCodigo', 'Código'),
                           col('DimPartida', 'Partida')] + [measure(m) for m in [
                'Presupuesto de coste', 'Presupuesto de venta', 'Coste real', 'Coste previsto',
                'Desviación de coste', 'Desviación de coste %', 'Producción', 'Importe certificado',
                'Pendiente de certificar', 'Margen real', 'Estado de desviación']]
            t = table(page, 'partidas', 'Partidas · desviación desfavorable primero', projections,
                      (36, 440, 1848, 600), measure('Desviación de coste %'))
            for target in [col('DimPartida', 'Partida'), measure('Estado de desviación')]:
                color_rule(t, target, measure('Estado de desviación'), 0, 'Coste sin avance', '#FFF2CC', True)
            color_rule(t, col('DimPartida', 'Partida'), measure('Estado de desviación'), 0,
                       'Desviación > 5 %', '#FCE4E8', True)
            # Con dimensiones independientes, no mostrar combinaciones fuera del filtro de obra.
            t['filterConfig'] = {'filters': [{'name': uid('partidas-con-presupuesto-o-coste'),
                'field': field('FactPresupuesto', 'Presupuesto de coste', True), 'type': 'Advanced',
                'filter': {'Version': 2, 'From': [{'Name': 'p', 'Entity': 'FactPresupuesto', 'Type': 0},
                                               {'Name': 'm', 'Entity': 'FactMovimiento', 'Type': 0}],
                    'Where': [{'Condition': {'Or': {
                        'Left': condition(field('p', 'Presupuesto de coste', True, True), 1, 0),
                        'Right': condition(field('m', 'Coste real', True, True), 1, 0)}}}]}}]}
            vs.append(t)
        else:
            period_labels = ['Coste del periodo', 'Producción del periodo', 'Certificado del periodo', 'Pendiente al cierre']
            for i, name in enumerate(['Coste real mensual', 'Producción mensual', 'Certificado mensual', 'Pendiente de certificar']):
                vs.append(card(page, name, (36 + i * 466, 240, 450, 128), period_labels[i]))
            vs.append(chart(page, 'flujos', 'Coste, producción y certificado · importes mensuales', [
                ('Coste real mensual', 'Coste'), ('Producción mensual', 'Producción'),
                ('Certificado mensual', 'Certificado')], (36, 400, 1848, 340)))
            projections = [col('DimFecha', 'AñoMes', 'Mes')] + [measure(m) for m in [
                'Coste real mensual', 'Producción mensual', 'Certificado mensual',
                'Coste real', 'Producción', 'Importe certificado', 'Pendiente de certificar', 'Margen real']]
            vs.append(table(page, 'meses', 'Detalle mensual y acumulados al cierre', projections,
                            (36, 768, 1848, 272), col('DimFecha', 'AñoMes')))
            sort(vs[-1], col('DimFecha', 'AñoMes'), False)
        save(REPORT / 'pages' / page / 'page.json', data)
        for v in vs:
            save(REPORT / 'pages' / page / 'visuals' / v['name'] / 'visual.json', v)
        inventory.append({'pagina': display, 'id': page, 'visuales': len(vs)})
    managed = [p[0] for p in pages]
    extra = [p for p in existing['pageOrder'] if p not in managed]
    save(REPORT / 'pages/pages.json', {'$schema': SCHEMA + 'pagesMetadata/1.1.0/schema.json',
        'pageOrder': managed + extra, 'activePageName': pages[0][0]})
    report = json.loads((REPORT / 'report.json').read_text())
    report['$schema'] = SCHEMA + 'report/3.3.0/schema.json'
    report['filterConfig'] = filter_config('periodo-datos', 'DimFecha', 'Fecha', '2025-01-01', '2026-10-31', True)
    save(REPORT / 'report.json', report)
    print(json.dumps(inventory, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    build()
