from __future__ import annotations

import asyncio
import json
import logging
import time
from typing import Any, Protocol
from urllib.parse import urlsplit

import httpx

from .config import Settings
from .models import CaptchaConfig, CaptchaSolution
from .transport import Transport

logger = logging.getLogger(__name__)

VALID_MODES = {"google", "geetest", "no-captcha"}
TWO_CAPTCHA_IN_URL = "https://2captcha.com/in.php"
TWO_CAPTCHA_RES_URL = "https://2captcha.com/res.php"
CAPSOLVER_CREATE_URL = "https://api.capsolver.com/createTask"
CAPSOLVER_RESULT_URL = "https://api.capsolver.com/getTaskResult"


def normalize_mode(value: Any) -> str:
    """Map a remote captcha setting to one of the three supported modes."""

    if isinstance(value, str):
        mode = value.strip().lower()

        if mode in VALID_MODES:
            return mode

    return "google"


def build_captcha_fields(mode: str, solution: CaptchaSolution | None) -> dict[str, Any]:
    """Shape a solved challenge into Telkomsel's request payload fields."""

    if mode == "no-captcha" or solution is None:
        return {}

    if mode == "geetest":
        return {
            "captcha_type": "geetest",
            "lotnumber": solution.fields.get("lot_number", ""),
            "output": solution.fields.get("captcha_output", ""),
            "token": solution.fields.get("pass_token", ""),
            "gentime": solution.fields.get("gen_time", ""),
        }

    return {"recaptcharesponse": str(solution.fields.get("token", ""))}


class CaptchaConfigProvider:
    """Reads the remote captcha mode, cached for a short TTL."""

    def __init__(
        self,
        transport: Transport,
        url: str,
        ttl: int = 300,
        fallback: str = "google",
    ) -> None:
        self._transport = transport
        self._url = url
        self._ttl = ttl
        self._fallback = fallback
        self._cached: CaptchaConfig | None = None
        self._expires_at = 0.0
        self._lock = asyncio.Lock()

    async def get(self, *, force: bool = False) -> CaptchaConfig:
        if not force and self._cached is not None and time.monotonic() < self._expires_at:
            return self._cached

        async with self._lock:
            if not force and self._cached is not None and time.monotonic() < self._expires_at:
                return self._cached

            response = await self._transport.get(self._url)
            data = response.json()

            if isinstance(data, dict):
                mode = normalize_mode(data.get("captcha_voucher_fisik"))
                config = CaptchaConfig(mode=mode, raw=data)
            else:
                mode = normalize_mode(self._fallback)
                config = CaptchaConfig(mode=mode, raw={"error": response.error or response.body})

            self._cached = config
            self._expires_at = time.monotonic() + self._ttl
            logger.info("Telkomsel captcha mode resolved: %s", config.mode)

            return config


class Solver(Protocol):
    name: str

    async def solve(self, mode: str, *, proxy: str | None = None) -> CaptchaSolution: ...


class NoopSolver:
    name = "none"

    async def solve(self, mode: str, *, proxy: str | None = None) -> CaptchaSolution:
        raise RuntimeError(
            "Telkomsel requested a captcha but no solver is configured "
            "(set TELKOMSEL_SOLVER_PROVIDER=2captcha|capsolver)."
        )


def _split_proxy(proxy: str) -> dict[str, str]:
    parts = urlsplit(proxy if "://" in proxy else f"http://{proxy}")
    scheme = parts.scheme or "http"
    result = {
        "proxyType": "http" if scheme.startswith("http") else scheme,
        "proxyAddress": parts.hostname or "",
        "proxyPort": str(parts.port or ""),
    }

    if parts.username:
        result["proxyLogin"] = parts.username

    if parts.password:
        result["proxyPassword"] = parts.password

    return result


class TwoCaptchaSolver:
    name = "2captcha"

    def __init__(self, settings: Settings) -> None:
        if not settings.solver_api_key:
            raise RuntimeError("TELKOMSEL_SOLVER_API_KEY is required for 2captcha.")

        self._settings = settings

    async def _create(self, payload: dict[str, Any]) -> str:
        body = {**payload, "key": self._settings.solver_api_key, "json": 1}

        async with httpx.AsyncClient(timeout=30) as client:
            response = await client.post(TWO_CAPTCHA_IN_URL, data=body)

        data = response.json()

        if int(data.get("status", 0)) != 1:
            raise RuntimeError(f"2Captcha rejected the task: {data.get('request')}")

        return str(data["request"])

    async def _poll(self, task_id: str) -> str:
        deadline = time.monotonic() + self._settings.solver_timeout_seconds

        async with httpx.AsyncClient(timeout=30) as client:
            while time.monotonic() < deadline:
                await asyncio.sleep(self._settings.solver_poll_interval)
                response = await client.get(
                    TWO_CAPTCHA_RES_URL,
                    params={
                        "key": self._settings.solver_api_key,
                        "action": "get",
                        "id": task_id,
                        "json": 1,
                    },
                )
                data = response.json()

                if int(data.get("status", 0)) == 1:
                    return str(data["request"])

                if data.get("request") not in {"CAPCHA_NOT_READY", "CAPTCHA_NOT_READY"}:
                    raise RuntimeError(f"2Captcha failed: {data.get('request')}")

        raise TimeoutError("2Captcha did not return a token in time.")

    async def solve(self, mode: str, *, proxy: str | None = None) -> CaptchaSolution:
        if mode == "geetest":
            payload: dict[str, Any] = {
                "method": "geetest",
                "captcha_id": self._settings.geetest_captcha_id,
                "pageurl": self._settings.captcha_page_url,
                "version": 4,
            }
        else:
            payload = {
                "method": "userrecaptcha",
                "googlekey": self._settings.captcha_sitekey,
                "pageurl": self._settings.captcha_page_url,
                "invisible": 1,
            }

            solve_proxy = self._settings.solver_proxy or proxy
            if solve_proxy:
                payload["proxy"] = solve_proxy
                payload["proxytype"] = "HTTP"

        token = await self._poll(await self._create(payload))

        if mode == "geetest":
            try:
                fields = json.loads(token)
            except (TypeError, ValueError):
                fields = {}

            if not isinstance(fields, dict):
                fields = {}

            return CaptchaSolution(fields=fields, mode=mode, solver=self.name)

        return CaptchaSolution(fields={"token": token}, mode=mode, solver=self.name)


class CapSolverSolver:
    name = "capsolver"

    def __init__(self, settings: Settings) -> None:
        if not settings.solver_api_key:
            raise RuntimeError("TELKOMSEL_SOLVER_API_KEY is required for capsolver.")

        self._settings = settings

    async def _create(self, task: dict[str, Any]) -> str:
        async with httpx.AsyncClient(timeout=30) as client:
            response = await client.post(
                CAPSOLVER_CREATE_URL,
                json={"clientKey": self._settings.solver_api_key, "task": task},
            )

        data = response.json()

        if data.get("errorId"):
            raise RuntimeError(f"CapSolver rejected the task: {data.get('errorDescription')}")

        return str(data["taskId"])

    async def _poll(self, task_id: str) -> dict[str, Any]:
        deadline = time.monotonic() + self._settings.solver_timeout_seconds

        async with httpx.AsyncClient(timeout=30) as client:
            while time.monotonic() < deadline:
                await asyncio.sleep(self._settings.solver_poll_interval)
                response = await client.post(
                    CAPSOLVER_RESULT_URL,
                    json={"clientKey": self._settings.solver_api_key, "taskId": task_id},
                )
                data = response.json()

                if data.get("status") == "ready":
                    return data.get("solution") or {}

                if data.get("errorId"):
                    raise RuntimeError(f"CapSolver failed: {data.get('errorDescription')}")

        raise TimeoutError("CapSolver did not return a token in time.")

    async def solve(self, mode: str, *, proxy: str | None = None) -> CaptchaSolution:
        solve_proxy = self._settings.solver_proxy or proxy
        proxy_fields = _split_proxy(solve_proxy) if solve_proxy else {}

        if mode == "geetest":
            task: dict[str, Any] = {
                "type": "GeeTestTask" if proxy_fields else "GeeTestTaskProxyless",
                "websiteURL": self._settings.captcha_page_url,
                "gt": self._settings.geetest_captcha_id,
                "version": 4,
                **proxy_fields,
            }
        else:
            task = {
                "type": "ReCaptchaV2Task" if proxy_fields else "ReCaptchaV2TaskProxyLess",
                "websiteURL": self._settings.captcha_page_url,
                "websiteKey": self._settings.captcha_sitekey,
                "isInvisible": True,
                **proxy_fields,
            }

        solution = await self._poll(await self._create(task))

        if mode == "geetest":
            return CaptchaSolution(fields=solution, mode=mode, solver=self.name)

        token = solution.get("gRecaptchaResponse") or solution.get("token") or ""

        return CaptchaSolution(fields={"token": str(token)}, mode=mode, solver=self.name)


def build_solver(settings: Settings) -> Solver:
    provider = settings.solver_provider.strip().lower()

    if provider == "2captcha":
        return TwoCaptchaSolver(settings)

    if provider == "capsolver":
        return CapSolverSolver(settings)

    return NoopSolver()
