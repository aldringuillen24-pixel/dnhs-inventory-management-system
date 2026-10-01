#!/usr/bin/env python3
"""Train production monthly demand forecasts from Laravel's approved export."""

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
    "inventory_id", "item_name", "category_id", "category", "unit",
    "quantity_issued", "stock_out_date", "history_start_month",
    "history_end_month", "history_complete", "movement_type", "status",
}
INCLUDED_TYPES = {"stock out", "stockout", "issue", "issuance"}
INCLUDED_STATUSES = {"approved", "complete", "completed"}
MODEL_VERSION = "demand-linear-regression-v2"
MAX_HISTORY_MONTHS = 120


def normalise(value: str) -> str:
    return value.strip().casefold().replace("_", " ").replace("-", " ")


def month_sequence(start_month: str, end_month: str) -> list[str]:
    start = datetime.strptime(start_month, "%Y-%m")
    end = datetime.strptime(end_month, "%Y-%m")
    if start > end:
        raise ValueError("history_start_month must not be after history_end_month")
    count = (end.year - start.year) * 12 + end.month - start.month + 1
    if count > MAX_HISTORY_MONTHS:
        raise ValueError(f"history window cannot exceed {MAX_HISTORY_MONTHS} months")
    return [next_month(start_month, offset) for offset in range(count)]


def next_month(month: str, offset: int = 1) -> str:
    year, month_number = map(int, month.split("-"))
    serial = year * 12 + month_number - 1 + offset
    return f"{serial // 12:04d}-{serial % 12 + 1:02d}"


def month_offset(start_month: str, month: str) -> int:
    start_year, start_index = map(int, start_month.split("-"))
    year, index = map(int, month.split("-"))
    return (year - start_year) * 12 + index - start_index


def parse_export(path: str | Path) -> dict[tuple[int, int], dict[str, Any]]:
    """Aggregate eligible events without merging inventory identities or gaps."""
    csv_path = Path(path)
    if not csv_path.is_file() or not csv_path.stat().st_size:
        raise ValueError("Production forecast export does not exist or is empty")

    items: dict[tuple[int, int], dict[str, Any]] = {}
    with csv_path.open("r", newline="", encoding="utf-8-sig") as source:
        reader = csv.DictReader(source)
        if reader.fieldnames is None:
            raise ValueError("Production export must include a header row")
        reader.fieldnames = [field.strip().lower() for field in reader.fieldnames]
        missing = REQUIRED_COLUMNS - set(reader.fieldnames)
        if missing:
            raise ValueError(f"Production export is missing columns: {', '.join(sorted(missing))}")

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
                start_month = row["history_start_month"].strip()
                end_month = row["history_end_month"].strip()
                window_months = month_sequence(start_month, end_month)
            except (TypeError, ValueError) as exception:
                raise ValueError(f"Invalid identity, quantity, or date on CSV line {line_number}") from exception

            item_name = row["item_name"].strip()
            category = row["category"].strip()
            unit = row["unit"].strip()
            if inventory_id < 1 or category_id < 1 or not item_name or not category or not unit:
                raise ValueError(f"Item identity is incomplete on CSV line {line_number}")
            if not math.isfinite(quantity) or quantity <= 0:
                continue

            event_month = event_date.strftime("%Y-%m")
            if event_month not in window_months:
                raise ValueError(f"Stock-out date is outside the declared history window on CSV line {line_number}")
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
                raise ValueError(f"Conflicting identity or history metadata on CSV line {line_number}")
            items[identity]["events"][event_month] += quantity

    if not items:
        return {}

    for item in items.values():
        months = month_sequence(item["history_start_month"], item["history_end_month"])
        events = item["events"]
        if item["history_complete"]:
            item["monthly_usage"] = {
                month: normalise_quantity(float(events.get(month, 0))) for month in months
            }
            item["unknown_months"] = []
        else:
            item["monthly_usage"] = {
                month: normalise_quantity(float(quantity)) for month, quantity in sorted(events.items())
            }
            item["unknown_months"] = [month for month in months if month not in events]
        item["months_used"] = list(item["monthly_usage"])
        item["verified_months_used"] = len(item["months_used"])
    return dict(sorted(items.items()))


def normalise_quantity(quantity: float) -> int | float:
    return int(quantity) if quantity.is_integer() else quantity


def generate_forecast(
    items: dict[tuple[int, int], dict[str, Any]], minimum_history: int = 3
) -> tuple[dict[str, Any], dict[str, Any]]:
    if minimum_history < 1:
        raise ValueError("minimum_history must be at least 1")

    forecasts: list[dict[str, Any]] = []
    insufficient: list[dict[str, Any]] = []
    estimators: dict[str, Any] = {}
    for identity, item in sorted(items.items()):
        start = item["history_start_month"]
        end = item["history_end_month"]
        used_months = item["months_used"]
        history_count = len(used_months)
        window = {
            "start_month": start,
            "end_month": end,
            "completeness": "complete" if item["history_complete"] else "incomplete",
        }
        common = {
            "inventory_id": item["inventory_id"],
            "item_name": item["item_name"],
            "category_id": item["category_id"],
            "category": item["category"],
            "unit": item["unit"],
            "forecast_month": next_month(end),
            "history_window": window,
            "monthly_usage": item["monthly_usage"],
            "unknown_months": item["unknown_months"],
            "months_used": used_months,
            "verified_months_used": history_count,
            "required_months": minimum_history,
            "model_version": MODEL_VERSION,
            "confidence": "High" if history_count >= 12 else ("Medium" if history_count >= 6 else "Low"),
        }
        if history_count < minimum_history:
            insufficient.append({**common, "status": "insufficient_history"})
            continue

        offsets = [[month_offset(start, month)] for month in used_months]
        quantities = [float(item["monthly_usage"][month]) for month in used_months]
        validation: dict[str, Any] = {"status": "unavailable", "months_tested": 0, "mae": None}
        if history_count >= minimum_history + 1:
            validation_model = LinearRegression().fit(offsets[:-1], quantities[:-1])
            holdout_prediction = float(validation_model.predict([offsets[-1]])[0])
            validation = {
                "status": "time_ordered_holdout",
                "months_tested": 1,
                "mae": normalise_quantity(abs(holdout_prediction - quantities[-1])),
            }

        model = LinearRegression().fit(offsets, quantities)
        forecast_offset = month_offset(start, end) + 1
        predicted = max(0, int(math.floor(float(model.predict([[forecast_offset]])[0]) + 0.5)))
        forecasts.append({**common, "predicted_quantity": predicted, "status": "success", "validation": validation})
        estimators[f"{identity[0]}:{identity[1]}"] = {
            "inventory_id": identity[0], "category_id": identity[1], "estimator": model,
            "history_window": window, "months_used": used_months,
        }

    generated_at = datetime.now(timezone.utc).isoformat(timespec="seconds").replace("+00:00", "Z")
    result = {
        "schema_version": 2,
        "source_type": "live",
        "model_version": MODEL_VERSION,
        "generated_at": generated_at,
        "forecasts": forecasts,
        "insufficient_history": insufficient,
    }
    bundle = {
        "source_type": "live",
        "model_version": MODEL_VERSION,
        "minimum_verified_months": minimum_history,
        "trained_at": generated_at,
        "estimators": estimators,
    }
    validate_result(result)
    return result, bundle


def validate_result(result: dict[str, Any]) -> None:
    required_root = {"schema_version", "source_type", "model_version", "generated_at", "forecasts", "insufficient_history"}
    if set(result) != required_root or result["schema_version"] != 2 or result["source_type"] != "live":
        raise ValueError("Generated production forecast has an invalid root structure or source")
    if result["model_version"] != MODEL_VERSION or not result["generated_at"].endswith("Z"):
        raise ValueError("Generated production forecast is missing model metadata")
    identities: set[tuple[int, int]] = set()
    for group in ("forecasts", "insufficient_history"):
        if not isinstance(result[group], list):
            raise ValueError("Generated production forecast groups must be lists")
        for row in result[group]:
            identity = (row.get("inventory_id"), row.get("category_id"))
            if not all(isinstance(value, int) and value > 0 for value in identity) or identity in identities:
                raise ValueError("Generated production forecast contains an invalid or duplicate identity")
            identities.add(identity)
            quantity = row.get("predicted_quantity")
            if group == "forecasts" and (not isinstance(quantity, int) or quantity < 0 or row.get("status") != "success"):
                raise ValueError("Generated production forecast contains an invalid prediction")
            if group == "insufficient_history" and ("predicted_quantity" in row or row.get("status") != "insufficient_history"):
                raise ValueError("Insufficient-history results cannot contain predictions")
            if row.get("months_used") != list(row.get("monthly_usage", {})):
                raise ValueError("Generated production forecast has inconsistent history months")
            if row.get("verified_months_used") != len(row["months_used"]):
                raise ValueError("Generated production forecast has an inconsistent history count")
            window = row["history_window"]
            expected_months = month_sequence(window["start_month"], window["end_month"])
            if window["completeness"] == "complete":
                if row["unknown_months"] or row["months_used"] != expected_months:
                    raise ValueError("Complete production history must verify every window month")
            elif set(row["months_used"]) & set(row["unknown_months"]):
                raise ValueError("Unknown history months cannot also be verified")
            for month, value in row["monthly_usage"].items():
                if month not in expected_months or not isinstance(value, (int, float)) or not math.isfinite(value) or value < 0:
                    raise ValueError("Generated production forecast contains invalid monthly usage")


def main() -> None:
    parser = argparse.ArgumentParser(description="Train identity-aware monthly demand forecasts.")
    parser.add_argument("--input", required=True)
    parser.add_argument("--minimum-history", type=int, default=3)
    parser.add_argument("--output", required=True)
    parser.add_argument("--model-output", required=True)
    arguments = parser.parse_args()
    if arguments.minimum_history < 1:
        raise SystemExit("--minimum-history must be at least 1")

    items = parse_export(arguments.input)
    result, bundle = generate_forecast(items, arguments.minimum_history)
    output_path = Path(arguments.output)
    model_path = Path(arguments.model_output)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    model_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")
    joblib.dump(bundle, model_path)


if __name__ == "__main__":
    main()
