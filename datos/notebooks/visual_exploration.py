# %% [markdown]
# # Exploración visual de InfraData
#
# Ejecuta las celdas en VS Code con el intérprete de `.venv`. El mapa usa el
# resumen diario precalculado; la serie consulta solo el sensor seleccionado.

# %%
import ipywidgets as widgets
import plotly.graph_objects as go
from IPython.display import display

from infradata.db import connect
from infradata.exploration import course_completion, sensor_readings, sensors

# %% [markdown]
# ## Completitud diaria
#
# Una celda representa un día UTC y un sensor. El porcentaje es
# `lecturas recibidas / 288 × 100`; un día sin lecturas aparece como 0 %.
# Pasa el ratón por una celda para ver el porcentaje exacto.

# %%
with connect() as connection:
    sensor_codes, days, percentages = course_completion(connection)

heatmap = go.Figure(
    go.Heatmap(
        x=days,
        y=sensor_codes,
        z=percentages,
        zmin=0,
        zmax=100,
        colorscale="RdYlGn",
        colorbar={"title": "%"},
        hovertemplate="Sensor: %{y}<br>Día UTC: %{x}<br>Completitud: %{z:.2f} %<extra></extra>",
    )
)
heatmap.update_layout(
    title="Completitud diaria por sensor",
    xaxis_title="Día UTC",
    yaxis_title="Sensor",
    height=500,
)
display(heatmap)

# %% [markdown]
# ## Serie temporal
#
# Selecciona un sensor. Arrastra sobre el gráfico para ampliar un intervalo;
# haz doble clic para recuperar la vista completa.

# %%
with connect() as connection:
    available_sensors = sensors(connection)

sensor_picker = widgets.Dropdown(
    options=[(f"{sensor.code} · {sensor.name}", sensor.code) for sensor in available_sensors],
    description="Sensor:",
)


def show_sensor(code: str) -> None:
    sensor = next(sensor for sensor in available_sensors if sensor.code == code)
    with connect() as connection:
        readings = sensor_readings(connection, code)
    chart = go.Figure(
        go.Scattergl(
            x=[timestamp for timestamp, _ in readings],
            y=[value for _, value in readings],
            mode="lines",
            name=code,
        )
    )
    chart.update_layout(
        title=f"Serie temporal · {sensor.name} ({code})",
        xaxis_title="Fecha y hora UTC",
        yaxis_title=sensor.unit,
        dragmode="zoom",
        height=500,
    )
    display(chart)


display(sensor_picker, widgets.interactive_output(show_sensor, {"code": sensor_picker}))
