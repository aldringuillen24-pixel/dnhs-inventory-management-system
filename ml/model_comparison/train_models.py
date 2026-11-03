#!/usr/bin/env python3
"""Fit comparison models from the chronological training CSV."""

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
from sklearn.linear_model import LinearRegression
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
    "history_complete",
    "movement_type",
    "status",
}
ELIGIBLE_MOVEMENTS = {"stock out", "stockout", "issue", "issuance"}
ELIGIBLE_STATUSES = {"approved", "complete", "completed"}


def month_offset(start_month: str, month: str) -> int:
    start_year, start_number = map(int, start_month.split("-"))
    year, number = map(int, month.split("-"))
    return (year - start_year) * 12 + number - start_number


def load_training_rows(path: Path) -> dict[tuple[int, int], dict[str, Any]]:
    if not path.is_file() or not path.stat().st_size:
        raise ValueError(f"Training CSV does not exist or is empty: {path}")

    items: dict[tuple[int, int], dict[str, Any]] = {}
    with path.open("r", newline="", encoding="utf-8-sig") as source:
        reader = csv.DictReader(source)
        if reader.fieldnames is None:
            raise ValueError("Training CSV must include a header row")
        reader.fieldnames = [name.strip().lower() for name in reader.fieldnames]
        missing = REQUIRED_COLUMNS - set(reader.fieldnames)
        if missing:
            raise ValueError(f"Training CSV is missing columns: {', '.join(sorted(missing))}")

        for line_number, row in enumerate(reader, start=2):
            normalise = lambda value: value.strip().casefold().replace("_", " ").replace("-", " ")
            if normalise(row["movement_type"]) not in ELIGIBLE_MOVEMENTS:
                raise ValueError(f"Ineligible movement type on CSV line {line_number}")
            if normalise(row["status"]) not in ELIGIBLE_STATUSES:
                raise ValueError(f"Ineligible status on CSV line {line_number}")
            if row["history_complete"].strip().casefold() not in {"true", "false"}:
                raise ValueError(f"history_complete must be true or false on CSV line {line_number}")

            try:
                inventory_id = int(row["inventory_id"])
                category_id = int(row["category_id"])
                quantity = float(row["quantity_issued"])
                event_date = datetime.strptime(row["stock_out_date"].strip(), "%Y-%m-%d")
                start_month = row["history_start_month"].strip()
                datetime.strptime(start_month, "%Y-%m")
            except (TypeError, ValueError) as error:
                raise ValueError(f"Invalid identity, quantity, or date on CSV line {line_number}") from error

            item_name = row["item_name"].strip()
            unit = row["unit"].strip()
            if inventory_id < 1 or category_id < 1 or not item_name or not unit:
                raise ValueError(f"Incomplete item identity on CSV line {line_number}")
            if not math.isfinite(quantity) or quantity < 0:
                raise ValueError(f"Quantity must be finite and non-negative on CSV line {line_number}")

            month = event_date.strftime("%Y-%m")
            offset = month_offset(start_month, month)
            if offset < 0:
                raise ValueError(f"Stock-out date precedes history window on CSV line {line_number}")

            identity = (inventory_id, category_id)
            metadata = {
                "inventory_id": inventory_id,
                "category_id": category_id,
                "item_name": item_name,
                "unit": unit,
                "history_start_month": start_month,
            }
            if identity not in items:
                items[identity] = {**metadata, "observations": {}}
            elif any(items[identity][key] != value for key, value in metadata.items()):
                raise ValueError(f"Conflicting item metadata on CSV line {line_number}")
            if offset in items[identity]["observations"]:
                raise ValueError(f"Duplicate item-month row on CSV line {line_number}; aggregate monthly data first")
            items[identity]["observations"][offset] = quantity

    if not items:
        raise ValueError("Training CSV contains no eligible item observations")
    return items


def train_models(items: dict[tuple[int, int], dict[str, Any]]) -> dict[str, Any]:
    trained: dict[str, Any] = {}
    item_summary = []
    for identity, item in sorted(items.items()):
        observations = sorted(item["observations"].items())
        if len(observations) < 3:
            raise ValueError(
                f"Item {identity[0]}:{identity[1]} has fewer than three verified training months"
            )
        x = [[offset] for offset, _ in observations]
        y = [quantity for _, quantity in observations]

        trained[f"{identity[0]}:{identity[1]}"] = {
            "inventory_id": identity[0],
            "category_id": identity[1],
            "item_name": item["item_name"],
            "unit": item["unit"],
            "history_start_month": item["history_start_month"],
            "training_month_offsets": [offset for offset, _ in observations],
            "models": {
                "linear": LinearRegression().fit(x, y),
                "polynomial_degree_2": make_pipeline(
                    PolynomialFeatures(degree=2, include_bias=False),
                    LinearRegression(),
                ).fit(x, y),
            },
        }
        item_summary.append({
            "inventory_id": identity[0],
            "category_id": identity[1],
            "item_name": item["item_name"],
            "training_months": len(observations),
        })

    return {
        "experiment": "linear_vs_polynomial_degree_2",
        "target": "monthly quantity issued",
        "feature": "calendar-month offset from history_start_month",
        "training_items": item_summary,
        "models": trained,
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Train isolated inventory demand comparison models.")
    parser.add_argument("--input", type=Path, default=HERE / "train.csv")
    parser.add_argument("--output", type=Path, default=HERE / "trained_models.joblib")
    parser.add_argument("--summary", type=Path, default=HERE / "training_summary.json")
    args = parser.parse_args()

    try:
        items = load_training_rows(args.input)
        bundle = train_models(items)
    except ValueError as error:
        parser.error(str(error))

    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.summary.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(bundle, args.output)
    args.summary.write_text(
        json.dumps({key: value for key, value in bundle.items() if key != "models"}, indent=2) + "\n",
        encoding="utf-8",
    )
    print(f"Trained both model types for {len(bundle['models'])} items.")
    print(f"Saved models: {args.output}")
    print(f"Saved training summary: {args.summary}")


if __name__ == "__main__":
    main()
