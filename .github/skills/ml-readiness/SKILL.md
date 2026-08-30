---
name: ml-readiness
description: "Use when designing or implementing inventory forecasting, procurement recommendations, anomaly detection, data quality checks, feature engineering, model evaluation, or safe ML integration for DNHS."
---
# ML Readiness

Use this workflow before adding machine learning to the inventory system.

## Establish the Evidence
- Inspect inventory, assignment request, transaction, category, and migration history.
- Measure completeness, time coverage, duplicates, status consistency, and quantity integrity.
- Identify whether the data has enough history for the proposed prediction.
- Record missing data and cold-start behavior instead of inventing values.

## Define the Baseline
- State the falsifiable hypothesis.
- Define the prediction target, time window, features, labels, and train/test split.
- Start with a transparent baseline such as recent demand, moving average, or reorder threshold.
- Choose metrics that match the action, such as MAE, precision, recall, or calibration.
- Include confidence, freshness, assumptions, and a deterministic fallback.

## Integration Rules
- Keep training and inference separate from latency-sensitive Laravel requests when needed.
- Do not let predictions directly mutate inventory or approve procurement.
- Minimize personal data; aggregate inventory data is preferred.
- Version datasets and models, validate outputs, and make recommendations explainable.
- Do not add an ML runtime or dependency without documenting setup and operating cost.

## Validation
Test calculations with fixed fixtures and edge cases. Compare the model with the baseline and document limitations, drift signals, reproducibility, and deployment risks.
