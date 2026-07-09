from datetime import datetime, timedelta
import math
import os

from flask import Flask, jsonify, request
import mysql.connector
import numpy as np
import pandas as pd
from sklearn.linear_model import Ridge


app = Flask(__name__)


HORIZONS = {
    "24h": {"hours": 24, "label": "24 horas"},
    "72h": {"hours": 72, "label": "72 horas"},
    "7d": {"hours": 24 * 7, "label": "7 días"},
    "30d": {"hours": 24 * 30, "label": "30 días"},
}

WINDOWS = {
    "7d": 7,
    "30d": 30,
    "90d": 90,
    "all": None,
}


def db_connection():
    return mysql.connector.connect(
        host=os.getenv("DB_HOST"),
        port=int(os.getenv("DB_PORT", "3306")),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASSWORD"),
        database=os.getenv("DB_NAME"),
    )


def text_filter(value):
    if value in (None, "", "T", "all", "todos"):
        return None
    return value


def fetch_measurements(piscigranja_id=None, piscina_id=None, window_key="30d"):
    filters = ["pa.deleted_at IS NULL"]
    params = []

    if text_filter(piscina_id):
        filters.append("pa.piscina_id = %s")
        params.append(piscina_id)

    if text_filter(piscigranja_id):
        filters.append("pi.piscigranja_id = %s")
        params.append(piscigranja_id)

    where_sql = " AND ".join(filters)
    timestamp_sql = "COALESCE(pa.fecha_medicion, pa.created_at)"

    with db_connection() as db:
        cursor = db.cursor(dictionary=True)
        cursor.execute(
            f"""
            SELECT MAX({timestamp_sql}) AS max_ts
            FROM parametro_aguas pa
            LEFT JOIN piscinas pi ON pi.id = pa.piscina_id
            WHERE {where_sql}
            """,
            params,
        )
        max_row = cursor.fetchone() or {}
        max_ts = max_row.get("max_ts")

        if max_ts and WINDOWS.get(window_key) is not None:
            filters.append(f"{timestamp_sql} >= %s")
            params.append(max_ts - timedelta(days=WINDOWS[window_key]))
            where_sql = " AND ".join(filters)

        cursor.execute(
            f"""
            SELECT *
            FROM (
                SELECT
                    pa.id,
                    pa.piscina_id,
                    pi.nombre AS piscina,
                    pg.nombre AS piscigranja,
                    {timestamp_sql} AS measured_at,
                    pa.temperatura,
                    pa.ph,
                    pa.oxigeno_disuelto,
                    pa.ion_nitrato
                FROM parametro_aguas pa
                LEFT JOIN piscinas pi ON pi.id = pa.piscina_id
                LEFT JOIN piscigranjas pg ON pg.id = pi.piscigranja_id
                WHERE {where_sql}
                ORDER BY measured_at DESC
                LIMIT 1600
            ) recent_measurements
            ORDER BY measured_at ASC
            """,
            params,
        )
        rows = cursor.fetchall()

    df = pd.DataFrame(rows)
    if df.empty:
        return df, max_ts

    df["measured_at"] = pd.to_datetime(df["measured_at"])
    for field in ["temperatura", "ph", "oxigeno_disuelto", "ion_nitrato"]:
        df[field] = pd.to_numeric(df[field], errors="coerce")
    return df.dropna(subset=["measured_at"]).sort_values("measured_at"), max_ts


def format_ts(value):
    if isinstance(value, pd.Timestamp):
        value = value.to_pydatetime()
    if isinstance(value, datetime):
        return value.isoformat(timespec="minutes")
    return str(value)


def display_label(value):
    if isinstance(value, pd.Timestamp):
        value = value.to_pydatetime()
    if isinstance(value, datetime):
        return value.strftime("%d/%m %H:%M")
    return str(value)


def safe_float(value, digits=3):
    if value is None or (isinstance(value, float) and math.isnan(value)):
        return None
    try:
        return round(float(value), digits)
    except (TypeError, ValueError):
        return None


def median_step_hours(dates, horizon_hours):
    if len(dates) < 2:
        return 1 if horizon_hours <= 72 else 6
    diffs = pd.Series(dates).diff().dropna().dt.total_seconds().div(3600)
    step = float(diffs[diffs > 0].median()) if not diffs.empty else 1
    if not math.isfinite(step) or step <= 0:
        step = 1
    if horizon_hours >= 24 * 30:
        step = max(step, 12)
    elif horizon_hours >= 24 * 7:
        step = max(step, 6)
    return min(max(step, 1), 24)


def feature_matrix(dates, origin):
    hours = np.array([(dt - origin).total_seconds() / 3600 for dt in dates], dtype=float)
    hour_day = np.array([dt.hour + dt.minute / 60 for dt in dates], dtype=float)
    return np.column_stack(
        [
            hours,
            np.sin(2 * np.pi * hour_day / 24),
            np.cos(2 * np.pi * hour_day / 24),
        ]
    )


def project_series(df, field, name, unit, horizon_hours, color):
    data = df[["measured_at", field]].dropna().tail(360)
    if data.shape[0] < 3:
        return {
            "code": field,
            "name": name,
            "unit": unit,
            "status": "sin_datos",
            "message": "No hay datos suficientes para proyectar este parámetro.",
            "current_value": safe_float(data[field].iloc[-1]) if not data.empty else None,
            "forecast": [],
            "chart": build_line_chart([], [], name, unit, color),
        }

    origin = data["measured_at"].iloc[0].to_pydatetime()
    dates = [item.to_pydatetime() for item in data["measured_at"]]
    y = data[field].astype(float).to_numpy()
    model = Ridge(alpha=1.0)
    model.fit(feature_matrix(dates, origin), y)

    step = median_step_hours(data["measured_at"], horizon_hours)
    count = max(2, min(120, int(math.ceil(horizon_hours / step))))
    start = data["measured_at"].iloc[-1].to_pydatetime()
    future_dates = [start + timedelta(hours=step * idx) for idx in range(1, count + 1)]
    predicted = model.predict(feature_matrix(future_dates, origin))

    historical = [
        {"timestamp": format_ts(row.measured_at), "label": display_label(row.measured_at), "value": safe_float(getattr(row, field))}
        for row in data.tail(120).itertuples(index=False)
    ]
    forecast = [
        {"timestamp": format_ts(dt), "label": display_label(dt), "value": safe_float(value)}
        for dt, value in zip(future_dates, predicted)
    ]

    residuals = y - model.predict(feature_matrix(dates, origin))
    mae = float(np.mean(np.abs(residuals))) if len(residuals) else None

    return {
        "code": field,
        "name": name,
        "unit": unit,
        "status": "disponible",
        "message": "Proyección basada en la ventana reciente de mediciones.",
        "current_value": safe_float(y[-1]),
        "mae": safe_float(mae),
        "forecast": forecast,
        "chart": build_line_chart(historical, forecast, name, unit, color),
    }


def build_line_chart(historical, forecast, title, unit, color):
    labels = [item["label"] for item in historical] + [item["label"] for item in forecast]
    historical_values = [item["value"] for item in historical] + [None] * len(forecast)
    forecast_values = [None] * len(historical) + [item["value"] for item in forecast]
    tooltip_data = []

    for item in historical + forecast:
        tooltip_data.append(
            {
                "title": item["label"],
                "items": [
                    {
                        "field": title,
                        "label": title,
                        "value": f"{item['value']} {unit}" if item["value"] is not None else "Sin dato",
                        "unit": unit,
                    }
                ],
            }
        )

    return {
        "tooltip": {"trigger": "axis", "textStyle": {"fontSize": 12}, "data": tooltip_data},
        "legend": {"top": 10},
        "grid": {"top": 64, "bottom": 48, "left": 60, "right": 40},
        "xAxis": {"type": "category", "data": labels},
        "yAxis": [{"type": "value", "name": unit, "scale": True}],
        "series": [
            {
                "name": "Histórico",
                "type": "line",
                "smooth": True,
                "showSymbol": False,
                "data": historical_values,
                "lineStyle": {"width": 3, "color": color},
            },
            {
                "name": "Proyección",
                "type": "line",
                "smooth": True,
                "showSymbol": False,
                "data": forecast_values,
                "lineStyle": {"width": 3, "type": "dashed", "color": "#f59e0b"},
            },
        ],
    }


def nitrate_risk(value):
    if value is None:
        return None
    value = max(float(value), 0)
    if value <= 50:
        return min(25, value / 50 * 25)
    return min(100, 25 + (value - 50) / 150 * 75)


def oxygen_risk(value):
    if value is None:
        return None
    value = float(value)
    if value >= 7:
        return 0
    if value >= 5:
        return (7 - value) / 2 * 35
    if value >= 3:
        return 35 + (5 - value) / 2 * 45
    return 100


def build_risk_model(nitrate_model, oxygen_model):
    nitrate_forecast = nitrate_model.get("forecast", [])
    oxygen_forecast = oxygen_model.get("forecast", [])
    count = min(len(nitrate_forecast), len(oxygen_forecast))
    forecast = []
    for index in range(count):
        n_risk = nitrate_risk(nitrate_forecast[index]["value"])
        o_risk = oxygen_risk(oxygen_forecast[index]["value"])
        risks = [item for item in [n_risk, o_risk] if item is not None]
        risk = sum(risks) / len(risks) if risks else None
        forecast.append(
            {
                "timestamp": nitrate_forecast[index]["timestamp"],
                "label": nitrate_forecast[index]["label"],
                "value": safe_float(risk, 2),
            }
        )

    current_risks = [
        nitrate_risk(nitrate_model.get("current_value")),
        oxygen_risk(oxygen_model.get("current_value")),
    ]
    current_risks = [item for item in current_risks if item is not None]
    current_value = sum(current_risks) / len(current_risks) if current_risks else None

    return {
        "code": "indice_operativo",
        "name": "Índice operativo de calidad",
        "unit": "0-100",
        "status": "disponible" if forecast else "sin_datos",
        "message": "Integra tendencia de Ion Nitrato y Oxígeno Disuelto.",
        "current_value": safe_float(current_value, 2),
        "forecast": forecast,
        "chart": build_line_chart([], forecast, "Índice operativo de calidad", "0-100", "#7c3aed"),
    }


@app.route("/")
def hello():
    with db_connection() as db:
        cursor = db.cursor()
        cursor.execute("SELECT NOW()")
        result = cursor.fetchone()
    return f"Hello from Flask! DB time: {result[0]}"


@app.route("/api/modelos-ml/proyecciones")
def modelos_ml_proyecciones():
    horizon_key = request.args.get("horizonte", "72h")
    window_key = request.args.get("ventana", "30d")
    horizon = HORIZONS.get(horizon_key, HORIZONS["72h"])
    if window_key not in WINDOWS:
        window_key = "30d"

    df, max_ts = fetch_measurements(
        piscigranja_id=request.args.get("piscigranja_id"),
        piscina_id=request.args.get("piscina_id"),
        window_key=window_key,
    )

    if df.empty:
        return jsonify(
            {
                "status": "sin_datos",
                "message": "No hay mediciones disponibles para los filtros seleccionados.",
                "generated_at": datetime.utcnow().isoformat(timespec="seconds") + "Z",
                "models": [],
            }
        )

    nitrate = project_series(df, "ion_nitrato", "Ion Nitrato", "mg/L", horizon["hours"], "#0ea5e9")
    oxygen = project_series(df, "oxigeno_disuelto", "Oxígeno Disuelto", "mg/L", horizon["hours"], "#22c55e")
    risk = build_risk_model(nitrate, oxygen)

    latest = df.tail(1).iloc[0]
    models = [nitrate, oxygen, risk]

    return jsonify(
        {
            "status": "ok",
            "generated_at": datetime.utcnow().isoformat(timespec="seconds") + "Z",
            "anchor_timestamp": format_ts(max_ts),
            "filters": {
                "piscigranja_id": request.args.get("piscigranja_id", "T"),
                "piscina_id": request.args.get("piscina_id", "T"),
                "horizonte": horizon_key,
                "horizonte_label": horizon["label"],
                "ventana": window_key,
            },
            "latest": {
                "timestamp": format_ts(latest["measured_at"]),
                "piscigranja": latest.get("piscigranja"),
                "piscina": latest.get("piscina"),
                "temperatura": safe_float(latest.get("temperatura")),
                "ph": safe_float(latest.get("ph")),
                "oxigeno_disuelto": safe_float(latest.get("oxigeno_disuelto")),
                "ion_nitrato": safe_float(latest.get("ion_nitrato")),
            },
            "summary": {
                "samples": int(df.shape[0]),
                "from": format_ts(df["measured_at"].min()),
                "to": format_ts(df["measured_at"].max()),
                "available_models": sum(1 for model in models if model["status"] == "disponible"),
            },
            "models": models,
        }
    )


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)
