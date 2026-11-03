# Linear and Polynomial Regression Model Comparison

## Objective

Evaluate Linear Regression and degree-2 Polynomial Regression for monthly
inventory demand forecasting using a separate experiment. Keep the existing
production forecasting model unchanged during the comparison.

## Tasks

- [x] Create an experimental folder at `ml/model_comparison/` for the
  comparison workflow, inputs, and results.
- [x] Prepare historical inventory issuance data aggregated by inventory item
  and calendar month. Preserve item identities so different inventory records
  are not combined.
- [x] Distinguish confirmed zero-issuance months from months with incomplete
  records. Use calendar-month positions when representing time gaps.
- [x] Select items with enough verified history to support the training and
  testing split and fit both models.
- [x] Split each item's data chronologically into training and testing periods.
  Use the same split and test months for both models; do not shuffle the data.
- [x] Train Linear Regression and degree-2 Polynomial Regression on the same
  training data, using month position as the input and monthly quantity issued
  as the target.
- [x] Generate predictions for the held-out test months and calculate Mean
  Absolute Error (MAE) and Root Mean Squared Error (RMSE) for each model.
- [x] Summarize results across items, including the number of items and test
  months evaluated and each model's MAE and RMSE.
- [x] Document the training/testing periods, data limitations, evaluation
  results, and which model performed better on the held-out data.
- [ ] Consider repeating the evaluation at multiple chronological cutoffs
  (walk-forward validation) to check whether the comparison depends on one
  particular split. The current four-month sample supports only one cutoff;
  collect more verified history to complete this task.
- [ ] Review the experimental results before proposing any production-model
  change. Do not modify or replace the live forecasting model as part of this
  comparison.