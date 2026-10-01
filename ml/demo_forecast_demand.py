#!/usr/bin/env python3
"""Train an isolated sample-only inventory demand forecast."""

from __future__ import annotations

import argparse
import csv
import json
import math
from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import joblib
from sklearn.linear_model import LinearRegression

REQUIRED_COLUMNS = {
    "inventory_id",
    "item_name",
    "category_id",
    "category",
    "unit",
    "quantity_issued",
    "stock_out_date",
    "history_start_month",
    "history_end_month",
    "history_complete",
    "movement_type",
    "status",
}
MODEL_VERSION = "demo-linear-regression-v1"
MAX_HISTORY_MONTHS = 120
INCLUDED_TYPES = {"stock out", "stockout", "issue", "issuance"}
INCLUDED_STATUSES = {"approved", "complete", "completed"}


def month_sequence(start_month: str, end_month: str) -> list[str]:
    start = datetime.strptime(start_month, "%Y-%m")
    end = datetime.strptime(end_month, "%Y-%m")
    if start > end:
        raise ValueError("history_start_month must not be after history_end_month")
    month_count = (end.year - start.year) * 12 + end.month - start.month + 1
    if month_count > MAX_HISTORY_MONTHS:
        raise ValueError(f"history window cannot exceed {MAX_HISTORY_MONTHS} months")

    months = []
    year, month = start.year, start.month
    while (year, month) <= (end.year, end.month):
        months.append(f"{year:04d}-{month:02d}")
        month += 1
        if month == 13:
            year += 1
            month = 1
    return months


def next_month(month: str) -> str:
    year, month_number = map(int, month.split("-"))
    if month_number == 12:
        return f"{year + 1:04d}-01"
    return f"{year:04d}-{month_number + 1:02d}"


def month_offset(start_month: str, month: str) -> int:
    start_year, start_index = map(int, start_month.split("-"))
    year, index = map(int, month.split("-"))
    return (year - start_year) * 12 + index - start_index


def normalise(value: str) -> str:
    return value.strip().casefold().replace("_", " ").replace("-", " ")


def parse_sample_csv(path: str | Path) -> dict[tuple[int, int], dict[str, Any]]:
    """Aggregate only approved sample stock-outs by stable inventory/category IDs."""
    csv_path = Path(path)
    if not csv_path.is_file() or not csv_path.stat().st_size:
        raise ValueError(f"Sample CSV does not exist or is empty: {csv_path}")

    items: dict[tuple[int, int], dict[str, Any]] = {}
    with csv_path.open("r", newline="", encoding="utf-8-sig") as source:
        reader = csv.DictReader(source)
        if reader.fieldnames is None:
            raise ValueError("Sample CSV must include a header row")
        reader.fieldnames = [field.strip().lower() for field in reader.fieldnames]
        missing = REQUIRED_COLUMNS - set(reader.fieldnames)
        if missing:
            raise ValueError(f"Sample CSV is missing columns: {', '.join(sorted(missing))}")

        for line_number, row in enumerate(reader, start=2):
            if normalise(row.get("movement_type", "")) not in INCLUDED_TYPES:
                continue
            if normalise(row.get("status", "")) not in INCLUDED_STATUSES:
                continue

            try:
                inventory_id = int(row["inventory_id"])
                category_id = int(row["category_id"])
                quantity = float(row["quantity_issued"])
                event_date = datetime.strptime(row["stock_out_date"], "%Y-%m-%d")
                datetime.strptime(row["history_start_month"], "%Y-%m")
                datetime.strptime(row["history_end_month"], "%Y-%m")
            except (TypeError, ValueError) as exception:
                raise ValueError(f"Invalid sample stock-out identity, quantity, or date on CSV line {line_number}") from exception

            item_name = row["item_name"].strip()
            category = row["category"].strip()
            unit = row["unit"].strip()
            if inventory_id < 1 or category_id < 1 or not item_name or not category or not unit:
                raise ValueError(f"Sample item identity is incomplete on CSV line {line_number}")
            if not math.isfinite(quantity) or quantity <= 0:
                continue

            start_month = row["history_start_month"]
            end_month = row["history_end_month"]
            months = month_sequence(start_month, end_month)
            event_month = event_date.strftime("%Y-%m")
            if event_month not in months:
                raise ValueError(f"Stock-out date falls outside its declared history window on CSV line {line_number}")

            complete_value = row["history_complete"].strip().casefold()
            if complete_value not in {"true", "false"}:
                raise ValueError(f"history_complete must be true or false on CSV line {line_number}")
            identity = (inventory_id, category_id)
            metadata = {
                "inventory_id": inventory_id,
                "item_name": item_name,
                "category_id": category_id,
                "category": category,
                "unit": unit,
                "history_start_month": start_month,
                "history_end_month": end_month,
                "history_complete": complete_value == "true",
            }

            if identity not in items:
                items[identity] = {**metadata, "events": defaultdict(float)}
            elif any(items[identity][field] != value for field, value in metadata.items()):
                raise ValueError(f"Conflicting identity or history metadata for inventory {inventory_id} on CSV line {line_number}")

            items[identity]["events"][event_month] += quantity

    if not items:
        raise ValueError("Sample CSV contains no eligible completed stock-out records")

    for item in items.values():
        months = month_sequence(item["history_start_month"], item["history_end_month"])
        event_months = item["events"]
        item["unknown_months"] = []
        if item["history_complete"]:
            item["monthly_usage"] = {
                month: int(event_months.get(month, 0)) if event_months.get(month, 0).is_integer() else event_months[month]
                for month in months
            }
        else:
            item["monthly_usage"] = {
                month: int(quantity) if quantity.is_integer() else quantity
                for month, quantity in sorted(event_months.items())
            }
            item["unknown_months"] = [month for month in months if month not in event_months]

        item["months_used"] = list(item["monthly_usage"])
        item["verified_months_used"] = len(item["months_used"])

    return dict(sorted(items.items()))


def validate_result(result: dict[str, Any]) -> None:
    expected_root = {"schema_version", "source_type", "model_version", "generated_at", "forecasts", "insufficient_history"}
    if set(result) != expected_root or result["schema_version"] != 1 or result["source_type"] != "demo":
        raise ValueError("Generated demo forecast has an invalid root structure")
    if result["model_version"] != MODEL_VERSION or not result["generated_at"].endswith("Z"):
        raise ValueError("Generated demo forecast is missing source metadata")

    identities = set()
    required_item_fields = {
        "inventory_id", "item_name", "category_id", "category", "unit", "forecast_month",
        "history_window", "monthly_usage", "unknown_months", "months_used", "verified_months_used",
        "required_months", "model_version", "confidence",
    }
    for collection_name in ("forecasts", "insufficient_history"):
        for item in result[collection_name]:
            if not required_item_fields.issubset(item):
                raise ValueError("Generated demo forecast item is missing required identity or history fields")
            identity = (item["inventory_id"], item["category_id"])
            if identity in identities:
                raise ValueError("Generated demo forecast contains a duplicate inventory/category identity")
            identities.add(identity)
            window = item["history_window"]
            expected_months = month_sequence(window["start_month"], window["end_month"])
            if item["months_used"] != list(item["monthly_usage"]):
                raise ValueError("Generated demo forecast months_used does not match monthly usage")
            if item["verified_months_used"] != len(item["months_used"]):
                raise ValueError("Generated demo forecast verified-month count is inconsistent")
            if window["completeness"] == "complete":
                if item["unknown_months"] or item["months_used"] != expected_months:
                    raise ValueError("Complete demo history must verify every declared month")
            elif set(item["months_used"]) & set(item["unknown_months"]):
                raise ValueError("Unknown demo months cannot also be marked as verified")
            for month, value in item["monthly_usage"].items():
                if month not in expected_months or not isinstance(value, (int, float)) or value < 0 or not math.isfinite(value):
                    raise ValueError("Generated demo forecast contains invalid monthly usage")

    forecast_identities = {(item["inventory_id"], item["category_id"]) for item in result["forecasts"]}
    if forecast_identities | identities != identities:
        raise ValueError("Generated demo forecast identity validation failed")


def generate_demo_forecast(items: dict[tuple[int, int], dict[str, Any]], minimum_history: int) -> tuple[dict[str, Any], dict[str, Any]]:
    forecasts = []
    insufficient_history = []
    estimators = {}

    for identity, item in items.items():
        identity_key = f"{identity[0]}:{identity[1]}"
        window = {
            "start_month": item["history_start_month"],
            "end_month": item["history_end_month"],
            "completeness": "complete" if item["history_complete"] else "incomplete",
        }
        months_used = item["months_used"]
        common = {
            "inventory_id": item["inventory_id"],
            "item_name": item["item_name"],
            "category_id": item["category_id"],
            "category": item["category"],
            "unit": item["unit"],
            "forecast_month": next_month(item["history_end_month"]),
            "history_window": window,
            "monthly_usage": item["monthly_usage"],
            "unknown_months": item["unknown_months"],
            "months_used": months_used,
            "verified_months_used": item["verified_months_used"],
            "required_months": minimum_history,
            "model_version": MODEL_VERSION,
            "confidence": "High" if len(months_used) >= 12 else ("Medium" if len(months_used) >= 6 else "Low"),
        }
        if len(months_used) < minimum_history:
            insufficient_history.append({**common, "status": "insufficient_history"})
            continue

        offsets = [[month_offset(item["history_start_month"], month)] for month in months_used]
        quantities = [float(item["monthly_usage"][month]) for month in months_used]
        model = LinearRegression().fit(offsets, quantities)
        next_offset = [[month_offset(item["history_start_month"], item["history_end_month"]) + 1]]
        predicted_quantity = max(0, int(math.floor(float(model.predict(next_offset)[0]) + 0.5)))
        forecasts.append({**common, "predicted_quantity": predicted_quantity, "status": "success"})
        estimators[identity_key] = {
            "inventory_id": item["inventory_id"],
            "category_id": item["category_id"],
            "item_name": item["item_name"],
            "category": item["category"],
            "history_window": window,
            "months_used": months_used,
            "estimator": model,
        }

    generated_at = datetime.now(timezone.utc).isoformat(timespec="seconds").replace("+00:00", "Z")
    result = {
        "schema_version": 1,
        "source_type": "demo",
        "model_version": MODEL_VERSION,
        "generated_at": generated_at,
        "forecasts": forecasts,
        "insufficient_history": insufficient_history,
    }
    validate_result(result)
    model_bundle = {
        "source_type": "demo",
        "model_version": MODEL_VERSION,
        "minimum_verified_months": minimum_history,
        "trained_at": generated_at,
        "estimators": estimators,
    }
    return result, model_bundle


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Train isolated sample-only monthly demand models.")
    parser.add_argument("--input", required=True, help="Dedicated sample stock-out CSV path.")
    parser.add_argument("--minimum-history", type=int, default=3)
    parser.add_argument("--output", required=True, help="Demo-only forecast JSON path.")
    parser.add_argument("--model-output", required=True, help="Demo-only Joblib model bundle path.")
    return parser.parse_args()


def main() -> None:
    arguments = parse_arguments()
    if arguments.minimum_history < 1:
        raise SystemExit("--minimum-history must be at least 1")

    items = parse_sample_csv(arguments.input)
    result, model_bundle = generate_demo_forecast(items, arguments.minimum_history)
    output_path = Path(arguments.output)
    model_path = Path(arguments.model_output)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    model_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")
    joblib.dump(model_bundle, model_path)


if __name__ == "__main__":
    main()
