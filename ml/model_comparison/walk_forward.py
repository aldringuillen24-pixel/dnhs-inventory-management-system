#!/usr/bin/env python3
"""Run expanding-window validation using all verified monthly history."""

from __future__ import annotations

import argparse
import csv
import json
import math
from collections import defaultdict
from datetime import datetime
from pathlib import Path
from typing import Any

from sklearn.linear_model import LinearRegression
from sklearn.metrics import mean_absolute_error, mean_squared_error
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import PolynomialFeatures

HERE = Path(__file__).resolve().parent
REQUIRED_COLUMNS = {
    "inventory_id",
    "item_name",
    "category_id",
    "unit",
    "quantity_issued",
    "stock_out_date",
    "history_start_month",
    "history_end_month",
    "history_complete",
    "movement_type",
    "status",
}
ELIGIBLE_MOVEMENTS = {"stock out", "stockout", "issue", "issuance"}
ELIGIBLE_STATUSES = {"approved", "complete", "completed"}
MINIMUM_TRAINING_MONTHS = 3
MODEL_NAMES = ("linear", "polynomial_degree_2")


def month_index(month: str) -> int:
    year, number = map(int, month.split("-"))
    return year * 12 + number - 1


def month_label(index: int) -> str:
    return f"{index // 12:04d}-{index % 12 + 1:02d}"


def month_range(start: str, end: str) -> list[str]:
    first, last = month_index(start), month_index(end)
    if first > last:
        raise ValueError("history_start_month must not be after history_end_month")
    return [month_label(index) for index in range(first, last + 1)]


def normalise(value: str) -> str:
    return value.strip().casefold().replace("_", " ").replace("-", " ")


def load_history(path: Path) -> dict[tuple[int, int], dict[str, Any]]:
    if not path.is_file() or not path.stat().st_size:
        raise ValueError(f"History CSV does not exist or is empty: {path}")

    items: dict[tuple[int, int], dict[str, Any]] = {}
    with path.open("r", newline="", encoding="utf-8-sig") as source:
        reader = csv.DictReader(source)
        if reader.fieldnames is None:
            raise ValueError("History CSV must include a header row")
        reader.fieldnames = [name.strip().lower() for name in reader.fieldnames]
        missing = REQUIRED_COLUMNS - set(reader.fieldnames)
        if missing:
            raise ValueError(f"History CSV is missing columns: {', '.join(sorted(missing))}")

        for line_number, row in enumerate(reader, start=2):
            if normalise(row["movement_type"]) not in ELIGIBLE_MOVEMENTS:
                continue
            if normalise(row["status"]) not in ELIGIBLE_STATUSES:
                continue
            try:
                inventory_id = int(row["inventory_id"])
                category_id = int(row["category_id"])
                quantity = float(row["quantity_issued"])
                event_date = datetime.strptime(row["stock_out_date"].strip(), "%Y-%m-%d")
                start = row["history_start_month"].strip()
                end = row["history_end_month"].strip()
                datetime.strptime(start, "%Y-%m")
                datetime.strptime(end, "%Y-%m")
                window = month_range(start, end)
            except (TypeError, ValueError) as error:
                raise ValueError(f"Invalid identity, quantity, or date on CSV line {line_number}") from error

            name, unit = row["item_name"].strip(), row["unit"].strip()
            complete = row["history_complete"].strip().casefold()
            if inventory_id < 1 or category_id < 1 or not name or not unit:
                raise ValueError(f"Incomplete item identity on CSV line {line_number}")
            if not math.isfinite(quantity) or quantity < 0:
                raise ValueError(f"Quantity must be finite and non-negative on CSV line {line_number}")
            if complete not in {"true", "false"}:
                raise ValueError(f"history_complete must be true or false on CSV line {line_number}")
            month = event_date.strftime("%Y-%m")
            if month not in window:
                raise ValueError(f"Stock-out date is outside history window on CSV line {line_number}")

            identity = (inventory_id, category_id)
            metadata = (name, unit, start, end, complete == "true")
            item = items.get(identity)
            if item is None:
                item = {
                    "inventory_id": inventory_id,
                    "category_id": category_id,
                    "item_name": name,
                    "unit": unit,
                    "start_month": start,
                    "end_month": end,
                    "complete": complete == "true",
                    "metadata": metadata,
                    "quantities": defaultdict(float),
                }
                items[identity] = item
            elif item["metadata"] != metadata:
                raise ValueError(f"Conflicting item or history metadata on CSV line {line_number}")
            item["quantities"][month] += quantity

    if not items:
        raise ValueError("History CSV contains no eligible observations")

    for item in items.values():
        if item["complete"]:
            months = month_range(item["start_month"], item["end_month"])
            item["monthly_usage"] = {
                month: float(item["quantities"].get(month, 0.0)) for month in months
            }
        else:
            item["monthly_usage"] = dict(item["quantities"])
        item["history_start_index"] = month_index(item["start_month"])
    return dict(sorted(items.items()))


def new_models() -> dict[str, Any]:
    return {
        "linear": LinearRegression(),
        "polynomial_degree_2": make_pipeline(
            PolynomialFeatures(degree=2, include_bias=False),
            LinearRegression(),
        ),
    }


def metric_pair(actual: list[float], predicted: list[float]) -> dict[str, float]:
    return {
        "mae": float(mean_absolute_error(actual, predicted)),
        "rmse": math.sqrt(float(mean_squared_error(actual, predicted))),
    }


def run_walk_forward(
    items: dict[tuple[int, int], dict[str, Any]],
) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    predictions: list[dict[str, Any]] = []
    for identity, item in items.items():
        observations = sorted(
            (
                month_index(month) - item["history_start_index"],
                quantity,
                month,
            )
            for month, quantity in item["monthly_usage"].items()
        )
        for cutoff in range(MINIMUM_TRAINING_MONTHS, len(observations)):
            training = observations[:cutoff]
            test_offset, actual, test_month = observations[cutoff]
            x_train = [[offset] for offset, _, _ in training]
            y_train = [quantity for _, quantity, _ in training]
            row: dict[str, Any] = {
                "inventory_id": identity[0],
                "category_id": identity[1],
                "item_name": item["item_name"],
                "unit": item["unit"],
                "training_month_count": len(training),
                "training_through_month": training[-1][2],
                "test_month": test_month,
                "actual_quantity": actual,
            }
            for model_name, model in new_models().items():
                model.fit(x_train, y_train)
                prediction = max(0.0, float(model.predict([[test_offset]])[0]))
                row[f"{model_name}_prediction"] = prediction
                row[f"{model_name}_absolute_error"] = abs(actual - prediction)
            predictions.append(row)

    if not predictions:
        raise ValueError(
            "No walk-forward cutoffs are available; each item needs at least "
            f"{MINIMUM_TRAINING_MONTHS + 1} verified months."
        )

    by_unit: dict[str, dict[str, dict[str, list[float]]]] = defaultdict(
        lambda: defaultdict(lambda: {"actual": [], "predicted": []})
    )
    by_cutoff: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for row in predictions:
        by_cutoff[row["test_month"]].append(row)
        for model_name in MODEL_NAMES:
            values = by_unit[row["unit"]][model_name]
            values["actual"].append(row["actual_quantity"])
            values["predicted"].append(row[f"{model_name}_prediction"])

    metrics_by_unit = {
        unit: {
            model_name: metric_pair(values[model_name]["actual"], values[model_name]["predicted"])
            for model_name in MODEL_NAMES
        }
        for unit, values in sorted(by_unit.items())
    }
    cutoff_results = []
    for test_month, rows in sorted(by_cutoff.items()):
        units: dict[str, dict[str, dict[str, list[float]]]] = defaultdict(
            lambda: defaultdict(lambda: {"actual": [], "predicted": []})
        )
        for row in rows:
            for model_name in MODEL_NAMES:
                units[row["unit"]][model_name]["actual"].append(row["actual_quantity"])
                units[row["unit"]][model_name]["predicted"].append(row[f"{model_name}_prediction"])
        cutoff_results.append({
            "test_month": test_month,
            "item_count": len(rows),
            "metrics_by_unit": {
                unit: {
                    model_name: metric_pair(values[model_name]["actual"], values[model_name]["predicted"])
                    for model_name in MODEL_NAMES
                }
                for unit, values in sorted(units.items())
            },
        })

    summary = {
        "experiment": "expanding_window_walk_forward",
        "minimum_training_months": MINIMUM_TRAINING_MONTHS,
        "cutoff_count": len(cutoff_results),
        "multiple_cutoffs_available": len(cutoff_results) > 1,
        "item_month_observation_count": len(predictions),
        "metrics_scope": "Per unit; errors across unlike units are not combined.",
        "metrics_by_unit": metrics_by_unit,
        "cutoffs": cutoff_results,
        "limitation": (
            "The supplied history supports only one chronological test cutoff. "
            "Collect more verified months per item to assess stability across multiple cutoffs."
            if len(cutoff_results) <= 1
            else None
        ),
    }
    return predictions, summary


def main() -> None:
    parser = argparse.ArgumentParser(description="Run expanding-window forecast validation.")
    parser.add_argument("--input", type=Path, default=HERE / "example_monthly_issuance.csv")
    parser.add_argument("--predictions", type=Path, default=HERE / "walk_forward_predictions.csv")
    parser.add_argument("--summary", type=Path, default=HERE / "walk_forward_results.json")
    args = parser.parse_args()

    try:
        items = load_history(args.input)
        predictions, summary = run_walk_forward(items)
    except ValueError as error:
        parser.error(str(error))

    args.predictions.parent.mkdir(parents=True, exist_ok=True)
    args.summary.parent.mkdir(parents=True, exist_ok=True)
    with args.predictions.open("w", newline="", encoding="utf-8") as output:
        writer = csv.DictWriter(output, fieldnames=list(predictions[0]))
        writer.writeheader()
        writer.writerows(predictions)
    args.summary.write_text(json.dumps(summary, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(summary, indent=2))


if __name__ == "__main__":
    main()
