# Demand Forecast ML: How It Works

## Purpose

The demand forecast estimates how many eligible supply items may be needed in
the next forecast month. Laravel prepares recorded usage, the Python model
calculates the demand estimate, and Laravel saves the result for the Property
Custodian's Demand Forecast page.

The forecast is advice for planning. It does not place purchase orders or
change inventory by itself.

## Monthly timing and automatic schedule

The GitHub Actions workflow runs
`php artisan forecast:train --local-model --no-interaction` every day at
`02:00 UTC` (`0 2 * * *` in
[demand-forecast.yml](../.github/workflows/demand-forecast.yml)). The daily
run does not mean that the forecast month advances every day.

At each run, Laravel:

1. Finds the last completed calendar month.
2. Excludes the current, unfinished month from the training data.
3. Asks the model to forecast the month immediately after the last completed
   month.
4. Saves the new live forecast when training succeeds.

For example:

| When training runs | Usage included through | Forecast month |
| --- | --- | --- |
| Any day in October | September | October |
| Any day in November | October | November |
| Any day in December | November | December |

Therefore, a run halfway through October still uses data only through
September. After October ends, a November run can include October's recorded
usage and forecast November. If the scheduled run succeeds, this happens
automatically; the custodian does not need to retrain the model.

The **Reload latest result** button on the custodian page only fetches the
saved result. It does not start a training run.

## What is included in training

Laravel exports positive-quantity demand movements dated before the current
month begins. Eligible inventory must be non-serialized and have a category
name matching the configured supply/consumable terms in
[TrainDemandForecast.php](../app/Console/Commands/TrainDemandForecast.php).

The export includes qualifying stock-outs and issuances. Assignment movements
are included only when their linked request and transaction meet the
completion/approval checks. Pending requests are not historical training
usage; they are considered later when calculating procurement suggestions.
Returns, transfers, stock-ins, disposals, serialized assets, and the current
unfinished month are not training demand.

Data is grouped by inventory and category identity, then by calendar month.
The production export marks its history as incomplete: a month with no
recorded movement is treated as unknown, not automatically as zero usage.
Only usage actually recorded in the database can be learned from.

## How the model estimates demand

The production model is a scikit-learn linear regression trend in
[forecast_demand.py](../ml/forecast_demand.py). For each item, it:

- Uses recorded monthly issued quantities and their calendar-month positions.
- Fits a straight-line trend and projects it one month ahead.
- Clamps negative estimates to zero and rounds to a whole unit.
- Requires at least three observed months by default
  (`FORECAST_MINIMUM_HISTORY=3`).

Items below the minimum history are marked **insufficient history** and have
no demand estimate. Confidence labels are based only on the number of observed
months: **Low** below 6 months, **Medium** at 6-11 months, and **High** at 12
or more. These labels do not prove the prediction is accurate.

When enough months are available, the output also includes a one-month,
time-ordered holdout error (MAE). It is a small validation check, not a
guarantee of future accuracy. The model does not account for school calendars,
holidays, future events, changing enrollment, prices, or stock levels when
predicting demand.

## From demand estimate to procurement suggestion

The model predicts demand; Laravel separately estimates the amount that may
need to be procured for an item that matches inventory. For a matched live
item, the formula is:

```text
suggested quantity =
  max(0, forecast demand - available stock - pending requests + unmet demand)
```

- **Available stock** is the quantity currently recorded as available.
- **Pending requests** are requests awaiting approval or transfer approval.
- **Unmet demand** is demand from requests that could not be filled because
  stock was unavailable.

For example, if demand is 24, available stock is 0, pending requests are 0,
and unmet demand is 0, the suggestion is 24. If 23 units are later received
and recorded as available, a newly loaded recommendation would be 1, assuming
demand and other requests have not changed. The original demand forecast
remains 24.

Priority is based on the stock gap: **Urgent** means no available stock and
pending requests do not cover forecast demand; **High** means stock plus
pending requests still fall short; **Medium** means the gap exists because of
unmet requests; **Normal** means no procurement gap or no usable estimate.

If an item cannot be matched to inventory, the row is labeled demand-only.
There is no stock or pending-request information to subtract, so its suggested
quantity may equal the full predicted demand. Verify the item and stock before
using that amount.

The live matched-item formula does not add the configured safety-stock rate.
The `FORECAST_SAFETY_STOCK_RATE` setting should not be interpreted as an extra
buffer in live procurement quantities.

## Training, storage, and errors

In the GitHub Actions production workflow, the Laravel command loads the
historical CSV from a GitHub Actions secret, maps its items to real inventory
using item name, category, and unit, and combines those rows with eligible
database usage. It runs `ml/forecast_demand.py` locally on the runner,
validates the output, and stores the versioned live forecast in the
`forecast_payloads` database table. The runner needs external access to the
production database. Historical rows must not duplicate database usage.
PHP reads the saved forecast and joins it with current inventory and request
data.

The forecast page will not treat an unsuccessful run as a fresh result. If
database access, Python execution, or forecast validation fails, training is
marked failed and the failure is logged. Old results are considered stale
after `FORECAST_MAXIMUM_AGE_HOURS` (default 168 hours / 7 days). Check the
GitHub Actions run and Laravel logs if the forecast is missing, failed, or
stale.

The custodian's page displays the forecast month, generation time,
recommendations, and warnings. It does not provide a retraining action.

## Running and testing

- **Production GitHub Actions workflow:** Runs
  `php artisan forecast:train --local-model --no-interaction` daily. The
  `--local-model` option uses the production database export and executes the
  Python model on the Actions runner; no hosted Python API is required.
- **Production API option:** `php artisan forecast:train --no-interaction`
  continues to use `FORECAST_API_URL` and `FORECAST_API_TOKEN` when a hosted
  forecasting API is configured.
- **Local/testing live-pipeline test:** `php artisan forecast:train --test-dataset`
  uses the dummy dataset and replaces the local live forecast. It is rejected
  outside local/testing environments.
- **Isolated sample/demo:** `php artisan forecast:train --sample` uses demo
  data and stores a separately labeled demo result. It does not replace or
  train the live forecast.

The GitHub Actions workflow needs the production database connection values
and `APP_KEY` configured as GitHub repository secrets. See the
[GitHub Actions setup guide](./Python-service-setup.md). Never commit secret
values to this repository.

## Related source files

- [Forecast trainer and eligible-data export](../app/Console/Commands/TrainDemandForecast.php)
- [Forecast settings](../config/forecast.php)
- [Stored forecast and procurement calculations](../app/Services/Forecasting/StoredDemandForecastService.php)
- [Python production model](../ml/forecast_demand.py)
- [ML service and deployment notes](../ml/README.md)
- [Custodian forecast page](../resources/js/spa/views/custodian/ForecastRecommendations.vue)