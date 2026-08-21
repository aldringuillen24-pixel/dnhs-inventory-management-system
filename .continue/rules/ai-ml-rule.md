---
description: AI and machine learning development standards for inventory prediction and decision support
---

# AI/ML Rules

- Keep machine learning functionality separate from the Laravel application.
- Use Python for machine learning functionality.
- Use FastAPI when exposing machine learning functionality as an API service.
- Use scikit-learn for traditional machine learning models unless another framework is explicitly required.
- Use Joblib for saving and loading compatible trained models.
- Do not place machine learning training logic directly inside Laravel controllers.
- Keep data preprocessing, feature engineering, model training, prediction, and evaluation logically separated.
- Validate input data before generating predictions.
- Handle missing, invalid, and unexpected data appropriately.
- Do not train models on production data without appropriate validation and authorization.
- Separate model training from prediction/inference.
- Save trained models as versioned artifacts when appropriate.
- Do not retrain a model every time a prediction is requested.
- Evaluate models using appropriate metrics for the problem.
- Avoid data leakage between training and testing datasets.
- Keep machine learning dependencies isolated from Laravel dependencies.
- Clearly handle prediction failures and unavailable models.
- Do not present predictions as guaranteed outcomes.
- Keep AI-generated recommendations explainable where practical.
- Log important prediction errors without exposing sensitive information.