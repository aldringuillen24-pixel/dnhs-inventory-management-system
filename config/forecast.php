<?php

return [
    /*
     * Override this when the server uses a virtual environment, for example:
     * FORECAST_PYTHON_BINARY=C:\\path\\to\\venv\\Scripts\\python.exe
     */
    'python_binary' => env('FORECAST_PYTHON_BINARY', 'python'),
    'minimum_history' => (int) env('FORECAST_MINIMUM_HISTORY', 3),
    'maximum_age_hours' => (int) env('FORECAST_MAXIMUM_AGE_HOURS', 168),
    'safety_stock_rate' => (float) env('FORECAST_SAFETY_STOCK_RATE', 0.25),
    /*
     * Unusual consumption.
     *
     * Deliberately NOT a model. The trained forecast is a linear trend over month
     * offsets (ml/forecast_demand.py), so it has no concept of an outlier: a spike
     * simply raises the slope and it reports the higher number as the new normal.
     * Asking it to judge what is unusual would be asking a question it cannot
     * answer. So the spike is measured against the item's own verified history
     * here, and these are policy thresholds rather than learned parameters.
     *
     * `minimum_verified_months` is the floor for being willing to call anything
     * unusual at all. It sits above `minimum_history` on purpose: at three months
     * a single bulk purchase is indistinguishable from a trend, so the honest
     * answer below that depth is "cannot tell". Note that in practice only rows
     * with 12+ months can reach Buy now (that is what High confidence means), so
     * this floor does not suppress the demotion on a real forecast.
     *
     * `spike_ratio` is how many times the latest verified month must exceed the
     * median of the months before it.
     */
    'consumption_anomaly' => [
        'minimum_verified_months' => (int) env('FORECAST_SPIKE_MIN_MONTHS', 6),
        'spike_ratio' => (float) env('FORECAST_SPIKE_RATIO', 3.0),
    ],
    'demo_mode' => (bool) env('FORECAST_DEMO_MODE', false),
    'demo' => [
        'input_path' => base_path('ml/demo_stock_out_data.csv'),
        'trainer_path' => base_path('ml/demo_forecast_demand.py'),
        'output_path' => 'forecast/demo/forecast.json',
        'model_path' => 'models/demo/demand-model.joblib',
        'minimum_history' => (int) env('DEMO_FORECAST_MINIMUM_HISTORY', 3),
    ],
];
