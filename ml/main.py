"""HTTP API for production demand forecasting."""

from __future__ import annotations

import os
import re
import secrets
import shutil
import tempfile
import logging
import math
from pathlib import Path
from typing import Annotated, Any

from fastapi import FastAPI, File, Form, Header, HTTPException, Request, UploadFile
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field, validator

from forecast_demand import (
    MODEL_VERSION,
    generate_forecast,
    month_sequence,
    parse_export,
    predict_monthly_demand,
)

app = FastAPI(title="DNHS Demand Forecast API")
logger = logging.getLogger(__name__)


class HistoricalMonth(BaseModel):
    class Config:
        extra = "forbid"

    month: str
    issued_quantity: float = Field(ge=0, allow_inf_nan=False)
    stock_out_quantity: float | None = Field(default=None, ge=0, allow_inf_nan=False)
    stock_level: float | None = Field(default=None, ge=0, allow_inf_nan=False)

    @validator("month")
    def validate_month(cls, value: str) -> str:
        if re.fullmatch(r"\d{4}-(0[1-9]|1[0-2])", value) is None:
            raise ValueError("Must use YYYY-MM format with a valid month.")
        try:
            month_sequence(value, value)
        except ValueError as exception:
            raise ValueError("Must use YYYY-MM format with a valid month.") from exception
        return value


class PredictionRequest(BaseModel):
    class Config:
        extra = "forbid"

    item_id: int = Field(gt=0)
    item_name: str = Field(min_length=1, max_length=255)
    forecast_months: int = Field(ge=1, le=12)
    historical_data: list[HistoricalMonth] = Field(min_items=1, max_items=120)

    @validator("item_name")
    def validate_item_name(cls, value: str) -> str:
        if not value.strip():
            raise ValueError("Must not be blank.")
        return value


def error_response(
    status_code: int,
    code: str,
    message: str,
    details: list[dict[str, str]] | None = None,
) -> JSONResponse:
    return JSONResponse(
        status_code=status_code,
        content={
            "success": False,
            "error": {"code": code, "message": message, "details": details or []},
        },
    )


@app.exception_handler(RequestValidationError)
async def validation_error_handler(request: Request, exception: RequestValidationError) -> JSONResponse:
    details = []
    history_invalid = False
    for error in exception.errors():
        location = error.get("loc", ())
        if request.url.path == "/predict" and "historical_data" in location:
            history_invalid = True
        field = ".".join(str(part) for part in location if part != "body") or "request"
        details.append({"field": field, "message": error["msg"]})

    if history_invalid:
        return error_response(422, "INVALID_HISTORY", "Historical records are invalid.", details)
    return error_response(422, "VALIDATION_ERROR", "The request contains invalid input.", details)


@app.exception_handler(HTTPException)
async def http_error_handler(request: Request, exception: HTTPException) -> JSONResponse:
    if request.url.path != "/predict":
        return JSONResponse(
            status_code=exception.status_code,
            content={"detail": exception.detail},
            headers=exception.headers,
        )

    if exception.status_code == 401:
        return error_response(401, "UNAUTHORIZED", "Missing or invalid API credentials.")
    if exception.status_code == 503:
        return error_response(503, "MODEL_UNAVAILABLE", "The forecasting service is unavailable.")
    return error_response(500, "PREDICTION_FAILED", "The forecast could not be generated.")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/predict")
def predict(
    request: PredictionRequest,
    authorization: Annotated[str | None, Header()] = None,
) -> dict[str, Any] | JSONResponse:
    expected_token = os.environ.get("ML_API_TOKEN", "")
    if not expected_token:
        raise HTTPException(status_code=503, detail="Forecast API authentication is not configured")

    scheme, separator, supplied_token = (authorization or "").partition(" ")
    if (
        not separator
        or scheme.casefold() != "bearer"
        or not secrets.compare_digest(supplied_token.encode(), expected_token.encode())
    ):
        raise HTTPException(
            status_code=401,
            detail="Invalid or missing bearer token",
            headers={"WWW-Authenticate": "Bearer"},
        )

    history = sorted(request.historical_data, key=lambda record: record.month)
    months = [record.month for record in history]
    if len(set(months)) != len(months):
        return error_response(
            422,
            "INVALID_HISTORY",
            "Historical records are invalid.",
            [{"field": "historical_data", "message": "Months must be unique."}],
        )
    if len(months) < int(os.environ.get("FORECAST_MINIMUM_HISTORY", "3")):
        return error_response(
            422,
            "INVALID_HISTORY",
            "The model requires at least three observed monthly records.",
            [{"field": "historical_data", "message": "Provide at least three distinct observed months."}],
        )

    monthly_usage = {record.month: record.issued_quantity for record in history}
    try:
        predictions = predict_monthly_demand(monthly_usage, request.forecast_months)
        if len(predictions) != request.forecast_months or any(
            not math.isfinite(float(prediction["predicted_demand"]))
            or prediction["predicted_demand"] < 0
            for prediction in predictions
        ):
            raise ValueError("Model returned invalid predictions")
    except Exception:
        logger.exception("Demand forecast prediction failed.")
        return error_response(500, "PREDICTION_FAILED", "The forecast could not be generated.")

    return {
        "success": True,
        "item_id": request.item_id,
        "item_name": request.item_name,
        "forecast_period": {
            "start_month": predictions[0]["month"],
            "months": request.forecast_months,
        },
        "predictions": predictions,
        "model_info": {
            "model_name": "demand-linear-regression",
            "model_version": MODEL_VERSION,
        },
    }


@app.post("/forecast")
def forecast(
    file: Annotated[UploadFile, File()],
    minimum_history: Annotated[int, Form(ge=1)] = 3,
    authorization: Annotated[str | None, Header()] = None,
) -> dict[str, Any]:
    expected_token = os.environ.get("ML_API_TOKEN", "")
    if not expected_token:
        raise HTTPException(status_code=503, detail="Forecast API authentication is not configured")

    scheme, separator, supplied_token = (authorization or "").partition(" ")
    if (
        not separator
        or scheme.casefold() != "bearer"
        or not secrets.compare_digest(supplied_token.encode(), expected_token.encode())
    ):
        raise HTTPException(
            status_code=401,
            detail="Invalid or missing bearer token",
            headers={"WWW-Authenticate": "Bearer"},
        )

    try:
        with tempfile.TemporaryDirectory(prefix="dnhs-forecast-") as directory:
            csv_path = Path(directory) / "input.csv"
            with csv_path.open("wb") as destination:
                shutil.copyfileobj(file.file, destination)

            items = parse_export(csv_path)
            result, _bundle = generate_forecast(items, minimum_history)
    except ValueError as exception:
        raise HTTPException(status_code=422, detail=str(exception)) from exception

    return result
