from __future__ import annotations

import asyncio


class ProxyPool:
    """Round-robin proxy rotation.

    Telkomsel throttles per egress IP, so rotation is the only way to raise
    throughput above three requests per minute. An empty pool means "direct".
    """

    def __init__(self, proxies: list[str]) -> None:
        self._proxies = [proxy for proxy in proxies if proxy]
        self._cursor = 0
        self._lock = asyncio.Lock()

    def __bool__(self) -> bool:
        return bool(self._proxies)

    @property
    def size(self) -> int:
        return len(self._proxies)

    async def next(self) -> str | None:
        if not self._proxies:
            return None

        async with self._lock:
            proxy = self._proxies[self._cursor % len(self._proxies)]
            self._cursor += 1

        return proxy

    @staticmethod
    def key(proxy: str | None) -> str:
        return proxy or "direct"
