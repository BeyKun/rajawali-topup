from __future__ import annotations

import asyncio

from app.limiter import SlidingWindowRateLimiter


class FakeClock:
    def __init__(self) -> None:
        self.now = 0.0
        self.sleeps: list[float] = []

    def monotonic(self) -> float:
        return self.now

    async def sleep(self, seconds: float) -> None:
        self.sleeps.append(seconds)
        self.now += seconds


async def test_fourth_request_waits_for_the_window() -> None:
    clock = FakeClock()
    limiter = SlidingWindowRateLimiter(
        3,
        60,
        clock=clock.monotonic,
        sleep=clock.sleep,
    )

    for _ in range(3):
        await limiter.acquire("direct")

    waited = await limiter.acquire("direct")

    assert waited == 60
    assert clock.sleeps == [60]


async def test_concurrent_requests_never_exceed_three_per_window() -> None:
    clock = FakeClock()
    limiter = SlidingWindowRateLimiter(
        3,
        60,
        clock=clock.monotonic,
        sleep=clock.sleep,
    )

    completions: list[float] = []

    async def call() -> None:
        await limiter.acquire("direct")
        completions.append(clock.now)

    await asyncio.gather(*[call() for _ in range(9)])

    completions.sort()

    assert completions[:3] == [0, 0, 0]
    # No 60-second window may contain more than three acquisitions.
    for index in range(3, len(completions)):
        assert completions[index] - completions[index - 3] >= 60


async def test_keys_have_independent_budgets() -> None:
    clock = FakeClock()
    limiter = SlidingWindowRateLimiter(
        1,
        60,
        clock=clock.monotonic,
        sleep=clock.sleep,
    )

    assert await limiter.acquire("proxy-a") == 0
    assert await limiter.acquire("proxy-b") == 0
    assert await limiter.acquire("proxy-a") == 60
