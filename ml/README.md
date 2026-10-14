# Demand Forecast Pipelines

## Isolated Sample/Demo Path

Generate the demo result with:

```powershell
php artisan forecast:train --sample
```

This path reads only `ml/demo_stock_out_data.csv` and runs `ml/demo_forecast_demand.py`. It writes `storage/app/forecast/demo/forecast.json` and `storage/app/models/demo/demand-model.joblib`; it never writes the live forecast paths. Every output row retains `inventory_id` and `category_id`, so identical names with different identities stay separate. The JSON is labeled `source_type: demo` and includes the model version, generated time, forecast month, verified history window, months used, and unknown months.

The sample declares each identity's history window and whether it is complete. Missing months are zero-filled only for complete windows; gaps in incomplete windows remain unknown. Only verified months count toward the configured minimum. Rows below that minimum appear under `insufficient_history` and have no predicted quantity.

Chat uses the stored demo result only for an explicit sample/demo forecast request. The reports page reads the same validated file and labels it as sample data. Neither surface joins live stock or pending requests, calculates procurement recommendations, nor modifies inventory. Missing or invalid output is reported explicitly; no live result is substituted.

The demo Python dependencies are listed in `ml/requirements.txt`.

## Production API

`main.py` exposes the production trainer as a FastAPI service. It accepts the
Laravel CSV export and minimum-history setting at `POST /forecast`, protected
by the `ML_API_TOKEN` bearer token, and returns the same JSON document produced
by `generate_forecast`. `GET /health` is public for service health checks. The
API does not persist uploads or write/load a Joblib model; Laravel stores and
validates the returned forecast JSON.

On Render, the repository blueprint creates the `dnhs-demand-forecast` Python
web service. After its first deploy, copy its `ML_API_TOKEN` into
`FORECAST_API_TOKEN` on both the `dnhs-inventory` web service and the
`dnhs-forecast-train` cron service. Set `FORECAST_API_URL` on both to the
forecast service URL, without a trailing slash. Keep the token secret and do
not commit it. The cron job continues to export approved demand locally, then
posts the CSV to this API over HTTPS; the demo/sample trainer remains isolated
and local to the Laravel container.

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
`storage/app/forecast/real-stock-out-data.csv` and persists the API's JSON
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

The sample/demo artifacts remain isolated from production paths. In a demonstration deployment, `FORECAST_DEMO_MODE=true` routes general forecast questions to the validated demo result; every response remains explicitly labeled **Sample/Demo** and does not calculate live procurement advice.
