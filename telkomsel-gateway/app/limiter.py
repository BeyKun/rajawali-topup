from __future__ import annotations

import asyncio
import time
from collections import deque
from collections.abc import Awaitable, Callable


class SlidingWindowRateLimiter:
    """Per-key sliding-window limiter.

    ``acquire`` blocks until a slot is free, so a burst of queued Laravel jobs
    is serialized into a steady 3-requests-per-minute stream per egress IP.
    Clock and sleep are injectable to keep tests fast and deterministic.
    """

    def __init__(
        self,
        limit: int,
        window: float,
        *,
        clock: Callable[[], float] = time.monotonic,
        sleep: Callable[[float], Awaitable[None]] = asyncio.sleep,
    ) -> None:
        if limit < 1:
            raise ValueError("limit must be at least 1")

        self._limit = limit
        self._window = window
        self._clock = clock
        self._sleep = sleep
        self._events: dict[str, deque[float]] = {}
        self._locks: dict[str, asyncio.Lock] = {}

    async def acquire(self, key: str) -> float:
        """Reserve one slot for ``key`` and return the seconds spent waiting."""

        lock = self._locks.setdefault(key, asyncio.Lock())

        async with lock:
            waited = 0.0

            while True:
                now = self._clock()
                events = self._events.setdefault(key, deque())

                while events and now - events[0] >= self._window:
                    events.popleft()

                if len(events) < self._limit:
                    events.append(now)

                    return waited

                delay = self._window - (now - events[0])

                if delay <= 0:
                    continue

                await self._sleep(delay)
                waited += delay

    def pending(self, key: str) -> int:
        now = self._clock()
        events = self._events.get(key)

        if not events:
            return 0

        while events and now - events[0] >= self._window:
            events.popleft()

        return len(events)
