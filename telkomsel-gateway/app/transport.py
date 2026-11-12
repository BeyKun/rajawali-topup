from __future__ import annotations

import asyncio
import logging
from typing import Any, Protocol

from curl_cffi.requests import AsyncSession

from .models import UpstreamResponse

logger = logging.getLogger(__name__)

CHROME_HEADERS = {
    "Accept": "application/json, text/plain, */*",
    "Accept-Language": "id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7",
    "Origin": "https://www.telkomsel.com",
    "Referer": "https://www.telkomsel.com/shops/voucher/check",
    "sec-ch-ua": '"Chromium";v="131", "Not_A Brand";v="24", "Google Chrome";v="131"',
    "sec-ch-ua-mobile": "?0",
    "sec-ch-ua-platform": '"Windows"',
    "sec-fetch-dest": "empty",
    "sec-fetch-mode": "cors",
    "sec-fetch-site": "same-origin",
}


class Transport(Protocol):
    async def post_json(
        self,
        url: str,
        payload: dict[str, Any],
        *,
        proxy: str | None = None,
    ) -> UpstreamResponse: ...

    async def get(self, url: str, *, proxy: str | None = None) -> UpstreamResponse: ...

    async def aclose(self) -> None: ...


class CurlTransport:
    """HTTP transport that mimics a real Chrome TLS/JA3 fingerprint.

    One ``AsyncSession`` is kept per proxy so cookies and TLS state stay warm
    between calls, which raises the anti-abuse trust score.
    """

    def __init__(self, timeout: float = 45.0, impersonate: str = "chrome") -> None:
        self._timeout = timeout
        self._impersonate = impersonate
        self._sessions: dict[str, AsyncSession] = {}
        self._lock = asyncio.Lock()

    async def _session(self, proxy: str | None) -> AsyncSession:
        key = proxy or "direct"
        session = self._sessions.get(key)

        if session is not None:
            return session

        async with self._lock:
            session = self._sessions.get(key)

            if session is None:
                session = AsyncSession(
                    impersonate=self._impersonate,
                    headers=CHROME_HEADERS,
                    timeout=self._timeout,
                )
                self._sessions[key] = session

        return session

    async def post_json(
        self,
        url: str,
        payload: dict[str, Any],
        *,
        proxy: str | None = None,
    ) -> UpstreamResponse:
        session = await self._session(proxy)

        try:
            response = await session.post(
                url,
                json=payload,
                proxy=proxy,
                timeout=self._timeout,
            )
        except Exception as exception:  # noqa: BLE001 - normalize every transport fault
            logger.warning("Telkomsel request failed: %s", exception)

            return UpstreamResponse(status=0, error=str(exception))

        return UpstreamResponse(
            status=int(response.status_code),
            body=response.text or "",
            headers={str(key).lower(): str(value) for key, value in response.headers.items()},
        )

    async def get(self, url: str, *, proxy: str | None = None) -> UpstreamResponse:
        session = await self._session(proxy)

        try:
            response = await session.get(url, proxy=proxy, timeout=self._timeout)
        except Exception as exception:  # noqa: BLE001
            logger.warning("Telkomsel GET failed: %s", exception)

            return UpstreamResponse(status=0, error=str(exception))

        return UpstreamResponse(
            status=int(response.status_code),
            body=response.text or "",
            headers={str(key).lower(): str(value) for key, value in response.headers.items()},
        )

    async def aclose(self) -> None:
        for session in self._sessions.values():
            await session.close()

        self._sessions.clear()
