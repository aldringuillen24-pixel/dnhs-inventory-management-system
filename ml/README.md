# Demand Forecast Pipelines

Python forecasting code and its requirements live in `ml/`; Laravel exports eligible demand, calls the Python forecasting service, validates the returned JSON, and stores the live document in `forecast_payloads.payload` with `source_type=live`. Production Laravel reads live forecasts from that database row only. `storage/app/forecast/` holds local training inputs/status and isolated demo output, while Joblib models belong under `storage/app/models/` for local development only and are never loaded by PHP.

# training model
python ml\forecast_demand.py --input storage\app\datasets\dummay-datasets.csv --minimum-history 3 --output storage\app\forecast\dummy-forecast.json --model-output storage\app\models\dummy-demand-model.joblib


## Isolated Sample/Demo Path

Generate the demo result with:

```powershell
php artisan forecast:train --sample
```

This path reads only `ml/demo_stock_out_data.csv` and runs `ml/demo_forecast_demand.py`. It writes `storage/app/forecast/demo/forecast.json` and `storage/app/models/demo/demand-model.joblib`; it never writes the live forecast paths. Every output row retains `inventory_id` and `category_id`, so identical names with different identities stay separate. The JSON is labeled `source_type: demo` and includes the model version, generated time, forecast month, verified history window, months used, and unknown months.

The sample declares each identity's history window and whether it is complete. Missing months are zero-filled only for complete windows; gaps in incomplete windows remain unknown. Only verified months count toward the configured minimum. Rows below that minimum appear under `insufficient_history` and have no predicted quantity.

The AI Inventory Assistant's historical-usage comparison reads the bundled
`resources/data/historical-stock-outs.csv` directly. It aggregates approved
stock-out quantities by item and month, and labels the comparison as historical
usage data. This is separate from live activity comparisons, which continue to
read database records. Keep the bundled CSV synchronized with the intended
historical source data when updating the June–September history.

Chat uses the stored demo result only for an explicit sample/demo forecast request. The reports page reads the same validated file and labels it as sample data. Demo rows may be matched to inventory by item name, category, and unit to calculate a clearly labeled stock-adjusted recommendation; unmatched rows remain demand-only. This does not modify inventory or the live forecast. Missing or invalid output is reported explicitly; no live result is substituted.

The demo Python dependencies are listed in `ml/requirements.txt`.

## Testing demand forecasts with the dummy dataset

On a local or testing environment, run:

```powershell
php artisan forecast:train --test-dataset
```

This trains directly from `storage/app/datasets/dummay-datasets.csv` using the
local Python trainer and saves the result to the local live forecast database
record. It replaces the current local live forecast, so use it only when you
intend to test the app with sample demand. Each forecast item is matched to
inventory by item name, category, and unit. Matched items use available stock,
pending requests, and unmet requests for suggested procurement. Unmatched items
still appear with predicted demand but no stock-based suggestion. It does not
add inventory or movement records, and the original CSV is not modified. The
option is rejected outside local/testing environments. Run the regular command
without `--test-dataset` to refresh from database demand instead.

## Production API

`main.py` exposes the production trainer as a FastAPI service. It accepts the
Laravel CSV export and minimum-history setting at `POST /forecast`, protected
by the `ML_API_TOKEN` bearer token, and returns the same JSON document produced
by `generate_forecast`. `GET /health` is public for service health checks. The
API does not persist uploads or write/load a Joblib model; Laravel stores and
validates the returned forecast JSON.

An authenticated `POST /predict` JSON endpoint is also available for one item.
It uses the same scikit-learn `LinearRegression` model and monthly
issued-quantity target as the production trainer. Its request accepts
`item_id`, `item_name`, `forecast_months` (1–12), and observed monthly
`historical_data` records with `month` and `issued_quantity`.
`stock_out_quantity` and `stock_level` are optional, validated metadata and
are not model inputs. The API does not infer demand from stock-outs, infer
zero-usage months, or use stock levels. At least `FORECAST_MINIMUM_HISTORY`
distinct observed months are required (default 3); gaps remain gaps and the
model uses the calendar offsets of the observed months. The fitted trend is
extrapolated for each requested future month and predictions remain decimal
and non-negative.

The existing Laravel scheduled training flow remains on `POST /forecast`: it
sends the approved movement CSV and persists the established version-2
forecast document. This preserves existing storage and display behavior;
`/predict` is an additional JSON contract and does not silently replace that
pipeline.

Example JSON body (the quantities are illustrative input, not model output):

```json
{
  "item_id": 12,
  "item_name": "Bond Paper A4",
  "forecast_months": 3,
  "historical_data": [
    {"month": "2026-07", "issued_quantity": 120},
    {"month": "2026-08", "issued_quantity": 150},
    {"month": "2026-09", "issued_quantity": 135}
  ]
}
```

Each successful response contains `success`, the requested item identity,
`forecast_period` (`start_month` and `months`), exactly the requested number
of monthly `predictions`, and `model_info` with the actual model version.
Errors use `{ "success": false, "error": { "code", "message", "details" } }`.
Validation failures return HTTP 422, invalid credentials return HTTP 401, and
unexpected prediction failures return a generic HTTP 500 response without
internal exception details.

On Render, the repository blueprint creates the `dnhs-demand-forecast` Python
web service. After its first deploy, copy its `ML_API_TOKEN` into
`FORECAST_API_TOKEN` on both the `dnhs-inventory` web service and the
`dnhs-forecast-train` cron service. Set `FORECAST_API_URL` on both to the
forecast service URL, without a trailing slash. Keep the token secret and do
not commit it. The cron job continues to export approved demand locally, then
posts the CSV to this API over HTTPS; the demo/sample trainer remains isolated
and local to the Laravel container.

To deploy this service on Railway instead, create a Python service from this
repository with its root directory set to `ml`. `ml/railway.json` specifies
the Uvicorn start command and `/health` health check. Set `ML_API_TOKEN` in
Railway service variables, generate a public HTTPS domain, and set
`FORECAST_API_URL` and the same `FORECAST_API_TOKEN` in the Laravel Render
service. The current Laravel cron continues to call `/forecast`; both
endpoints are hosted by this service.

To run the API locally:

Install Python 3.10 or newer and the declared dependencies:

```powershell
python -m venv .venv-forecast
.\.venv-forecast\Scripts\Activate.ps1
python -m pip install -r ml\requirements.txt
uvicorn main:app --app-dir ml --reload
```

Set `ML_API_TOKEN` in the environment where Uvicorn runs and set
`FORECAST_API_URL` and the matching `FORECAST_API_TOKEN` in Laravel. Run
`php artisan forecast:train` on a schedule outside chat requests. Laravel
exports completed eligible demand to
`storage/app/datasets/real-stock-out-data.csv` and persists the API's JSON
response to the shared forecast database. The separate
`forecast/training-status.json` marks running, successful, or failed refreshes.

The production CSV and output preserve inventory and category IDs. Only approved completed stock-outs and eligible non-returnable consumable issuance are demand; pending requests are not training data. Grouping is by stable identity and calendar month. The live history window is incomplete, so months without events remain unknown rather than being filled with zero. The configured minimum history defaults to three observed months. When there is an extra month, the model reports a time-ordered one-month holdout MAE.

The output uses schema version 2 and declares `source_type: live`, model version, generation time, forecast month, history window, verified/unknown months, confidence, and validation status. Laravel validates this file and calculates all stock, pending-demand, safety-stock, priority, and suggested-quantity values. Never load Joblib artifacts from untrusted sources.

## Configuration

- `FORECAST_MINIMUM_HISTORY=3`
- `FORECAST_MAXIMUM_AGE_HOURS=168`
- `FORECAST_SAFETY_STOCK_RATE=0.25`
- `FORECAST_DEMO_MODE=false`
- `FORECAST_API_URL` and `FORECAST_API_TOKEN` for the protected forecasting service
- `ML_API_TOKEN` for the FastAPI service
- `FORECAST_MINIMUM_HISTORY` for `/predict`'s minimum distinct observed months

The sample/demo artifacts remain isolated from production paths. In a demonstration deployment, `FORECAST_DEMO_MODE=true` routes general forecast questions to the validated demo result; every response remains explicitly labeled **Sample/Demo** and does not calculate live procurement advice.
