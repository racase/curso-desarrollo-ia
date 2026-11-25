"""Compara salida de db/query.sh y data JSON de dax_query_operations (Inline)."""
import json
import sys
from decimal import Decimal
from pathlib import Path


def comparar(sql_path, dax_path):
    lineas = Path(sql_path).read_text().splitlines()
    columnas = next(linea for linea in lineas if linea.startswith("Corte|Nivel|")).split("|")
    claves = ["Corte", "Nivel", "Obra", "Partida"]
    sql = {}
    for linea in lineas:
        if not linea.startswith(("2025-", "2026-")):
            continue
        valores = linea.split("|")
        # La clave compuesta de partida también contiene el separador de sqlcmd.
        if valores[1] == "PARTIDA":
            valores[3:5] = ["|".join(valores[3:5])]
        assert len(valores) == len(columnas), linea
        fila = dict(zip(columnas, valores))
        clave = tuple(fila[k] for k in claves)
        assert clave not in sql, clave
        sql[clave] = fila

    datos = json.loads(Path(dax_path).read_text())
    if "data" in datos:
        datos = datos["data"]
    assert not datos.get("isTruncated"), "La respuesta DAX está truncada"
    dax = {}
    for original in datos["rows"]:
        fila = {k.strip("[]"): v for k, v in original.items()}
        fila["Corte"] = fila["Corte"][:10]
        clave = tuple(fila[k] for k in claves)
        assert clave not in dax, clave
        dax[clave] = fila

    faltantes = sorted(set(sql) ^ set(dax))
    diferencias = []
    max_importe = Decimal(0)
    max_ratio = Decimal(0)
    for clave in sorted(set(sql) & set(dax)):
        for nombre in columnas[4:]:
            origen = None if sql[clave][nombre] == "NULL" else Decimal(sql[clave][nombre])
            valor = dax[clave].get(nombre)
            modelo = None if valor is None else Decimal(str(valor))
            ratio = nombre.endswith("Pct") or nombre == "AvanceGlobal"
            if origen is None or modelo is None:
                if origen != modelo:
                    diferencias.append([clave, nombre, str(origen), str(modelo)])
                continue
            delta = abs(origen - modelo)
            if ratio:
                max_ratio = max(max_ratio, delta)
            else:
                max_importe = max(max_importe, delta)
            if delta > (Decimal("0.00000001") if ratio else Decimal(0)):
                diferencias.append([clave, nombre, str(origen), str(modelo), str(delta)])

    resumen = {
        "filasSQL": len(sql), "filasDAX": len(dax),
        "clavesDiferentes": len(faltantes), "diferencias": len(diferencias),
        "maxDiferenciaImporte": str(max_importe), "maxDiferenciaRatio": str(max_ratio),
        "toleranciaRatio": "0.00000001", "primerasDiferencias": diferencias[:10],
    }
    assert len(sql) == len(dax) == 2442, resumen
    return resumen


if __name__ == "__main__":
    resultado = comparar(sys.argv[1], sys.argv[2])
    print(json.dumps(resultado, ensure_ascii=False, indent=2))
    sys.exit(1 if resultado["clavesDiferentes"] or resultado["diferencias"] else 0)
