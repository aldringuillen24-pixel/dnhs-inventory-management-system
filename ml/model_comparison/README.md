# Experimental Model Comparison

This folder is reserved for the separate evaluation of Linear Regression and
degree-2 Polynomial Regression for monthly inventory demand forecasting.

`example_monthly_issuance.csv` contains the monthly records provided for the
initial comparison. It has 11 item identities with four complete months each
(June–September 2026). Confirm the records against the intended historical
source before treating evaluation results as representative. Four months per
item also limits how much chronological holdout testing can be done.

## Eligible items for this sample

All 11 item identities have four verified consecutive months, so they can be
included in a minimal chronological holdout: use the first three months to fit
both models and the fourth month as the test observation. Three training
observations are the minimum needed to fit a degree-2 polynomial with an
intercept. This leaves only one test observation per item, so the resulting
metrics are exploratory and are not strong evidence that one model generalizes
better. Prefer collecting a longer history before drawing conclusions.

The split has been prepared from `example_monthly_issuance.csv`:

- `train.csv`: June, July, and August 2026 (33 rows across 11 items).
- `test.csv`: September 2026 (11 rows, one for each of the same 11 items).

Both models must use these exact same training and test rows. Do not shuffle
the months or use test observations during model fitting.

Train both models for each item with:

```powershell
python ml\model_comparison\train_models.py
```

The script uses scikit-learn dependencies from `ml/requirements.txt`. It fits
both model types on the same calendar-month offsets and quantities from
`train.csv`, saves the model bundle as `trained_models.joblib`, and records
training coverage in `training_summary.json`. Model files are generated local
artifacts and are not committed.

Evaluate against the held-out test data with:

```powershell
python ml\model_comparison\evaluate_models.py
```

The evaluator applies a zero lower bound to both models' predictions, calculates
per-item and same-unit-group MAE/RMSE, writes row-level predictions to
`predictions.csv`, and writes the metrics to `evaluation_results.json`. With
multiple measurement units in the sample, it does not combine errors across
units into a single score. With only one test month per item, interpret these
metrics as exploratory.

## Initial results

The models were trained on June–August 2026 and tested on September 2026.
Evaluation covered 11 items and 11 item-month observations (one test month per
item). The table reports MAE / RMSE in each item's issuance unit; items with
different units are not pooled together.

| Unit | Items | Linear Regression MAE / RMSE | Degree-2 Polynomial MAE / RMSE |
| --- | ---: | ---: | ---: |
| bottles | 1 | 5.00 / 5.00 | 20.00 / 20.00 |
| boxes | 2 | 11.00 / 12.37 | 38.50 / 40.18 |
| liters | 1 | 8.67 / 8.67 | 37.00 / 37.00 |
| packs | 1 | 9.33 / 9.33 | 46.00 / 46.00 |
| pieces | 3 | 18.11 / 22.07 | 87.00 / 102.82 |
| reams | 2 | 29.50 / 30.01 | 134.50 / 136.90 |
| rolls | 1 | 5.67 / 5.67 | 24.00 / 24.00 |

Linear Regression had lower MAE and RMSE for all 11 items in this holdout.
Because there is only one held-out month per item and only four months of
sample history, this result is preliminary and does not establish that Linear
Regression will generalize better. See `evaluation_results.json` for the
per-item results and `predictions.csv` for each observed and predicted value.

## Walk-forward validation

Run expanding-window validation on every available historical cutoff:

```powershell
python ml\model_comparison\walk_forward.py
```

For each item, the evaluator trains both models on at least the first three
verified months, then predicts each later verified month using only preceding
months. It retains calendar-month offsets, fills gaps with zero only for
histories marked complete, and leaves incomplete-history gaps unknown.
Predictions and per-cutoff/unit metrics are written to
`walk_forward_predictions.csv` and `walk_forward_results.json`.

The supplied four-month history provides only one valid cutoff (September
2026), so it cannot establish stability across multiple cutoffs. The script
reports this limitation; more verified historical months are needed to
complete a multi-cutoff comparison.



### Step-by-step procedure with more history

Run these commands in **PowerShell from the repository root**
(`C:\xampp\htdocs\dnhsInventoryManagementSystem`). The walk-forward script
trains both models itself for each cutoff; you do not need to run
`train_models.py` or `evaluate_models.py` for this procedure.

1. **Export the history to CSV.** Use the same headers as
   `example_monthly_issuance.csv`, including item/category IDs, item name,
   unit, quantity, stock-out date, history start/end month, completeness flag,
   movement type, and status. Include eligible approved demand records. The
   script groups rows by item identity and sums quantities in the same
   calendar month.
2. **Check the data.** Confirm IDs, dates, quantities, units, and history
   windows are accurate. Set `history_complete` to `true` only when every
   month in that item's declared window is known. A missing issuance in a
   complete window is treated as zero; a missing month in an incomplete
   window remains unknown. Do not include pending, unapproved, returns, or
   other non-demand movements as demand.
3. **Save the CSV.** For example, save it as
   `C:\forecast-data\historical_monthly_issuance.csv`. Keeping the source data
   outside the repository is recommended if it contains private information.
4. **Open PowerShell at the repository root** and make sure Python and the
   required packages are available. If scikit-learn or joblib is missing, run:

   ```powershell
   python -m pip install scikit-learn joblib
   ```

5. **Run the comparison.** This command reads the history CSV and writes the
   result files into `ml\model_comparison\`:

   ```powershell
   python ml\model_comparison\walk_forward.py `
     --input "C:\forecast-data\historical_monthly_issuance.csv" `
     --predictions ml\model_comparison\walk_forward_predictions.csv `
     --summary ml\model_comparison\walk_forward_results.json
   ```

   Replace the `--input` path if you saved the CSV elsewhere. A successful
   run prints the summary in the PowerShell window.
6. **Open the results.**
   - Open `ml\model_comparison\walk_forward_results.json` in VS Code for the
     cutoff count, test observation count, and MAE/RMSE by unit and cutoff.
   - Open `ml\model_comparison\walk_forward_predictions.csv` in VS Code or
     Excel to see each item's actual quantity, both predictions, and errors.
   - Check `multiple_cutoffs_available` in the JSON. At least four verified
     months per item are needed for one cutoff with three training months.
     More months provide more cutoffs.
7. **Write up the findings.** State the date range, number of items, number
   of test cutoffs and observations, and MAE/RMSE for each model. Compare
   results by unit and item; do not combine unlike measurement units into one
   headline score. Lower MAE means smaller average absolute errors; lower RMSE
   means fewer/lower large errors.

Walk-forward cutoffs overlap, and using them to choose a model does not provide
a final unbiased performance estimate. If that estimate is needed, reserve the
latest block of months before comparing models, do not use it to choose the
model, and evaluate on it only once after the comparison.

## History and time handling

The CSV's `history_start_month`, `history_end_month`, and `history_complete`
fields describe the observation window for each inventory item:

- When `history_complete` is `true`, the full declared window is verified. A
  month with no issuance record may be represented as zero only if the source
  confirms there was no issuance.
- When `history_complete` is `false`, a month without an issuance record is
  unknown. Do not fill it with zero or use it as an observed training or test
  value.
- Represent time using calendar-month offsets from the start of the window
  (for example, June = 0, July = 1, August = 2, September = 3). Do not
  renumber only the observed months, since that would hide gaps.

The provided sample has an issuance row for every month of every item, so it
does not itself demonstrate either a confirmed zero-issuance month or an
unknown gap.

The experiment must use a chronological train/test split and report MAE and
RMSE for both models. Keep its input data, comparison workflow, and results
separate from the production forecasting pipeline. Do not change the live model
as part of this experiment.
