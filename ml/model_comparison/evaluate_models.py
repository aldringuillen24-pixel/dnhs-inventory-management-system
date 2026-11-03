#!/usr/bin/env python3
"""Evaluate the comparison models on the held-out chronological test CSV."""

from __future__ import annotations

import argparse
import csv
import json
import math
from collections import defaultdict
from datetime import datetime
from pathlib import Path
from typing import Any

import joblib
from sklearn.metrics import mean_absolute_error, mean_squared_error

HERE = Path(__file__).resolve().parent
REQUIRED_COLUMNS = {
    "inventory_id",
    "category_id",
    "quantity_issued",
    "stock_out_date",
}
MODEL_NAMES = ("linear", "polynomial_degree_2")


def load_test_rows(
    path: Path, trained_models: dict[str, dict[str, Any]]
) -> list[dict[str, Any]]:
    if not path.is_file() or not path.stat().st_size:
        raise ValueError(f"Test CSV does not exist or is empty: {path}")

    rows = []
    seen_item_months: set[tuple[str, int]] = set()
    with path.open("r", newline="", encoding="utf-8-sig") as source:
        reader = csv.DictReader(source)
        if reader.fieldnames is None:
            raise ValueError("Test CSV must include a header row")
        reader.fieldnames = [name.strip().lower() for name in reader.fieldnames]
        missing = REQUIRED_COLUMNS - set(reader.fieldnames)
        if missing:
            raise ValueError(f"Test CSV is missing columns: {', '.join(sorted(missing))}")

        for line_number, row in enumerate(reader, start=2):
            try:
                inventory_id = int(row["inventory_id"])
                category_id = int(row["category_id"])
                actual = float(row["quantity_issued"])
                date = datetime.strptime(row["stock_out_date"].strip(), "%Y-%m-%d")
            except (TypeError, ValueError) as error:
                raise ValueError(f"Invalid identity, quantity, or date on CSV line {line_number}") from error
            if inventory_id < 1 or category_id < 1 or not math.isfinite(actual) or actual < 0:
                raise ValueError(f"Invalid identity or non-negative quantity on CSV line {line_number}")

            key = f"{inventory_id}:{category_id}"
            if key not in trained_models:
                raise ValueError(f"Test item {key} has no fitted training models")
            month = date.strftime("%Y-%m")
            offset = (
                (date.year - int(trained_models[key]["history_start_month"][:4])) * 12
                + date.month
                - int(trained_models[key]["history_start_month"][5:7])
            )
            if offset <= max(trained_models[key]["training_month_offsets"]):
                raise ValueError(f"Test month is not after training data on CSV line {line_number}")
            item_month = (key, offset)
            if item_month in seen_item_months:
                raise ValueError(f"Duplicate item-month row on CSV line {line_number}")
            seen_item_months.add(item_month)
            rows.append({
                "inventory_id": inventory_id,
                "category_id": category_id,
                "item_name": row.get("item_name", ""),
                "unit": row.get("unit", ""),
                "month": month,
                "month_offset": offset,
                "actual_quantity": actual,
                "model_key": key,
            })

    if not rows:
        raise ValueError("Test CSV contains no observations")
    if {row["model_key"] for row in rows} != set(trained_models):
        raise ValueError("Test items must match the trained item identities exactly")
    return rows


def evaluate(
    rows: list[dict[str, Any]], trained_models: dict[str, dict[str, Any]]
) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    predictions: list[dict[str, Any]] = []
    unit_values: dict[str, dict[str, dict[str, list[float]]]] = defaultdict(
        lambda: defaultdict(lambda: {"actual": [], "predicted": []})
    )
    item_values: dict[str, dict[str, dict[str, list[float]]]] = defaultdict(
        lambda: defaultdict(lambda: {"actual": [], "predicted": []})
    )

    for row in rows:
        item = trained_models[row["model_key"]]
        prediction_row = {key: value for key, value in row.items() if key != "model_key"}
        for model_name in MODEL_NAMES:
            estimator = item["models"].get(model_name)
            if estimator is None:
                raise ValueError(f"Trained model bundle is missing {model_name} for {row['model_key']}")
            # Demand cannot be negative; apply the same floor to both model predictions.
            prediction = max(0.0, float(estimator.predict([[row["month_offset"]]])[0]))
            prediction_row[f"{model_name}_prediction"] = prediction
            prediction_row[f"{model_name}_absolute_error"] = abs(row["actual_quantity"] - prediction)
            unit_values[row["unit"]][model_name]["actual"].append(row["actual_quantity"])
            unit_values[row["unit"]][model_name]["predicted"].append(prediction)
            item_values[row["model_key"]][model_name]["actual"].append(row["actual_quantity"])
            item_values[row["model_key"]][model_name]["predicted"].append(prediction)
        predictions.append(prediction_row)

    metrics_by_unit = {}
    per_item = []
    for unit, models in sorted(unit_values.items()):
        metrics_by_unit[unit] = {
            model_name: {
                "mae": mean_absolute_error(models[model_name]["actual"], models[model_name]["predicted"]),
                "rmse": math.sqrt(mean_squared_error(models[model_name]["actual"], models[model_name]["predicted"])),
            }
            for model_name in MODEL_NAMES
        }
    for item_key in sorted(item_values):
        first = next(row for row in rows if row["model_key"] == item_key)
        result = {
            "inventory_id": first["inventory_id"],
            "category_id": first["category_id"],
            "item_name": first["item_name"],
            "test_months": len(item_values[item_key][MODEL_NAMES[0]]["actual"]),
            "metrics": {},
        }
        for model_name in MODEL_NAMES:
            values = item_values[item_key][model_name]
            result["metrics"][model_name] = {
                "mae": mean_absolute_error(values["actual"], values["predicted"]),
                "rmse": math.sqrt(mean_squared_error(values["actual"], values["predicted"])),
            }
        per_item.append(result)

    summary = {
        "experiment": "linear_vs_polynomial_degree_2",
        "training_period": "2026-06 to 2026-08",
        "test_period": sorted({row["month"] for row in rows}),
        "test_item_count": len(per_item),
        "test_observation_count": len(rows),
        "prediction_floor": 0,
        "metrics_scope": "MAE/RMSE are reported per item and grouped by unit; metrics across different units are not combined.",
        "metrics_by_unit": metrics_by_unit,
        "per_item_metrics": per_item,
        "limitation": "Only one held-out month per item; treat results as exploratory.",
    }
    return predictions, summary


def main() -> None:
    parser = argparse.ArgumentParser(description="Evaluate isolated demand models on held-out data.")
    parser.add_argument("--models", type=Path, default=HERE / "trained_models.joblib")
    parser.add_argument("--test", type=Path, default=HERE / "test.csv")
    parser.add_argument("--predictions", type=Path, default=HERE / "predictions.csv")
    parser.add_argument("--summary", type=Path, default=HERE / "evaluation_results.json")
    args = parser.parse_args()

    if not args.models.is_file():
        parser.error(f"Trained model bundle does not exist: {args.models}")
    bundle = joblib.load(args.models)
    trained_models = bundle.get("models")
    if not isinstance(trained_models, dict) or not trained_models:
        parser.error("Trained model bundle has no item models")
    try:
        rows = load_test_rows(args.test, trained_models)
        predictions, summary = evaluate(rows, trained_models)
    except ValueError as error:
        parser.error(str(error))

    args.predictions.parent.mkdir(parents=True, exist_ok=True)
    args.summary.parent.mkdir(parents=True, exist_ok=True)
    with args.predictions.open("w", newline="", encoding="utf-8") as output:
        writer = csv.DictWriter(output, fieldnames=list(predictions[0]))
        writer.writeheader()
        writer.writerows(predictions)
    args.summary.write_text(json.dumps(summary, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(summary["metrics_by_unit"], indent=2))
    print(f"Evaluated {summary['test_observation_count']} test observations across {summary['test_item_count']} items.")
    print(f"Saved predictions: {args.predictions}")
    print(f"Saved evaluation summary: {args.summary}")


if __name__ == "__main__":
    main()
