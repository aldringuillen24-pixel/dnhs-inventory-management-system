from __future__ import annotations

import io
import os
import sys
import unittest
from pathlib import Path
from unittest.mock import patch

from fastapi import HTTPException, UploadFile

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
    def test_health_is_available_without_authentication(self) -> None:
        self.assertEqual(main.health(), {"status": "ok"})

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
