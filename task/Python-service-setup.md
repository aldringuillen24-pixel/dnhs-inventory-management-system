# GitHub Actions: Run the Demand Forecast Model

This setup uses GitHub Actions to run the daily forecast. The runner connects
to the production database, Laravel exports eligible completed-month usage,
and the Python model runs directly on the runner. Laravel then validates and
saves the forecast to the database.

There is no separate hosted Python API in this setup. The Python model runs
only during the scheduled workflow, so it does not need a continuously running
ML service. The Laravel website remains deployed separately.

## 1. Confirm the Render database can be reached externally

The database can remain on Render, but GitHub-hosted runners run outside
Render. In the Render Dashboard, open the database and find its **external**
connection details. Do not use a Render internal hostname: GitHub-hosted
runners cannot reach Render's private network.

Confirm the database plan and network settings allow external connections.
If Render does not provide an externally reachable database connection for
your plan, the workflow cannot connect directly; use a supported external
connection method or a runner that can access the private database network.

## 2. Add GitHub repository secrets

In the GitHub repository, open **Settings → Secrets and variables → Actions**
and add these under **Repository secrets**:

| Secret | Value |
| --- | --- |
| `APP_KEY` | The same Laravel application key used by the deployed website |
| `DB_HOST` | The database's external hostname from Render |
| `DB_PORT` | The database's external port |
| `DB_DATABASE` | The production database name |
| `DB_USERNAME` | The production database username |
| `DB_PASSWORD` | The production database password |
| `HISTORICAL_USAGE_CSV` | The complete historical CSV contents, including the header row |

Add this under **Repository variables**:

| Variable | Value |
| --- | --- |
| `APP_URL` | The deployed Laravel website's public HTTPS URL |

For `HISTORICAL_USAGE_CSV`, open your local historical CSV, copy all of its
contents including the first header line, then paste the contents as the
secret's value. The file must have the same columns as
`dummay-datasets.csv`. The secret keeps the usage rows out of the repository;
the workflow writes them to a temporary file on its runner.

Never commit secret values or put them directly in the workflow file. The
website's own environment must continue to use its production database
connection settings. The workflow does not need `FORECAST_API_URL` or
`FORECAST_API_TOKEN`, because it runs the Python model locally.

## 3. Workflow behavior

The workflow at
[.github/workflows/demand-forecast.yml](../.github/workflows/demand-forecast.yml)
runs every day at **02:00 UTC** (10:00 AM in the Philippines). It can also be
started manually from **GitHub → Actions → Demand forecast training → Run
workflow**. Scheduled runs use the workflow on the repository's default
branch.

The job:

1. Checks out the repository and installs PHP dependencies.
2. Sets up Python and installs the model's `scikit-learn` and `joblib`
   dependencies.
3. Writes the `HISTORICAL_USAGE_CSV` secret to a temporary file on the runner.
4. Runs `php artisan forecast:train --local-model --no-interaction`.
5. Laravel connects to the production database, exports eligible demand
   movements, maps historical rows to real inventory by exact item name,
   category, and unit, then invokes `ml/forecast_demand.py` on the combined
   history.
6. Laravel validates the forecast output and saves a successful live result
   to the database.

The historical file's item names, categories, and units must each identify
exactly one non-serialized inventory item. Matching ignores letter case and
surrounding whitespace; the file's numeric IDs are ignored. The historical
usage must be separate from the database usage; overlapping records would be
counted twice.

The workflow configuration is:

```yaml
name: Demand forecast training

on:
  schedule:
    - cron: "0 2 * * *"
  workflow_dispatch:

permissions:
  contents: read

concurrency:
  group: demand-forecast-training
  cancel-in-progress: false

jobs:
  train:
    runs-on: ubuntu-latest
    timeout-minutes: 15
    env:
      APP_ENV: production
      APP_DEBUG: "false"
      APP_KEY: ${{ secrets.APP_KEY }}
      APP_URL: ${{ vars.APP_URL }}
      DB_CONNECTION: pgsql
      DB_HOST: ${{ secrets.DB_HOST }}
      DB_PORT: ${{ secrets.DB_PORT }}
      DB_DATABASE: ${{ secrets.DB_DATABASE }}
      DB_USERNAME: ${{ secrets.DB_USERNAME }}
      DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
      FORECAST_MINIMUM_HISTORY: "3"
      LOG_CHANNEL: stderr

    steps:
      - name: Check out repository
        uses: actions/checkout@v4

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.2"
          extensions: bcmath, curl, gd, intl, mbstring, pcntl, pdo_mysql, pdo_pgsql, zip
          coverage: none

      - name: Install production dependencies
        run: composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

      - name: Set up Python
        uses: actions/setup-python@v5
        with:
          python-version: "3.12"

      - name: Install Python model dependencies
        run: python -m pip install --disable-pip-version-check "scikit-learn>=1.4,<2.0" "joblib>=1.3,<2.0"

      - name: Prepare historical usage data
        env:
          HISTORICAL_USAGE_CSV: ${{ secrets.HISTORICAL_USAGE_CSV }}
        run: |
          python - <<'PY'
          import os
          from pathlib import Path

          content = os.environ.get("HISTORICAL_USAGE_CSV", "")
          if not content.strip():
              raise SystemExit("Add the HISTORICAL_USAGE_CSV repository secret")

          path = Path("storage/app/datasets/historical-usage.csv")
          path.parent.mkdir(parents=True, exist_ok=True)
          path.write_text(content, encoding="utf-8")
          PY

      - name: Train and save the live forecast
        run: php artisan forecast:train --local-model --no-interaction
```

## 4. Verify it works

1. Add the secrets and variable above.
2. Push the workflow and Laravel training-command changes to the default
   branch.
3. In GitHub, open **Actions → Demand forecast training** and select **Run
   workflow**.
4. Check that **Train and save the live forecast** completes and prints
   `Demand forecast trained and saved.`
5. Open the custodian's **Demand Forecast** page and reload the latest result.

GitHub can delay scheduled workflow starts during busy periods. A successful
run only saves a forecast; it does not change inventory or place procurement
orders. The custodian's reload button only loads the saved result.

## 5. Forecast timing

Training runs daily, but incomplete current-month usage is excluded. Each run
uses eligible recorded demand through the last completed month and forecasts
the following month.

| When the workflow runs | Usage included through | Forecast month |
| --- | --- | --- |
| Any day in October | September | October |
| Any day in November | October | November |
| Any day in December | November | December |

For example, after October ends, a successful November run can use October's
recorded usage to forecast November.

## Troubleshooting

- **Database connection failure:** make sure the workflow uses the Render
  database's external hostname and port, credentials are correct, and external
  connections are enabled for the database plan.
- **Python trainer failure:** check the workflow logs for dependency or model
  errors. Confirm the `ml/forecast_demand.py` file and its dependencies are
  available in the checked-out repository.
- **Historical row cannot be matched:** verify its item name, category, and
  unit match exactly one non-serialized inventory item. The IDs in the
  historical CSV are ignored; Laravel replaces them with the real inventory
  IDs.
- **No historical data or missing secret:** add the complete CSV, including
  its header, as the `HISTORICAL_USAGE_CSV` repository secret.
- **Missing or invalid `APP_KEY`:** set the exact deployed Laravel key as the
  `APP_KEY` repository secret.
- **Workflow does not start on schedule:** confirm the workflow is on the
  default branch and enabled in GitHub Actions. GitHub may delay scheduled
  runs.
- **Forecast did not update:** inspect the job logs and Laravel logs. A failed
  run marks training as failed and does not replace the last successful
  forecast payload.

## Related project files

- [GitHub Actions workflow](../.github/workflows/demand-forecast.yml)
- [Laravel training command](../app/Console/Commands/TrainDemandForecast.php)
- [Python forecasting model](../ml/forecast_demand.py)
- [ML pipeline documentation](./ml-docu.md)
