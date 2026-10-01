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

## Production Path

Install Python 3.10 or newer and the declared dependencies:

```powershell
python -m venv .venv-forecast
.\.venv-forecast\Scripts\Activate.ps1
python -m pip install -r ml\requirements.txt
```

Set `FORECAST_PYTHON_BINARY` to that environment's Python, then run `php artisan forecast:train` on a schedule outside chat requests. Laravel exports completed eligible demand to `storage/app/forecast/real-stock-out-data.csv`; the Python trainer writes `storage/app/forecast/forecast.json` and `storage/app/models/demand-model.joblib`. The separate `forecast/training-status.json` marks running, successful, or failed refreshes.

The production CSV and output preserve inventory and category IDs. Only approved completed stock-outs and eligible non-returnable consumable issuance are demand; pending requests are not training data. Grouping is by stable identity and calendar month. The live history window is incomplete, so months without events remain unknown rather than being filled with zero. The configured minimum history defaults to three observed months. When there is an extra month, the model reports a time-ordered one-month holdout MAE.

The output uses schema version 2 and declares `source_type: live`, model version, generation time, forecast month, history window, verified/unknown months, confidence, and validation status. Laravel validates this file and calculates all stock, pending-demand, safety-stock, priority, and suggested-quantity values. Never load Joblib artifacts from untrusted sources.

## Configuration

- `FORECAST_MINIMUM_HISTORY=3`
- `FORECAST_MAXIMUM_AGE_HOURS=168`
- `FORECAST_SAFETY_STOCK_RATE=0.25`
- `FORECAST_DEMO_MODE=false`

The sample/demo artifacts remain isolated from production paths. In a demonstration deployment, `FORECAST_DEMO_MODE=true` routes general forecast questions to the validated demo result; every response remains explicitly labeled **Sample/Demo** and does not calculate live procurement advice.
