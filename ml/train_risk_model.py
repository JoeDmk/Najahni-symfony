from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import parse_qs, unquote, urlparse

import joblib
import numpy as np
import pandas as pd
import pymysql
from dotenv import dotenv_values
from sklearn.ensemble import GradientBoostingClassifier
from sklearn.metrics import accuracy_score, precision_score, recall_score
from sklearn.model_selection import train_test_split


BASE_DIR = Path(__file__).resolve().parent
PROJECT_DIR = BASE_DIR.parent
ENV_PATH = PROJECT_DIR / ".env"
MODEL_PATH = BASE_DIR / "risk_model.pkl"
META_PATH = BASE_DIR / "model_meta.json"
FEATURE_NAMES = ["sector", "amount", "duration_days", "economic_score", "offer_count"]


def load_database_url() -> str:
    values = dotenv_values(ENV_PATH)
    database_url = str(values.get("DATABASE_URL", "")).strip().strip('"').strip("'")
    if not database_url:
        raise RuntimeError("DATABASE_URL introuvable dans le fichier .env")

    return database_url


def parse_mysql_connection(database_url: str) -> dict[str, object]:
    parsed = urlparse(database_url)
    if parsed.scheme not in {"mysql", "mysql2"}:
        raise RuntimeError(f"Scheme DATABASE_URL non supporte: {parsed.scheme}")

    params = parse_qs(parsed.query)
    charset = params.get("charset", ["utf8mb4"])[0]

    return {
        "host": parsed.hostname or "127.0.0.1",
        "port": parsed.port or 3306,
        "user": unquote(parsed.username or "root"),
        "password": unquote(parsed.password or ""),
        "database": parsed.path.lstrip("/"),
        "charset": charset,
        "cursorclass": pymysql.cursors.DictCursor,
    }


def encode_sector(sector: str | None) -> int:
    normalized = (sector or "").strip().lower()
    if not normalized:
        return 6

    mapping = {
        1: ["tech", "technologie", "software", "saas", "digital", "data", "intelligence artificielle", "ia"],
        2: ["health", "sante", "med", "medical", "biotech", "diagnostic", "pharma"],
        3: ["agri", "agriculture", "food", "agro", "alimentaire"],
        4: ["energie", "energy", "renewable", "clean", "solar", "climate", "environnement"],
        5: ["fin", "finance", "service", "tourisme", "education", "commerce", "retail"],
        6: ["industrie", "industrial", "logistique", "logistics", "textile", "manufacturing"],
    }

    for encoded, keywords in mapping.items():
        if any(keyword in normalized for keyword in keywords):
            return encoded

    return 6


def fetch_training_rows(connection: pymysql.connections.Connection) -> pd.DataFrame:
    query = """
        SELECT
            opp.id,
            p.secteur AS sector_name,
            CAST(opp.target_amount AS DECIMAL(15, 2)) AS amount,
            GREATEST(COALESCE(DATEDIFF(opp.deadline, opp.created_at), 90), 1) AS duration_days,
            COALESCE(opp.risk_score, 50) AS economic_score,
            COUNT(o.id) AS offer_count,
            CASE
                WHEN opp.status = 'FUNDED' THEN 1
                WHEN COALESCE(SUM(CASE WHEN o.paid = 1 THEN CAST(o.proposed_amount AS DECIMAL(15, 2)) ELSE 0 END), 0) >= CAST(opp.target_amount AS DECIMAL(15, 2)) THEN 1
                ELSE 0
            END AS funded
        FROM investment_opportunity opp
        LEFT JOIN projet p ON p.id = opp.project_id
        LEFT JOIN investment_offer o ON o.opportunity_id = opp.id
        GROUP BY
            opp.id,
            p.secteur,
            opp.target_amount,
            opp.deadline,
            opp.created_at,
            opp.risk_score,
            opp.status
        ORDER BY opp.id ASC
    """

    with connection.cursor() as cursor:
        cursor.execute(query)
        rows = cursor.fetchall()

    frame = pd.DataFrame(rows)
    if frame.empty:
        return pd.DataFrame(columns=FEATURE_NAMES + ["funded"])

    frame["sector"] = frame["sector_name"].apply(encode_sector)
    frame["amount"] = pd.to_numeric(frame["amount"], errors="coerce").fillna(0.0)
    frame["duration_days"] = pd.to_numeric(frame["duration_days"], errors="coerce").fillna(90).clip(lower=1)
    frame["economic_score"] = pd.to_numeric(frame["economic_score"], errors="coerce").fillna(50.0).clip(lower=0, upper=100)
    frame["offer_count"] = pd.to_numeric(frame["offer_count"], errors="coerce").fillna(0).clip(lower=0)
    frame["funded"] = pd.to_numeric(frame["funded"], errors="coerce").fillna(0).clip(lower=0, upper=1).astype(int)

    return frame[FEATURE_NAMES + ["funded"]]


def build_synthetic_dataset(size: int = 200, seed: int = 42) -> pd.DataFrame:
    rng = np.random.default_rng(seed)
    sector = rng.integers(1, 7, size=size)
    amount = rng.uniform(5000, 100000, size=size).round(2)
    duration_days = rng.integers(30, 366, size=size)
    economic_score = rng.uniform(20, 80, size=size).round(2)
    offer_count = rng.integers(0, 9, size=size)

    logits = (
        -1.35
        + offer_count * 0.42
        + (60 - economic_score) * 0.04
        + (70000 - amount) / 135000
        + (220 - duration_days) / 320
    )
    probabilities = 1 / (1 + np.exp(-logits))
    funded = (rng.random(size=size) < np.clip(probabilities, 0.05, 0.95)).astype(int)

    return pd.DataFrame(
        {
            "sector": sector,
            "amount": amount,
            "duration_days": duration_days,
            "economic_score": economic_score,
            "offer_count": offer_count,
            "funded": funded,
        }
    )


def ensure_trainable_dataset(frame: pd.DataFrame) -> tuple[pd.DataFrame, bool]:
    synthetic_used = False
    if len(frame) < 10 or frame["funded"].nunique() < 2:
        synthetic_used = True
        print("WARNING: dataset insuffisant pour un apprentissage fiable. 200 lignes synthetiques ont ete ajoutees.")
        synthetic = build_synthetic_dataset()
        frame = pd.concat([frame, synthetic], ignore_index=True)

    return frame, synthetic_used


def main() -> None:
    database_url = load_database_url()
    connection_params = parse_mysql_connection(database_url)
    connection = pymysql.connect(**connection_params)

    try:
        dataset = fetch_training_rows(connection)
    finally:
        connection.close()

    dataset, synthetic_used = ensure_trainable_dataset(dataset)

    X = dataset[FEATURE_NAMES]
    y = dataset["funded"].astype(int)
    stratify = y if y.nunique() > 1 else None

    X_train, X_test, y_train, y_test = train_test_split(
        X,
        y,
        test_size=0.2,
        random_state=42,
        stratify=stratify,
    )

    model = GradientBoostingClassifier(random_state=42)
    model.fit(X_train, y_train)

    predictions = model.predict(X_test)
    accuracy = accuracy_score(y_test, predictions)
    precision = precision_score(y_test, predictions, zero_division=0)
    recall = recall_score(y_test, predictions, zero_division=0)

    print(f"Rows used: {len(dataset)}")
    print(f"Accuracy: {accuracy:.4f}")
    print(f"Precision: {precision:.4f}")
    print(f"Recall: {recall:.4f}")

    joblib.dump(model, MODEL_PATH)

    metadata = {
        "feature_names": FEATURE_NAMES,
        "training_date": datetime.now(timezone.utc).isoformat(),
        "accuracy": round(float(accuracy), 4),
        "precision": round(float(precision), 4),
        "recall": round(float(recall), 4),
        "synthetic_data": synthetic_used,
        "synthetic_data_used": synthetic_used,
        "sample_count": int(len(dataset)),
    }
    META_PATH.write_text(json.dumps(metadata, indent=2), encoding="utf-8")

    print(f"Model exported to: {MODEL_PATH}")
    print(f"Metadata exported to: {META_PATH}")


if __name__ == "__main__":
    main()