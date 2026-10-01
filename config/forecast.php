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
    'demo_mode' => (bool) env('FORECAST_DEMO_MODE', false),
    'demo' => [
        'input_path' => base_path('ml/demo_stock_out_data.csv'),
        'trainer_path' => base_path('ml/demo_forecast_demand.py'),
        'output_path' => 'forecast/demo/forecast.json',
        'model_path' => 'models/demo/demand-model.joblib',
        'minimum_history' => (int) env('DEMO_FORECAST_MINIMUM_HISTORY', 3),
    ],
];
