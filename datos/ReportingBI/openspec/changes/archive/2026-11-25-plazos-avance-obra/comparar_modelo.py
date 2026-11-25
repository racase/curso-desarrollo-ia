"""Compara la referencia NAV de db/query.sh con DAX ejecutado mediante MCP."""
import json
import sys
from decimal import Decimal
from pathlib import Path


def comparar(sql_path, dax_path):
    lineas = Path(sql_path).read_text().splitlines()
    columnas = next(x for x in lineas if x.startswith('Corte|Grupo|')).split('|')
    sql = {}
    for linea in lineas:
        if not linea.startswith(('2025-', '2026-')):
            continue
        valores = linea.split('|')
        assert len(valores) == len(columnas), linea
        fila = dict(zip(columnas, valores))
        clave = (fila['Corte'], fila['Grupo'])
        assert clave not in sql, clave
        sql[clave] = fila
    datos = json.loads(Path(dax_path).read_text())
    assert not datos['isTruncated'], 'Respuesta DAX truncada'
    dax = {}
    for original in datos['rows']:
        fila = {k.strip('[]'): v for k, v in original.items()}
        clave = (fila['Corte'][:10], fila['Grupo'])
        assert clave not in dax, clave
        dax[clave] = fila
    faltantes = sorted(set(sql) ^ set(dax))
    diferencias = []
    ratios = {'PlanPct', 'RealPct', 'DesviacionPP', 'Indice'}
    max_exacta = Decimal(0)
    max_ratio = Decimal(0)
    for clave in sorted(set(sql) & set(dax)):
        for campo in columnas[2:]:
            a = None if sql[clave][campo] == 'NULL' else Decimal(sql[clave][campo])
            valor = dax[clave].get(campo)
            b = None if valor is None else Decimal(str(valor))
            if a is None or b is None:
                if a != b:
                    diferencias.append([clave, campo, str(a), str(b)])
                continue
            delta = abs(a-b)
            if campo in ratios:
                max_ratio = max(max_ratio, delta)
            else:
                max_exacta = max(max_exacta, delta)
            if delta > (Decimal('0.00000001') if campo in ratios else Decimal(0)):
                diferencias.append([clave, campo, str(a), str(b), str(delta)])
    assert len(sql) == len(dax) == 624, (len(sql), len(dax))
    return {
        'filasSQL': len(sql), 'filasDAX': len(dax), 'indicadoresPorFila': len(columnas)-2,
        'clavesDiferentes': len(faltantes), 'diferencias': len(diferencias),
        'maxDiferenciaExacta': str(max_exacta), 'maxDiferenciaRatio': str(max_ratio),
        'toleranciaRatio': '0.00000001', 'primerasDiferencias': diferencias[:10],
    }


if __name__ == '__main__':
    resultado = comparar(sys.argv[1], sys.argv[2])
    print(json.dumps(resultado, ensure_ascii=False, indent=2))
    sys.exit(1 if resultado['clavesDiferentes'] or resultado['diferencias'] else 0)
