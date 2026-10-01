from __future__ import annotations

import csv
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import forecast_demand as production


FIELDS = [
    "inventory_id", "item_name", "category_id", "category", "unit",
    "quantity_issued", "stock_out_date", "history_start_month",
    "history_end_month", "history_complete", "movement_type", "status",
]


def write_export(rows: list[dict[str, str]]) -> Path:
    temporary = tempfile.NamedTemporaryFile(mode="w", newline="", suffix=".csv", delete=False, encoding="utf-8")
    with temporary:
        writer = csv.DictWriter(temporary, fieldnames=FIELDS)
        writer.writeheader()
        writer.writerows(rows)
    return Path(temporary.name)


def row(identity: int, category_id: int, month: str, quantity: int, **overrides: str) -> dict[str, str]:
    values = {
        "inventory_id": str(identity),
        "item_name": "Teacher Desk",
        "category_id": str(category_id),
        "category": f"Category {category_id}",
        "unit": "pieces",
        "quantity_issued": str(quantity),
        "stock_out_date": f"{month}-15",
        "history_start_month": "2026-01",
        "history_end_month": "2026-04",
        "history_complete": "false",
        "movement_type": "stock_out",
        "status": "approved",
    }
    return {**values, **overrides}


class ProductionDemandForecastTests(unittest.TestCase):
    def test_keeps_same_name_inventory_and_category_ids_separate(self) -> None:
        rows = [
            row(1001, 1, "2026-01", 2), row(1001, 1, "2026-02", 3), row(1001, 1, "2026-04", 4),
            row(2001, 8, "2026-01", 7), row(2001, 8, "2026-02", 8), row(2001, 8, "2026-04", 9),
        ]
        path = write_export(rows)
        try:
            items = production.parse_export(path)
        finally:
            path.unlink()

        self.assertEqual(set(items), {(1001, 1), (2001, 8)})
        self.assertEqual(items[(1001, 1)]["unknown_months"], ["2026-03"])
        self.assertNotIn("2026-03", items[(1001, 1)]["monthly_usage"])
        result, bundle = production.generate_forecast(items, minimum_history=3)
        self.assertEqual({row["inventory_id"] for row in result["forecasts"]}, {1001, 2001})
        self.assertEqual(set(bundle["estimators"]), {"1001:1", "2001:8"})
        self.assertEqual(result["forecasts"][0]["validation"]["status"], "unavailable")

    def test_excludes_unapproved_and_non_demand_movements(self) -> None:
        rows = [
            row(1001, 1, "2026-01", 2),
            row(1001, 1, "2026-02", 3, movement_type="transfer"),
            row(1001, 1, "2026-03", 4, status="pending"),
        ]
        path = write_export(rows)
        try:
            items = production.parse_export(path)
        finally:
            path.unlink()
        self.assertEqual(items[(1001, 1)]["monthly_usage"], {"2026-01": 2})

    def test_uses_time_ordered_holdout_and_insufficient_history_has_no_prediction(self) -> None:
        rows = [row(1001, 1, month, quantity) for month, quantity in [
            ("2026-01", 10), ("2026-02", 12), ("2026-03", 13), ("2026-04", 16),
        ]]
        rows.append(row(2001, 8, "2026-01", 1))
        path = write_export(rows)
        try:
            items = production.parse_export(path)
        finally:
            path.unlink()

        result, _ = production.generate_forecast(items, minimum_history=3)
        forecast = result["forecasts"][0]
        insufficient = result["insufficient_history"][0]
        self.assertEqual(forecast["validation"]["status"], "time_ordered_holdout")
        self.assertEqual(forecast["validation"]["months_tested"], 1)
        self.assertGreaterEqual(forecast["validation"]["mae"], 0)
        self.assertEqual(insufficient["status"], "insufficient_history")
        self.assertNotIn("predicted_quantity", insufficient)

    def test_rejects_conflicting_metadata_for_one_identity(self) -> None:
        rows = [
            row(1001, 1, "2026-01", 2),
            row(1001, 1, "2026-02", 3, item_name="Renamed Desk"),
        ]
        path = write_export(rows)
        try:
            with self.assertRaisesRegex(ValueError, "Conflicting identity"):
                production.parse_export(path)
        finally:
            path.unlink()


if __name__ == "__main__":
    unittest.main()
