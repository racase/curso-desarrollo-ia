"""Contrasta estados y días individuales de NAV frente a DAX ejecutado por MCP."""
import json
import sys
from pathlib import Path


def comparar(sql_path, dax_path):
    # La última sección con esta cabecera contiene la referencia completa.
    seccion = Path(sql_path).read_text().rsplit('Corte|Obra|Codigo|Estado|Dias', 1)[1]
    sql = {}
    for linea in seccion.splitlines():
        valores = linea.split('|')
        if len(valores) != 5 or not valores[0].startswith(('2025-', '2026-')):
            continue
        clave = tuple(valores[:3])
        assert clave not in sql, clave
        sql[clave] = (valores[3], int(valores[4]))
    datos = json.loads(Path(dax_path).read_text())
    assert not datos['isTruncated']
    dax = {}
    for fila in datos['rows']:
        clave = (fila['[Corte]'][:10], fila['[Obra]'], fila['[Codigo]'])
        assert clave not in dax, clave
        dax[clave] = (fila['[Estado]'], fila['[Dias]'])
    assert len(sql) == len(dax) == 1100
    assert sql == dax, 'Estados o días distintos de NAV'
    return {'filasSQL': len(sql), 'filasDAX': len(dax), 'diferenciasEstado': 0, 'diferenciasDias': 0}


if __name__ == '__main__':
    print(json.dumps(comparar(sys.argv[1], sys.argv[2]), ensure_ascii=False, indent=2))
