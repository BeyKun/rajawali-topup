from __future__ import annotations

import json
from typing import Any

from app.client import TelkomselGateway
from app.config import Settings
from app.models import CaptchaConfig, CaptchaSolution, UpstreamResponse


class ScriptedTransport:
    def __init__(self, responses: list[UpstreamResponse]) -> None:
        self._responses = list(responses)
        self.calls: list[tuple[str, dict[str, Any], str | None]] = []

    async def post_json(
        self,
        url: str,
        payload: dict[str, Any],
        *,
        proxy: str | None = None,
    ) -> UpstreamResponse:
        self.calls.append((url, payload, proxy))

        return self._responses.pop(0)

    async def get(self, url: str, *, proxy: str | None = None) -> UpstreamResponse:
        return UpstreamResponse(status=500, body="{}")

    async def aclose(self) -> None:
        return None


class StaticConfig:
    def __init__(self, mode: str) -> None:
        self.mode = mode
        self.calls = 0

    async def get(self, *, force: bool = False) -> CaptchaConfig:
        self.calls += 1

        return CaptchaConfig(mode=self.mode)


class RecordingSolver:
    name = "recording"

    def __init__(self) -> None:
        self.calls: list[tuple[str, str | None]] = []

    async def solve(self, mode: str, *, proxy: str | None = None) -> CaptchaSolution:
        self.calls.append((mode, proxy))

        return CaptchaSolution(fields={"token": f"token-{len(self.calls)}"}, mode=mode, solver=self.name)


class NoopLimiter:
    async def acquire(self, key: str) -> float:
        return 0.0


def body(payload: dict[str, Any]) -> str:
    return json.dumps(payload)


def make_gateway(
    responses: list[UpstreamResponse],
    *,
    mode: str = "google",
    max_attempts: int = 3,
) -> tuple[TelkomselGateway, ScriptedTransport, StaticConfig, RecordingSolver, list[float]]:
    transport = ScriptedTransport(responses)
    config = StaticConfig(mode)
    solver = RecordingSolver()
    sleeps: list[float] = []

    async def sleep(seconds: float) -> None:
        sleeps.append(seconds)

    gateway = TelkomselGateway(
        Settings(
            base_url="https://example.test/api/voucher",
            rpm=3,
            window_seconds=60,
            max_attempts=max_attempts,
            rate_limit_retries=1,
            rate_limit_backoff_seconds=62,
            captcha_retry_delay_seconds=0,
            solver_provider="none",
        ),
        transport=transport,
        solver=solver,
        limiter=NoopLimiter(),
        captcha_config=config,
        sleep=sleep,
    )

    return gateway, transport, config, solver, sleeps


async def test_check_succeeds_without_captcha() -> None:
    gateway, transport, _, solver, _ = make_gateway(
        [UpstreamResponse(status=200, body=body({"statusCode": 1, "statusMessage": "Tersedia"}))]
    )

    result = await gateway.check("3003 3812 0354")

    assert result["success"] is True
    assert result["upstream_status"] == 200
    assert result["payload"]["statusCode"] == 1
    assert solver.calls == []
    assert transport.calls[0][1]["serialNumber"] == "300338120354"
    assert transport.calls[0][1]["recaptcharesponse"] == ""


async def test_check_solves_captcha_only_after_428() -> None:
    gateway, transport, config, solver, _ = make_gateway(
        [
            UpstreamResponse(status=428, body=body({"error": {"code": "CAPTCHA_REQUIRED"}})),
            UpstreamResponse(status=200, body=body({"statusCode": 1})),
        ]
    )

    result = await gateway.check("300338120354")

    assert result["success"] is True
    assert result["meta"]["captcha"] == "google"
    assert result["meta"]["attempts"] == 2
    assert solver.calls == [("google", None)]
    assert transport.calls[1][1]["recaptcharesponse"] == "token-1"
    assert config.calls == 1


async def test_invalid_captcha_token_is_resolved_again() -> None:
    gateway, transport, config, solver, _ = make_gateway(
        [
            UpstreamResponse(status=428, body=body({"error": {"code": "CAPTCHA_REQUIRED"}})),
            UpstreamResponse(status=400, body=body({"error": {"code": "CAPTCHA_INVALID"}})),
            UpstreamResponse(status=200, body=body({"statusCode": 1})),
        ]
    )

    result = await gateway.check("300338120354")

    assert result["success"] is True
    assert len(solver.calls) == 2
    assert transport.calls[2][1]["recaptcharesponse"] == "token-2"
    assert config.calls == 2


async def test_rate_limit_waits_before_retrying() -> None:
    gateway, _, _, _, sleeps = make_gateway(
        [
            UpstreamResponse(status=429, body=body({"message": "Too Many Requests"})),
            UpstreamResponse(status=200, body=body({"statusCode": 1})),
        ]
    )

    result = await gateway.check("300338120354")

    assert result["success"] is True
    assert sleeps == [62]
    assert result["upstream_status"] == 200


async def test_rate_limit_is_returned_after_retries_are_exhausted() -> None:
    gateway, _, _, _, _ = make_gateway(
        [
            UpstreamResponse(status=429, body=body({"message": "Too Many Requests"})),
            UpstreamResponse(status=429, body=body({"message": "Too Many Requests"})),
        ],
        max_attempts=2,
    )

    result = await gateway.check("300338120354")

    assert result["success"] is True
    assert result["upstream_status"] == 429


async def test_transport_failure_is_reported_without_raising() -> None:
    gateway, _, _, _, _ = make_gateway([UpstreamResponse(status=0, error="timeout")])

    result = await gateway.check("300338120354")

    assert result["success"] is False
    assert result["upstream_status"] == 0
    assert result["payload"]["error"] == "Connection error"


async def test_redeem_formats_msisdn_and_marks_no_captcha() -> None:
    gateway, transport, _, _, _ = make_gateway(
        [UpstreamResponse(status=200, body=body({"status": True, "message": 200, "data": {"code": "00"}}))]
    )

    await gateway.redeem("71125613431848001", "0822-3345-6777")

    payload = transport.calls[0][1]
    assert payload["msisdn"] == "6282233456777"
    assert payload["no-captcha"] is True
    assert payload["voucher_type"] == "voucher"
