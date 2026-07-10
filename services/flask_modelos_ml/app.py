from datetime import datetime, timedelta
import math
import os
from pathlib import Path
import time

from flask import Flask, jsonify, request
import joblib
import mysql.connector
import numpy as np
import pandas as pd
from sklearn.ensemble import ExtraTreesRegressor, RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score


app = Flask(__name__)


ARTIFACT_DIR = Path(os.getenv("MODELOS_ML_ARTIFACT_DIR", "/usr/src/app/modelos_ml_artifacts"))
ARTIFACT_DIR.mkdir(parents=True, exist_ok=True)

HORIZONS = {
    "24h": {"hours": 24, "step_hours": 1, "label": "24 horas"},
    "72h": {"hours": 72, "step_hours": 3, "label": "72 horas"},
    "7d": {"hours": 24 * 7, "step_hours": 6, "label": "7 dias"},
    "30d": {"hours": 24 * 30, "step_hours": 24, "label": "30 dias"},
}

WINDOWS = {
    "7d": 7,
    "30d": 30,
    "90d": 90,
    "all": None,
}

NUMERIC_FIELDS = ["temperatura", "ph", "oxigeno_disuelto", "ion_nitrato"]
NORMALIZATION_VERSION = "nitrogen-domain-v3-created-at"
NITRATE_RAW_SENSOR_THRESHOLD = 1000.0
NITRATE_DOMAIN_MAX = 80.0
MODEL_RETRAIN_ROW_DELTA = 500

MODEL_SPECS = [
    {
        "code": "nitrogen_nitrate_projection",
        "field": "ion_nitrato",
        "name": "Ion Nitrato",
        "unit": "mg/L eq.",
        "color": "#0ea5e9",
        "algorithm": "RandomForestRegressor",
    },
    {
        "code": "dissolved_oxygen_projection",
        "field": "oxigeno_disuelto",
        "name": "Oxigeno disuelto",
        "unit": "mg/L",
        "color": "#16a34a",
        "algorithm": "ExtraTreesRegressor",
    },
]


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


def fetch_measurements(piscigranja_id=None, piscina_id=None, window_key="all"):
    filters = ["pa.deleted_at IS NULL"]
    params = []

    if text_filter(piscina_id):
        filters.append("pa.piscina_id = %s")
        params.append(piscina_id)

    if text_filter(piscigranja_id):
        filters.append("pi.piscigranja_id = %s")
        params.append(piscigranja_id)

    where_sql = " AND ".join(filters)
    timestamp_sql = "pa.created_at"

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
            ORDER BY measured_at ASC
            """,
            params,
        )
        rows = cursor.fetchall()

    df = pd.DataFrame(rows)
    if df.empty:
        return df, max_ts

    df["measured_at"] = pd.to_datetime(df["measured_at"])
    for field in NUMERIC_FIELDS:
        df[field] = pd.to_numeric(df[field], errors="coerce")

    return df.dropna(subset=["measured_at"]).sort_values("measured_at"), max_ts


def format_ts(value):
    if isinstance(value, pd.Timestamp):
        value = value.to_pydatetime()
    if isinstance(value, datetime):
        return value.isoformat(timespec="seconds")
    return str(value)


def display_label(value):
    if isinstance(value, pd.Timestamp):
        value = value.to_pydatetime()
    if isinstance(value, datetime):
        return value.strftime("%d/%m %H:%M")
    return str(value)


def safe_float(value, digits=3):
    if value is None:
        return None
    try:
        number = float(value)
    except (TypeError, ValueError):
        return None
    if not math.isfinite(number):
        return None
    return round(number, digits)


def prepare_model_frame(df):
    prepared = df.copy()
    normalization = {
        "version": NORMALIZATION_VERSION,
        "rows_received": int(prepared.shape[0]),
        "ion_nitrato": {
            "source_field": "ion_nitrato",
            "model_field": "ion_nitrato",
            "unit": "mg/L eq.",
            "raw_sensor_threshold": NITRATE_RAW_SENSOR_THRESHOLD,
            "domain_min": 0.0,
            "domain_max": NITRATE_DOMAIN_MAX,
        },
    }

    if "ion_nitrato" in prepared:
        raw = prepared["ion_nitrato"].astype(float)
        converted = raw.copy()
        sensor_scale = converted > NITRATE_RAW_SENSOR_THRESHOLD
        converted.loc[sensor_scale] = converted.loc[sensor_scale] / 1000.0
        below_domain = converted < 0
        above_domain = converted > NITRATE_DOMAIN_MAX
        prepared["ion_nitrato_raw"] = raw
        prepared["ion_nitrato"] = converted.clip(lower=0.0, upper=NITRATE_DOMAIN_MAX)
        normalization["ion_nitrato"].update(
            {
                "sensor_scale_converted": int(sensor_scale.sum()),
                "domain_clipped_low": int(below_domain.sum()),
                "domain_clipped_high": int(above_domain.sum()),
                "latest_raw": safe_float(raw.dropna().iloc[-1], 6) if raw.dropna().shape[0] else None,
                "latest_prepared": safe_float(prepared["ion_nitrato"].dropna().iloc[-1], 6)
                if prepared["ion_nitrato"].dropna().shape[0]
                else None,
            }
        )

    prepared.attrs["normalization"] = normalization
    return prepared


def time_features(dates, origin):
    rows = []
    for item in dates:
        if isinstance(item, pd.Timestamp):
            item = item.to_pydatetime()
        hours = max((item - origin).total_seconds() / 3600, 0.0)
        hour_of_day = item.hour + item.minute / 60
        day_of_week = item.weekday()
        day_of_year = int(item.strftime("%j"))
        rows.append(
            [
                hours,
                math.log1p(hours),
                math.sin(2 * math.pi * hour_of_day / 24),
                math.cos(2 * math.pi * hour_of_day / 24),
                math.sin(2 * math.pi * day_of_week / 7),
                math.cos(2 * math.pi * day_of_week / 7),
                math.sin(2 * math.pi * day_of_year / 366),
                math.cos(2 * math.pi * day_of_year / 366),
            ]
        )
    return np.asarray(rows, dtype=float)


def model_for_spec(spec):
    rows_leaf = 8
    if spec["algorithm"] == "ExtraTreesRegressor":
        return ExtraTreesRegressor(
            n_estimators=90,
            min_samples_leaf=rows_leaf,
            random_state=42,
            n_jobs=-1,
        )
    return RandomForestRegressor(
        n_estimators=80,
        min_samples_leaf=rows_leaf,
        random_state=42,
        n_jobs=-1,
    )


def target_bounds(values, field, latest_value):
    values = pd.Series(values).dropna().astype(float)
    if values.empty:
        return 0.0, 1.0

    low = float(values.quantile(0.005))
    high = float(values.quantile(0.995))

    if field == "oxigeno_disuelto":
        return 0.0, min(max(high, latest_value or 0, 8.0), 16.0)
    if field == "ph":
        return 0.0, 14.0
    if field == "temperatura":
        return 0.0, 42.0
    if field == "ion_nitrato":
        return 0.0, min(max(high, latest_value or 0, 5.0), NITRATE_DOMAIN_MAX)

    return 0.0, max(high, latest_value or 0, 1.0)


def artifact_path(code):
    return ARTIFACT_DIR / f"{code}.joblib"


def data_fingerprint(data, field):
    return {
        "field": field,
        "rows": int(data.shape[0]),
        "first_timestamp": format_ts(data["measured_at"].iloc[0]),
        "last_timestamp": format_ts(data["measured_at"].iloc[-1]),
        "latest_value": safe_float(data[field].iloc[-1], 6),
        "normalization_version": NORMALIZATION_VERSION,
    }


def train_or_load_model(df, spec, force_retrain=False):
    field = spec["field"]
    data = df[["measured_at", field]].dropna().copy()
    data = data.sort_values("measured_at")
    if data.shape[0] < 20:
        return None, {"status": "sin_datos", "message": "No hay suficientes datos para entrenar."}

    fingerprint = data_fingerprint(data, field)
    path = artifact_path(spec["code"])
    if path.exists() and not force_retrain:
        artifact = joblib.load(path)
        cached_fingerprint = artifact.get("fingerprint", {})
        row_delta = abs(int(fingerprint["rows"]) - int(cached_fingerprint.get("rows", 0)))
        retrain_threshold = max(MODEL_RETRAIN_ROW_DELTA, int(fingerprint["rows"] * 0.05))
        compatible_cache = (
            cached_fingerprint.get("field") == field
            and cached_fingerprint.get("normalization_version") == NORMALIZATION_VERSION
            and row_delta < retrain_threshold
        )
        if artifact.get("fingerprint") == fingerprint or compatible_cache:
            artifact["fingerprint_current"] = fingerprint
            artifact["latest_actual"] = float(data[field].astype(float).iloc[-1])
            artifact["loaded_from_cache"] = True
            artifact["cache_row_delta"] = row_delta
            return artifact, artifact["metadata"]

    start = time.time()
    origin = data["measured_at"].iloc[0].to_pydatetime()
    dates = [item.to_pydatetime() for item in data["measured_at"]]
    y_raw = data[field].astype(float).to_numpy()
    latest_value = float(y_raw[-1])
    low, high = target_bounds(y_raw, field, latest_value)
    y = np.clip(y_raw, low, high)
    x = time_features(dates, origin)
    split = max(10, int(len(y) * 0.8))
    if len(y) - split < 5:
        split = max(1, len(y) - 5)

    holdout_model = model_for_spec(spec)
    holdout_model.fit(x[:split], y[:split])
    holdout_pred = holdout_model.predict(x[split:])

    model = model_for_spec(spec)
    model.fit(x, y)
    train_pred = model.predict(x)
    metrics = regression_metrics(y[split:], holdout_pred)

    metadata = {
        "status": "trained",
        "model_code": spec["code"],
        "field": field,
        "algorithm": spec["algorithm"],
        "trained_at": datetime.utcnow().isoformat(timespec="seconds") + "Z",
        "training_rows": int(len(y)),
        "holdout_rows": int(len(y) - split),
        "target_bounds": {"min": safe_float(low, 6), "max": safe_float(high, 6)},
        "metrics": metrics,
        "training_seconds": safe_float(time.time() - start, 3),
        "artifact_path": str(path),
    }
    artifact = {
        "model": model,
        "origin": origin,
        "fingerprint": fingerprint,
        "metadata": metadata,
        "latest_training_prediction": float(train_pred[-1]),
        "latest_actual": latest_value,
    }
    joblib.dump(artifact, path)
    artifact["loaded_from_cache"] = False
    return artifact, metadata


def regression_metrics(y_true, y_pred):
    if len(y_true) == 0:
        return {}
    mse = mean_squared_error(y_true, y_pred)
    metrics = {
        "mae": safe_float(mean_absolute_error(y_true, y_pred), 6),
        "rmse": safe_float(math.sqrt(mse), 6),
    }
    if len(y_true) > 1:
        metrics["r2"] = safe_float(r2_score(y_true, y_pred), 6)
    return metrics


def future_dates(start, horizon):
    count = max(1, int(math.ceil(horizon["hours"] / horizon["step_hours"])))
    return [start + timedelta(hours=horizon["step_hours"] * index) for index in range(1, count + 1)]


def historical_points(df, field):
    data = df[["measured_at", field]].dropna()
    points = []
    for row in data.itertuples(index=False):
        point = {
            "timestamp": format_ts(row.measured_at),
            "label": display_label(row.measured_at),
            "value": safe_float(getattr(row, field), 6),
        }
        if field == "ion_nitrato" and "ion_nitrato_raw" in df.columns:
            raw_value = df.loc[df["measured_at"] == row.measured_at, "ion_nitrato_raw"]
            if not raw_value.empty:
                point["raw_value"] = safe_float(raw_value.iloc[-1], 6)
        points.append(point)
    return points


def forecast_points(artifact, spec, horizon, start):
    dates = future_dates(start, horizon)
    raw = artifact["model"].predict(time_features(dates, artifact["origin"]))
    raw_start = float(artifact["model"].predict(time_features([start], artifact["origin"]))[0])
    latest_actual = artifact["latest_actual"]
    max_hour = max(horizon["hours"], 1)
    low = artifact["metadata"]["target_bounds"]["min"]
    high = artifact["metadata"]["target_bounds"]["max"]
    max_delta = max_step_delta(spec["field"], latest_actual, horizon["step_hours"])
    previous = float(latest_actual)
    forecast = []

    for dt, value in zip(dates, raw):
        hour = (dt - start).total_seconds() / 3600
        blend = min(1.0, max(0.0, hour / max_hour)) ** 1.35
        anchored = float(latest_actual) + (float(value) - raw_start) * blend
        bounded = min(max(anchored, low), high)
        bounded = min(max(bounded, previous - max_delta), previous + max_delta)
        previous = bounded
        forecast.append(
            {
                "timestamp": format_ts(dt),
                "label": display_label(dt),
                "value": safe_float(bounded, 6),
            }
        )
    return forecast


def max_step_delta(field, latest_value, step_hours):
    hours_factor = max(float(step_hours), 1.0)
    if field == "oxigeno_disuelto":
        return max(0.25, abs(float(latest_value)) * 0.08) * (hours_factor / 3.0)
    if field == "ion_nitrato":
        return min(2.0, max(0.3, abs(float(latest_value)) * 0.2)) * (hours_factor / 3.0)
    return max(0.5, abs(float(latest_value)) * 0.1) * (hours_factor / 3.0)


def build_line_chart(historical, forecast, title, unit, color):
    last_real = historical[-1:] if historical else []
    return {
        "tooltip": {"trigger": "axis", "textStyle": {"fontSize": 12}},
        "legend": {"top": 10},
        "dataZoom": [
            {"type": "inside", "xAxisIndex": [0]},
            {"type": "slider", "height": 22, "bottom": 12},
        ],
        "grid": {"top": 64, "bottom": 58, "left": 64, "right": 40},
        "xAxis": {"type": "time"},
        "yAxis": [{"type": "value", "name": unit, "scale": True}],
        "series": [
            {
                "name": "Real",
                "type": "line",
                "smooth": False,
                "showSymbol": False,
                "sampling": "lttb",
                "data": [[item["timestamp"], item["value"]] for item in historical],
                "lineStyle": {"width": 2, "color": color},
            },
            {
                "name": "Proyeccion",
                "type": "line",
                "smooth": True,
                "showSymbol": False,
                "data": [[item["timestamp"], item["value"]] for item in forecast],
                "lineStyle": {"width": 3, "type": "dashed", "color": "#f59e0b"},
            },
            {
                "name": "Ultimo real",
                "type": "scatter",
                "symbolSize": 9,
                "data": [[item["timestamp"], item["value"]] for item in last_real],
                "itemStyle": {"color": "#111827"},
            },
        ],
    }


def project_model(df, spec, horizon, force_retrain=False):
    artifact, metadata = train_or_load_model(df, spec, force_retrain=force_retrain)
    historical = historical_points(df, spec["field"])
    if artifact is None:
        current = historical[-1]["value"] if historical else None
        return {
            "code": spec["code"],
            "field": spec["field"],
            "name": spec["name"],
            "unit": spec["unit"],
            "status": "sin_datos",
            "source": "flask_sismapiscis.parametro_aguas",
            "engine": f"Python {spec['algorithm']}",
            "asset_id": None,
            "message": metadata.get("message", "No hay datos suficientes."),
            "current_value": current,
            "historical": historical,
            "forecast": [],
            "chart": build_line_chart(historical, [], spec["name"], spec["unit"], spec["color"]),
            "lifecycle": metadata,
        }

    start = pd.to_datetime(df["measured_at"].max()).to_pydatetime()
    forecast = forecast_points(artifact, spec, horizon, start)
    current = historical[-1]["value"] if historical else artifact.get("latest_actual")
    loaded = "cache" if artifact.get("loaded_from_cache") else "entrenado"

    return {
        "code": spec["code"],
        "field": spec["field"],
        "name": spec["name"],
        "unit": spec["unit"],
        "status": "entrenado",
        "source": "flask_sismapiscis.parametro_aguas",
        "engine": f"Python {spec['algorithm']}",
        "asset_id": metadata.get("artifact_path"),
        "version": metadata.get("trained_at"),
        "message": f"Modelo {loaded} con todos los datos disponibles de la base.",
        "current_value": safe_float(current, 6),
        "mae": metadata.get("metrics", {}).get("mae"),
        "metrics": metadata.get("metrics", {}),
        "historical": historical,
        "forecast": forecast,
        "chart": build_line_chart(historical, forecast, spec["name"], spec["unit"], spec["color"]),
        "lifecycle": metadata,
    }


def nitrate_pressure(value):
    if value is None:
        return None
    value = max(float(value), 0.0)
    if value <= 1:
        return value * 12
    if value <= 50:
        return 12 + (value - 1) / 49 * 28
    if value <= 1000:
        return 40 + (value - 50) / 950 * 35
    return min(100, 75 + math.log10(value / 1000 + 1) * 25)


def oxygen_pressure(value):
    if value is None:
        return None
    value = float(value)
    if value >= 7:
        return 5
    if value >= 5:
        return 5 + (7 - value) / 2 * 30
    if value >= 3:
        return 35 + (5 - value) / 2 * 45
    return 100


def pressure_value(nitrate, oxygen):
    items = [nitrate_pressure(nitrate), oxygen_pressure(oxygen)]
    items = [item for item in items if item is not None]
    if not items:
        return None
    return sum(items) / len(items)


def build_balance_model(df, nitrate_model, oxygen_model):
    merged = df[["measured_at", "ion_nitrato", "oxigeno_disuelto"]].dropna().copy()
    historical = [
        {
            "timestamp": format_ts(row.measured_at),
            "label": display_label(row.measured_at),
            "value": safe_float(pressure_value(row.ion_nitrato, row.oxigeno_disuelto), 4),
        }
        for row in merged.itertuples(index=False)
    ]
    count = min(len(nitrate_model.get("forecast", [])), len(oxygen_model.get("forecast", [])))
    forecast = []
    for index in range(count):
        n_item = nitrate_model["forecast"][index]
        o_item = oxygen_model["forecast"][index]
        forecast.append(
            {
                "timestamp": n_item["timestamp"],
                "label": n_item["label"],
                "value": safe_float(pressure_value(n_item["value"], o_item["value"]), 4),
            }
        )

    current_value = historical[-1]["value"] if historical else None
    return {
        "code": "water_nitrogen_balance_index",
        "field": "water_nitrogen_balance_index",
        "name": "Equilibrio nitrogenado del agua",
        "unit": "0-100",
        "status": "calculado",
        "source": "flask_sismapiscis.modelos_ml",
        "engine": "Indice derivado de modelos entrenados",
        "asset_id": None,
        "message": "Integra la presion del Ion Nitrato y la respuesta del Oxigeno Disuelto.",
        "current_value": current_value,
        "historical": historical,
        "forecast": forecast,
        "chart": build_line_chart(historical, forecast, "Equilibrio nitrogenado del agua", "0-100", "#7c3aed"),
        "lifecycle": {
            "status": "derived_from_models",
            "inputs": [nitrate_model.get("code"), oxygen_model.get("code")],
        },
    }


def build_combined_chart(models):
    series = []
    y_axis = []
    colors = ["#0ea5e9", "#16a34a", "#7c3aed"]
    for index, model in enumerate(models):
        y_axis.append(
            {
                "type": "value",
                "name": model["unit"],
                "scale": True,
                "position": "right" if index % 2 else "left",
                "offset": max(0, index - 1) * 52,
            }
        )
        color = colors[index % len(colors)]
        series.extend(
            [
                {
                    "name": f"{model['name']} real",
                    "type": "line",
                    "showSymbol": False,
                    "sampling": "lttb",
                    "yAxisIndex": index,
                    "data": [[item["timestamp"], item["value"]] for item in model.get("historical", [])],
                    "lineStyle": {"width": 2, "color": color, "opacity": 0.78},
                },
                {
                    "name": f"{model['name']} proyeccion",
                    "type": "line",
                    "showSymbol": False,
                    "smooth": True,
                    "yAxisIndex": index,
                    "data": [[item["timestamp"], item["value"]] for item in model.get("forecast", [])],
                    "lineStyle": {"width": 3, "type": "dashed", "color": color},
                },
            ]
        )

    return {
        "tooltip": {"trigger": "axis", "textStyle": {"fontSize": 12}},
        "legend": {"top": 8, "type": "scroll"},
        "dataZoom": [
            {"type": "inside", "xAxisIndex": [0]},
            {"type": "slider", "height": 22, "bottom": 12},
        ],
        "grid": {"top": 72, "bottom": 58, "left": 64, "right": 116},
        "xAxis": {"type": "time"},
        "yAxis": y_axis,
        "series": series,
    }


def quality_report(df):
    report = {}
    for field in NUMERIC_FIELDS:
        values = df[field].dropna()
        if values.empty:
            report[field] = {"records": 0, "missing": int(df[field].isna().sum())}
            continue
        q1 = float(values.quantile(0.25))
        q3 = float(values.quantile(0.75))
        iqr = q3 - q1
        low = q1 - 1.5 * iqr
        high = q3 + 1.5 * iqr
        report[field] = {
            "records": int(values.shape[0]),
            "missing": int(df[field].isna().sum()),
            "min": safe_float(values.min(), 6),
            "max": safe_float(values.max(), 6),
            "p50": safe_float(values.quantile(0.50), 6),
            "outliers_iqr": int(((values < low) | (values > high)).sum()),
        }
    return report


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
    window_key = request.args.get("ventana", "all")
    horizon = HORIZONS.get(horizon_key, HORIZONS["72h"])
    if window_key not in WINDOWS:
        window_key = "all"

    df, max_ts = fetch_measurements(
        piscigranja_id=request.args.get("piscigranja_id"),
        piscina_id=request.args.get("piscina_id"),
        window_key=window_key,
    )
    force_retrain = request.args.get("retrain") in ("1", "true", "si", "yes")

    if df.empty:
        return jsonify(
            {
                "status": "sin_datos",
                "message": "No hay mediciones disponibles para los filtros seleccionados.",
                "backend_engine": "Flask MLOps Python",
                "generated_at": datetime.utcnow().isoformat(timespec="seconds") + "Z",
                "models": [],
            }
        )

    df = prepare_model_frame(df)
    nitrate_model = project_model(df, MODEL_SPECS[0], horizon, force_retrain=force_retrain)
    oxygen_model = project_model(df, MODEL_SPECS[1], horizon, force_retrain=force_retrain)
    balance_model = build_balance_model(df, nitrate_model, oxygen_model)
    models = [nitrate_model, oxygen_model, balance_model]
    latest = df.tail(1).iloc[0]
    total_historical_points = sum(len(model.get("historical", [])) for model in models)
    total_forecast_points = sum(len(model.get("forecast", [])) for model in models)

    return jsonify(
        {
            "status": "ok",
            "backend_engine": "Flask MLOps Python",
            "backend_url": "http://flask_sismapiscis:5000",
            "legacy_flask_used": False,
            "generated_at": datetime.utcnow().isoformat(timespec="seconds") + "Z",
            "anchor_timestamp": format_ts(max_ts),
            "filters": {
                "piscigranja_id": request.args.get("piscigranja_id", "T"),
                "piscina_id": request.args.get("piscina_id", "T"),
                "horizonte": horizon_key,
                "horizonte_label": horizon["label"],
                "step_hours": horizon["step_hours"],
                "ventana": window_key,
                "ventana_label": "Todo el historico" if window_key == "all" else f"Ultimos {WINDOWS[window_key]} dias",
                "timestamp_field": "created_at",
            },
            "latest": {
                "timestamp": format_ts(latest["measured_at"]),
                "piscigranja": latest.get("piscigranja"),
                "piscina": latest.get("piscina"),
                "temperatura": safe_float(latest.get("temperatura")),
                "ph": safe_float(latest.get("ph")),
                "oxigeno_disuelto": safe_float(latest.get("oxigeno_disuelto")),
                "oxigeno_disuelto_unit": "mg/L",
                "ion_nitrato": safe_float(latest.get("ion_nitrato")),
                "ion_nitrato_raw": safe_float(latest.get("ion_nitrato_raw")),
                "ion_nitrato_unit": "mg/L eq.",
            },
            "summary": {
                "samples": int(df.shape[0]),
                "from": format_ts(df["measured_at"].min()),
                "to": format_ts(df["measured_at"].max()),
                "available_models": sum(1 for model in models if model["status"] in ("entrenado", "calculado")),
                "historical_points": int(total_historical_points),
                "forecast_points": int(total_forecast_points),
                "training_rows": {
                    model["code"]: model.get("lifecycle", {}).get("training_rows")
                    for model in models
                    if model.get("lifecycle", {}).get("training_rows")
                },
            },
            "lifecycle": {
                "status": "completed",
                "artifact_dir": str(ARTIFACT_DIR),
                "force_retrain": force_retrain,
                "models": [model.get("lifecycle", {}) for model in models],
            },
            "quality": quality_report(df),
            "data_preparation": df.attrs.get("normalization", {}),
            "warnings": [
                "Las curvas combinan datos reales de la base con proyecciones entrenadas/ancladas al ultimo dato real.",
                "Ion Nitrato se normaliza a mg/L equivalente para evitar que lecturas crudas del sensor dominen el entrenamiento.",
            ],
            "traceability": {
                "source": "flask_sismapiscis:/api/modelos-ml/proyecciones",
                "data_table": "parametro_aguas",
                "uses_all_points": window_key == "all",
                "model_lifecycle": "fetch_db -> train_or_load_artifact -> holdout_metrics -> anchored_projection",
                "selected_models": [model["code"] for model in models],
                "projection_method": "supervised_time_features_with_latest_observation_anchor",
                "timestamp_field": "parametro_aguas.created_at",
            },
            "combined_chart": build_combined_chart(models),
            "models": models,
        }
    )


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)
