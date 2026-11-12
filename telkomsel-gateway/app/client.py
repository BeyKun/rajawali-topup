from __future__ import annotations

import asyncio
import logging
import re
import time
from typing import Any
from urllib.parse import urlsplit, urlunsplit

from .captcha import (
    CaptchaConfigProvider,
    Solver,
    build_captcha_fields,
    build_solver,
)
from .config import Settings
from .limiter import SlidingWindowRateLimiter
from .models import UpstreamResponse
from .proxies import ProxyPool
from .transport import CurlTransport, Transport

logger = logging.getLogger(__name__)


class TelkomselGateway:
    """Serialized, captcha-aware client for the Telkomsel voucher API."""

    def __init__(
        self,
        settings: Settings,
        *,
        transport: Transport | None = None,
        solver: Solver | None = None,
        limiter: SlidingWindowRateLimiter | None = None,
        proxy_pool: ProxyPool | None = None,
        captcha_config: CaptchaConfigProvider | None = None,
        sleep=asyncio.sleep,
    ) -> None:
        self._settings = settings
        self._transport = transport or CurlTransport(timeout=settings.timeout_seconds)
        self._solver = solver or build_solver(settings)
        self._limiter = limiter or SlidingWindowRateLimiter(
            limit=settings.rpm,
            window=settings.window_seconds,
        )
        self._proxies = proxy_pool or ProxyPool(settings.proxy_list)
        self._captcha_config = captcha_config or CaptchaConfigProvider(
            self._transport,
            settings.captcha_config_url,
            ttl=settings.captcha_config_ttl,
            fallback=settings.captcha_hint,
        )
        self._sleep = sleep
        self._egress_cache: dict[str, str] = {}

    async def check(self, serial_number: str) -> dict[str, Any]:
        serial = self._sanitize_serial(serial_number)
        payload: dict[str, Any] = {
            "recaptcharesponse": "",
            "serialNumber": serial,
            "voucher_type": "voucher",
        }

        return await self._send("/check", payload)

    async def redeem(self, hrn: str, msisdn: str) -> dict[str, Any]:
        payload: dict[str, Any] = {
            "recaptcharesponse": "",
            "hrn": hrn.strip(),
            "msisdn": self._format_msisdn(msisdn),
            "no-captcha": True,
            "tag": "",
            "voucher_type": "voucher",
        }

        return await self._send("/redeem", payload)

    async def _send(self, endpoint: str, base_payload: dict[str, Any]) -> dict[str, Any]:
        started = time.monotonic()
        attempts = 0
        rate_retries = 0
        captcha_mode: str | None = None
        captcha_solver: str | None = None
        captcha_fields: dict[str, Any] = {}
        response: UpstreamResponse | None = None
        data: dict[str, Any] | None = None
        proxy: str | None = None
        waited_ms = 0

        while attempts < max(1, self._settings.max_attempts):
            attempts += 1
            proxy = await self._proxies.next()
            waited = await self._limiter.acquire(ProxyPool.key(proxy))
            waited_ms += int(round(waited * 1000))

            payload = {**base_payload, **captcha_fields}
            response = await self._transport.post_json(
                self._settings.base_url.rstrip("/") + endpoint,
                payload,
                proxy=proxy,
            )
            data = response.json()

            if not response.reachable:
                logger.warning("Telkomsel %s transport failure: %s", endpoint, response.error)

                return self._envelope(
                    success=False,
                    response=response,
                    payload={
                        "error": "Connection error",
                        "message": response.error or "Upstream unreachable",
                    },
                    meta=self._meta(
                        started,
                        attempts,
                        captcha_mode,
                        captcha_solver,
                        proxy,
                        waited_ms,
                    ),
                )

            if self._is_rate_limited(response, data):
                logger.warning("Telkomsel %s rate limited (HTTP %s)", endpoint, response.status)

                if rate_retries < self._settings.rate_limit_retries:
                    rate_retries += 1
                    await self._sleep(self._retry_after(response))

                    continue

                break

            captcha_code = self._captcha_code(response, data)

            if captcha_code is not None:
                logger.info("Telkomsel %s requested a captcha (%s)", endpoint, captcha_code)

                config = await self._captcha_config.get(force=captcha_code == "CAPTCHA_INVALID")
                captcha_mode = config.mode

                if captcha_mode == "no-captcha":
                    captcha_fields = {}

                    continue

                try:
                    solution = await self._solver.solve(captcha_mode, proxy=proxy)
                except Exception as exception:  # noqa: BLE001 - surface solver faults as data
                    logger.error("Captcha solver failed: %s", exception)

                    return self._envelope(
                        success=False,
                        response=response,
                        payload={
                            "error": {
                                "code": captcha_code,
                                "message": str(exception),
                            },
                        },
                        meta=self._meta(
                            started,
                            attempts,
                            captcha_mode,
                            captcha_solver,
                            proxy,
                            waited_ms,
                        ),
                    )

                captcha_solver = solution.solver
                captcha_fields = build_captcha_fields(captcha_mode, solution)
                await self._sleep(self._settings.captcha_retry_delay_seconds)

                continue

            logger.info(
                "Telkomsel %s completed with HTTP %s after %s attempt(s)",
                endpoint,
                response.status,
                attempts,
            )

            return self._envelope(
                success=True,
                response=response,
                payload=data if data is not None else {"raw": response.body},
                meta=self._meta(started, attempts, captcha_mode, captcha_solver, proxy, waited_ms),
            )

        return self._envelope(
            success=True,
            response=response,
            payload=(data if data is not None else {"raw": response.body if response else ""}),
            meta=self._meta(
                started,
                attempts,
                captcha_mode,
                captcha_solver,
                proxy,
                waited_ms,
            ),
        )

    def _envelope(
        self,
        *,
        success: bool,
        response: UpstreamResponse | None,
        payload: dict[str, Any],
        meta: dict[str, Any],
    ) -> dict[str, Any]:
        return {
            "success": success,
            "upstream_status": response.status if response else 0,
            "payload": payload,
            "meta": meta,
        }

    def _meta(
        self,
        started: float,
        attempts: int,
        captcha_mode: str | None,
        captcha_solver: str | None,
        proxy: str | None,
        waited_ms: int,
    ) -> dict[str, Any]:
        return {
            "duration_ms": int(round((time.monotonic() - started) * 1000)),
            "attempts": attempts,
            "captcha": captcha_mode or "none",
            "solver": captcha_solver or "none",
            "proxy": self._mask_proxy(proxy),
            "waited_ms": waited_ms,
        }

    def _retry_after(self, response: UpstreamResponse) -> float:
        header = response.headers.get("retry-after")

        if header:
            try:
                return max(1.0, float(header))
            except (TypeError, ValueError):
                pass

        return self._settings.rate_limit_backoff_seconds

    @staticmethod
    def _is_rate_limited(response: UpstreamResponse, data: dict[str, Any] | None) -> bool:
        if response.status == 429:
            return True

        if isinstance(data, dict) and int(data.get("statusCode") or 0) == 429:
            return True

        message = ""

        if isinstance(data, dict):
            message = str(
                data.get("statusMessage") or data.get("message") or data.get("error") or ""
            )

        lowered = message.lower()

        return any(
            keyword in lowered
            for keyword in (
                "rate limit",
                "too many request",
                "coba lagi",
                "try again",
                "terlalu banyak",
                "request limit",
            )
        )

    @staticmethod
    def _captcha_code(
        response: UpstreamResponse,
        data: dict[str, Any] | None,
    ) -> str | None:
        code = None

        if isinstance(data, dict):
            error = data.get("error")

            if isinstance(error, dict) and error.get("code"):
                code = str(error["code"]).upper()

        if code in {"CAPTCHA_REQUIRED", "CAPTCHA_INVALID"}:
            return code

        if response.status == 428:
            return "CAPTCHA_REQUIRED"

        return None

    @staticmethod
    def _sanitize_serial(serial_number: str) -> str:
        digits = re.sub(r"\D", "", serial_number.strip())

        return digits[:12] if digits else serial_number.strip()

    @staticmethod
    def _format_msisdn(phone: str) -> str:
        digits = re.sub(r"[^0-9]", "", phone)

        if digits.startswith("08"):
            return "62" + digits[1:]

        if digits.startswith("8"):
            return "62" + digits

        return digits

    @staticmethod
    def _mask_proxy(proxy: str | None) -> str:
        if not proxy:
            return "direct"

        parts = urlsplit(proxy if "://" in proxy else f"http://{proxy}")
        host = parts.hostname or "unknown"
        port = f":{parts.port}" if parts.port else ""

        return urlunsplit((parts.scheme or "http", f"***@{host}{port}", "", "", ""))

    async def aclose(self) -> None:
        await self._transport.aclose()
