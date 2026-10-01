from __future__ import annotations

import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import demo_forecast_demand as demo


SAMPLE_CSV = Path(__file__).resolve().parents[1] / "demo_stock_out_data.csv"


class DemoDemandForecastTests(unittest.TestCase):
    def setUp(self) -> None:
        self.items = demo.parse_sample_csv(SAMPLE_CSV)

    def test_same_name_inventory_and_category_identities_remain_separate(self) -> None:
        first = self.items[(1001, 1)]
        second = self.items[(2001, 8)]

        self.assertEqual(first["item_name"], second["item_name"])
        self.assertNotEqual(first["category_id"], second["category_id"])
        self.assertEqual(first["monthly_usage"]["2026-08"], 0)
        self.assertEqual(second["monthly_usage"]["2026-08"], 3)

        result, bundle = demo.generate_demo_forecast(self.items, minimum_history=3)
        teacher_desks = [row for row in result["forecasts"] if row["item_name"] == "Teacher Desk"]
        self.assertEqual({row["inventory_id"] for row in teacher_desks}, {1001, 2001})
        self.assertEqual({row["category_id"] for row in teacher_desks}, {1, 8})
        self.assertIn("3001:5", bundle["estimators"])

    def test_incomplete_history_keeps_missing_months_unknown_and_insufficient(self) -> None:
        item = self.items[(4001, 4)]
        result, _ = demo.generate_demo_forecast(self.items, minimum_history=3)
        limited = next(row for row in result["insufficient_history"] if row["inventory_id"] == 4001)

        self.assertEqual(item["unknown_months"], ["2026-07", "2026-09"])
        self.assertNotIn("2026-07", item["monthly_usage"])
        self.assertEqual(limited["verified_months_used"], 2)
        self.assertEqual(limited["status"], "insufficient_history")
        self.assertNotIn(4001, {row["inventory_id"] for row in result["forecasts"]})

    def test_one_hundred_added_items_use_all_system_categories(self) -> None:
        added_items = [identity for identity in self.items if identity[0] >= 5001]

        self.assertEqual(len(added_items), 100)
        self.assertEqual({category_id for _, category_id in added_items}, set(range(1, 9)))
        self.assertTrue(all(self.items[identity]["history_complete"] for identity in added_items))
        self.assertTrue(all(self.items[identity]["verified_months_used"] == 4 for identity in added_items))

    def test_result_includes_demo_source_model_identity_and_history_metadata(self) -> None:
        result, bundle = demo.generate_demo_forecast(self.items, minimum_history=3)
        forecast = next(row for row in result["forecasts"] if row["inventory_id"] == 1001)

        self.assertEqual(result["source_type"], "demo")
        self.assertEqual(forecast["model_version"], demo.MODEL_VERSION)
        self.assertTrue(result["generated_at"].endswith("Z"))
        self.assertEqual(forecast["forecast_month"], "2026-10")
        self.assertEqual(forecast["history_window"], {
            "start_month": "2026-06",
            "end_month": "2026-09",
            "completeness": "complete",
        })
        self.assertEqual(forecast["months_used"], ["2026-06", "2026-07", "2026-08", "2026-09"])
        self.assertEqual(bundle["source_type"], "demo")
        self.assertEqual(bundle["minimum_verified_months"], 3)

    def test_next_month_handles_year_rollover(self) -> None:
        self.assertEqual(demo.next_month("2026-12"), "2027-01")
        self.assertEqual(demo.next_month("2026-11"), "2026-12")

    def test_history_window_cannot_expand_without_bound(self) -> None:
        with self.assertRaisesRegex(ValueError, "cannot exceed 120 months"):
            demo.month_sequence("2000-01", "2026-01")


if __name__ == "__main__":
    unittest.main()
