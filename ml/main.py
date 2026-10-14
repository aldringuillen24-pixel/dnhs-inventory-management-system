"""HTTP API for production demand forecasting."""

from __future__ import annotations

import os
import secrets
import shutil
import tempfile
from pathlib import Path
from typing import Annotated, Any

from fastapi import FastAPI, File, Form, Header, HTTPException, UploadFile

from forecast_demand import generate_forecast, parse_export

app = FastAPI(title="DNHS Demand Forecast API")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


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
