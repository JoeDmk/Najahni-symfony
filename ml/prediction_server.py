from __future__ import annotations

import json
from pathlib import Path

import joblib
import pandas as pd
from flask import Flask, jsonify, request


BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "risk_model.pkl"
META_PATH = BASE_DIR / "model_meta.json"

app = Flask(__name__)
model = None
model_meta: dict[str, object] = {}


def load_assets() -> None:
    global model, model_meta

    if MODEL_PATH.exists():
        model = joblib.load(MODEL_PATH)
    else:
        model = None

    if META_PATH.exists():
        model_meta = json.loads(META_PATH.read_text(encoding="utf-8"))
    else:
        model_meta = {}


def build_confidence(probability: float) -> str:
    if probability > 0.75 or probability < 0.25:
        return "high"
    if 0.4 <= probability <= 0.6:
        return "medium"
    return "low"


@app.get("/health")
def health() -> tuple[str, int] | tuple[dict[str, object], int]:
    return jsonify({"status": "ok", "model_loaded": model is not None}), 200


@app.post("/predict")
def predict() -> tuple[dict[str, object], int]:
    if model is None:
        return jsonify({"error": "model_not_loaded"}), 503

    payload = request.get_json(silent=True) or {}
    required = ["sector", "amount", "duration_days", "economic_score", "offer_count"]
    if not all(key in payload for key in required):
        return jsonify({"error": "invalid_payload"}), 400

    features = pd.DataFrame(
        [
            {
                "sector": int(payload["sector"]),
                "amount": float(payload["amount"]),
                "duration_days": int(payload["duration_days"]),
                "economic_score": float(payload["economic_score"]),
                "offer_count": int(payload["offer_count"]),
            }
        ]
    )

    probability = float(model.predict_proba(features)[0][1])

    return jsonify(
        {
            "success_probability": round(probability, 4),
            "confidence": build_confidence(probability),
            "model_trained_on": model_meta.get("training_date", "unknown"),
            "synthetic_data": bool(model_meta.get("synthetic_data", False)),
        }
    ), 200


if __name__ == "__main__":
    load_assets()
    app.run(host="127.0.0.1", port=5001)