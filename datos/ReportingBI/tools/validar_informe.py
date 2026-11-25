"""Valida esquemas oficiales, referencias del modelo, filtros y diseño del PBIR.

Requiere jsonschema. --schemas permite usar un registro descargado sin conexión.
La validación estática no sustituye la revisión del informe en Desktop.
"""
import argparse
from collections import Counter, defaultdict
import json
from pathlib import Path
import re
import urllib.parse
import urllib.request


def validar(registry_path=None, page_ids=None):
    import jsonschema
    from referencing import Registry, Resource

    root = Path(__file__).resolve().parents[1]
    report = root / 'ObrasModel/Obras.Report/definition'
    model = root / 'ObrasModel/Obras.SemanticModel/definition/tables'
    schemas = json.loads(Path(registry_path).read_text()) if registry_path else {}

    def schema(uri):
        uri = uri.split('#')[0]
        if uri not in schemas:
            schemas[uri] = json.loads(urllib.request.urlopen(uri, timeout=30).read())
        data = schemas[uri]

        def refs(value):
            if isinstance(value, dict):
                ref = value.get('$ref', '')
                if ref and not ref.startswith('#'):
                    target = urllib.parse.urljoin(uri, ref).split('#')[0]
                    if target not in schemas:
                        alias = target.replace('schema-embedded.json', 'schema.embedded.json')
                        if alias in schemas:
                            schemas[target] = schemas[alias]
                        else:
                            schema(target)
                for child in value.values():
                    refs(child)
            elif isinstance(value, list):
                for child in value:
                    refs(child)
        refs(data)
        return data

    fields = {}
    for file in model.glob('*.tmdl'):
        fields[file.stem] = {'Column': set(), 'Measure': set()}
        for kind, quoted, plain in re.findall(
                r"^\s*(column|measure) (?:'((?:[^']|'')+)'|([^\s=]+))", file.read_text(), re.M):
            fields[file.stem]['Column' if kind == 'column' else 'Measure'].add((quoted or plain).replace("''", "'"))

    errors, files, groups, ids = [], [], defaultdict(list), set()
    metadata = json.loads((report / 'pages/pages.json').read_text())
    page_order = metadata['pageOrder']
    assert page_order and len(set(page_order)) == len(page_order)
    scoped_pages = set(page_ids or page_order)
    assert scoped_pages <= set(page_order)

    def bindings(value, sources=None):
        sources = dict(sources or {})
        if isinstance(value, dict):
            for source in value.get('From', []):
                sources[source['Name']] = source['Entity']
            for kind in ['Column', 'Measure']:
                node = value.get(kind)
                if isinstance(node, dict) and 'Property' in node:
                    ref = node.get('Expression', {}).get('SourceRef', {})
                    table = ref.get('Entity') or sources.get(ref.get('Source'))
                    if table not in fields or node['Property'] not in fields[table][kind]:
                        errors.append(f'Referencia no encontrada: {table}.{node["Property"]} ({kind})')
            for child in value.values():
                bindings(child, sources)
        elif isinstance(value, list):
            for child in value:
                bindings(child, sources)

    visuals = []
    for file in report.rglob('*.json'):
        if page_ids:
            relative = file.relative_to(report).parts
            # Conservar esquemas nativos del Desktop; validar las páginas editadas,
            # el orden y los visuales nuevos usando sus esquemas públicos declarados.
            if relative[0] != 'pages':
                continue
            if file.name != 'page.json' and relative != ('pages', 'pages.json'):
                if len(relative) < 2 or relative[1] not in scoped_pages:
                    continue
        data = json.loads(file.read_text())
        if '/version.json' in str(file):
            continue  # Metadato nativo sin cambios, fuera del conjunto generado.
        uri = data.get('$schema')
        assert uri, file
        definition = schema(uri)
        registry = Registry().with_resources((u, Resource.from_contents(d)) for u, d in schemas.items())
        for error in jsonschema.Draft7Validator(definition, registry=registry).iter_errors(data):
            errors.append(f'{file.relative_to(root)}: {list(error.absolute_path)}: {error.message}')
        bindings(data)
        files.append(str(file.relative_to(root)))
        if file.name == 'visual.json':
            assert data['name'] == file.parent.name and data['name'] not in ids
            ids.add(data['name'])
            page = json.loads((file.parents[2] / 'page.json').read_text())
            assert page['name'] in page_order
            p = data['position']
            assert p['x'] >= 0 and p['y'] >= 0 and p['width'] > 0 and p['height'] > 0
            assert p['x'] + p['width'] <= page['width'] and p['y'] + p['height'] <= page['height']
            visual = data['visual']
            roles = set(visual.get('query', {}).get('queryState', {}))
            projections = [p for bucket in visual.get('query', {}).get('queryState', {}).values()
                           for p in bucket['projections']]
            references = {p['queryRef'] for p in projections}
            if visual['visualType'] == 'tableEx':
                assert all('active' not in p for p in projections), file
            for definitions in visual.get('objects', {}).values():
                for definition in definitions:
                    target = definition.get('selector', {}).get('metadata')
                    if target:
                        assert target in references, (file, target)
            expected = {'cardVisual': {'Data'}, 'tableEx': {'Values'}, 'slicer': {'Values'}, 'lineChart': {'Category', 'Y'}}
            if visual['visualType'] in expected:
                assert roles == expected[visual['visualType']], (file, roles)
            if 'syncGroup' in visual:
                projection = visual['query']['queryState']['Values']['projections'][0]
                groups[visual['syncGroup']['groupName']].append((page['name'], projection['queryRef']))
            visuals.append((page['name'], data))

    for group, members in groups.items():
        assert len(members) == len(scoped_pages) and len({p for p, _ in members}) == len(scoped_pages), group
        assert len({field for _, field in members}) == 1, group
    assert len(groups) == 6
    for i, (page, a) in enumerate(visuals):
        for other_page, b in visuals[i + 1:]:
            if page != other_page:
                continue
            x, y = a['position'], b['position']
            overlap = (x['x'] < y['x'] + y['width'] and y['x'] < x['x'] + x['width']
                       and x['y'] < y['y'] + y['height'] and y['y'] < x['y'] + x['height'])
            assert not overlap, (a['name'], b['name'])

    summary = {'paginas': len(page_order), 'paginasConVisualesValidados': len(scoped_pages),
               'visuales': len(visuals), 'archivosValidados': len(files),
               'gruposSincronizados': len(groups), 'tipos': dict(Counter(v['visual']['visualType'] for _, v in visuals)),
               'errores': errors, 'revisionVisualDesktop': 'pendiente'}
    print(json.dumps(summary, ensure_ascii=False, indent=2))
    assert not errors
    return summary


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--schemas')
    parser.add_argument('--pages', nargs='+', help='Validar visuales solo de estas páginas y metadatos de todas las páginas')
    args = parser.parse_args()
    validar(args.schemas, args.pages)
