from __future__ import annotations

import logging
from contextlib import asynccontextmanager
from typing import Any, AsyncIterator

from fastapi import Depends, FastAPI, Header, HTTPException, Request
from pydantic import BaseModel, Field

from app.client import TelkomselGateway
from app.config import Settings, get_settings

logger = logging.getLogger(__name__)


@asynccontextmanager
async def lifespan(application: FastAPI) -> AsyncIterator[None]:
    settings = get_settings()
    logging.basicConfig(
        level=getattr(logging, settings.log_level.upper(), logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s %(message)s",
    )
    application.state.settings = settings
    application.state.gateway = TelkomselGateway(settings)
    logger.info(
        "Telkomsel gateway started (rpm=%s solver=%s proxies=%s)",
        settings.rpm,
        settings.solver_provider,
        len(settings.proxy_list),
    )

    try:
        yield
    finally:
        await application.state.gateway.aclose()


app = FastAPI(title="Rajawali Telkomsel Gateway", version="1.0.0", lifespan=lifespan)


class CheckRequest(BaseModel):
    serial_number: str = Field(min_length=1)


class RedeemRequest(BaseModel):
    hrn: str = Field(min_length=1)
    msisdn: str = Field(min_length=1)


def gateway(request: Request) -> TelkomselGateway:
    return request.app.state.gateway


def settings(request: Request) -> Settings:
    return request.app.state.settings


def require_token(
    request: Request,
    x_gateway_token: str | None = Header(default=None),
) -> None:
    expected = settings(request).gateway_token

    if expected and x_gateway_token != expected:
        raise HTTPException(status_code=401, detail="Invalid gateway token")


@app.get("/health")
def health(request: Request) -> dict[str, Any]:
    current = settings(request)

    return {
        "status": "ok",
        "service": "rajawali-telkomsel-gateway",
        "rpm": current.rpm,
        "solver": current.solver_provider,
        "proxies": len(current.proxy_list),
    }


@app.post("/check", dependencies=[Depends(require_token)])
async def check(request: CheckRequest, client: TelkomselGateway = Depends(gateway)) -> dict[str, Any]:
    return await client.check(request.serial_number)


@app.post("/redeem", dependencies=[Depends(require_token)])
async def redeem(
    request: RedeemRequest,
    client: TelkomselGateway = Depends(gateway),
) -> dict[str, Any]:
    return await client.redeem(request.hrn, request.msisdn)
