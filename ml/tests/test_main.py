from __future__ import annotations

import io
import os
import sys
import unittest
from pathlib import Path
from unittest.mock import patch

from fastapi import HTTPException, UploadFile
from fastapi.testclient import TestClient

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import main


CSV_EXPORT = b"""inventory_id,item_name,category_id,category,unit,quantity_issued,stock_out_date,history_start_month,history_end_month,history_complete,movement_type,status
1,Paper,2,Supplies,ream,10,2025-01-15,2025-01,2025-03,false,stock_out,approved
1,Paper,2,Supplies,ream,12,2025-02-15,2025-01,2025-03,false,stock_out,approved
1,Paper,2,Supplies,ream,11,2025-03-15,2025-01,2025-03,false,stock_out,approved
"""


def upload_csv(content: bytes = CSV_EXPORT) -> UploadFile:
    return UploadFile(filename="forecast.csv", file=io.BytesIO(content))


class DemandForecastApiTests(unittest.TestCase):
    def setUp(self) -> None:
        self.client = TestClient(main.app)

    def test_health_is_available_without_authentication(self) -> None:
        self.assertEqual(main.health(), {"status": "ok"})

    def test_predict_route_declares_json_body_and_bearer_header(self) -> None:
        operation = main.app.openapi()["paths"]["/predict"]["post"]

        self.assertIn("application/json", operation["requestBody"]["content"])
        self.assertTrue(
            any(
                parameter["name"] == "authorization" and parameter["in"] == "header"
                for parameter in operation["parameters"]
            )
        )

    def test_predict_returns_requested_months_using_issued_quantity_history(self) -> None:
        payload = {
            "item_id": 12,
            "item_name": "Bond Paper A4",
            "forecast_months": 3,
            "historical_data": [
                {"month": "2026-07", "issued_quantity": 120, "stock_out_quantity": 15, "stock_level": 40},
                {"month": "2026-08", "issued_quantity": 150, "stock_out_quantity": 20, "stock_level": 25},
                {"month": "2026-09", "issued_quantity": 135, "stock_out_quantity": 10, "stock_level": 30},
            ],
        }
        with patch.dict(os.environ, {"ML_API_TOKEN": "validation-key"}):
            response = self.client.post(
                "/predict",
                json=payload,
                headers={"Authorization": "Bearer validation-key"},
            )

        self.assertEqual(response.status_code, 200)
        result = response.json()
        self.assertEqual(set(result), {
            "success", "item_id", "item_name", "forecast_period", "predictions", "model_info",
        })
        self.assertTrue(result["success"])
        self.assertEqual(result["forecast_period"], {"start_month": "2026-10", "months": 3})
        self.assertEqual([prediction["month"] for prediction in result["predictions"]], [
            "2026-10", "2026-11", "2026-12",
        ])
        self.assertEqual(len(result["predictions"]), payload["forecast_months"])
        self.assertEqual(result["predictions"][1]["predicted_demand"], 157.5)
        self.assertEqual(result["model_info"]["model_version"], "demand-linear-regression-v2")

    def test_predict_rejects_insufficient_history_without_fabricating_months(self) -> None:
        payload = {
            "item_id": 12,
            "item_name": "Bond Paper A4",
            "forecast_months": 1,
            "historical_data": [
                {"month": "2026-07", "issued_quantity": 120},
                {"month": "2026-09", "issued_quantity": 135},
            ],
        }
        with patch.dict(os.environ, {"ML_API_TOKEN": "validation-key"}):
            response = self.client.post(
                "/predict",
                json=payload,
                headers={"Authorization": "Bearer validation-key"},
            )

        self.assertEqual(response.status_code, 422)
        self.assertEqual(response.json()["error"]["code"], "INVALID_HISTORY")

    def test_predict_uses_consistent_validation_and_authentication_errors(self) -> None:
        payload = {
            "item_id": 12,
            "item_name": "Bond Paper A4",
            "forecast_months": 13,
            "historical_data": [],
        }
        with patch.dict(os.environ, {"ML_API_TOKEN": "validation-key"}):
            invalid = self.client.post(
                "/predict",
                json=payload,
                headers={"Authorization": "Bearer validation-key"},
            )
            unauthorized = self.client.post("/predict", json=payload)

        self.assertEqual(invalid.status_code, 422)
        self.assertEqual(invalid.json()["error"]["code"], "VALIDATION_ERROR")
        self.assertEqual(unauthorized.status_code, 401)
        self.assertEqual(unauthorized.json()["error"]["code"], "UNAUTHORIZED")

    def test_forecast_route_declares_multipart_upload_and_bearer_header(self) -> None:
        operation = main.app.openapi()["paths"]["/forecast"]["post"]

        self.assertIn("multipart/form-data", operation["requestBody"]["content"])
        self.assertTrue(
            any(
                parameter["name"] == "authorization" and parameter["in"] == "header"
                for parameter in operation["parameters"]
            )
        )

    def test_rejects_missing_or_invalid_bearer_token(self) -> None:
        with patch.dict(os.environ, {"ML_API_TOKEN": "validation-key"}):
            for authorization in (None, "Bearer wrong-key"):
                with self.subTest(authorization=authorization):
                    with self.assertRaises(HTTPException) as error:
                        main.forecast(upload_csv(), 3, authorization)
                    self.assertEqual(error.exception.status_code, 401)

    def test_returns_forecast_json_for_authenticated_csv(self) -> None:
        with patch.dict(os.environ, {"ML_API_TOKEN": "validation-key"}):
            result = main.forecast(upload_csv(), 3, "Bearer validation-key")

        self.assertEqual(result["schema_version"], 2)
        self.assertEqual(result["source_type"], "live")
        self.assertEqual(len(result["forecasts"]), 1)
        self.assertEqual(result["forecasts"][0]["inventory_id"], 1)

    def test_rejects_invalid_csv(self) -> None:
        with patch.dict(os.environ, {"ML_API_TOKEN": "validation-key"}):
            with self.assertRaises(HTTPException) as error:
                main.forecast(upload_csv(b""), 3, "Bearer validation-key")

        self.assertEqual(error.exception.status_code, 422)


if __name__ == "__main__":
    unittest.main()
