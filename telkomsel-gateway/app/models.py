from __future__ import annotations

import json
from dataclasses import dataclass, field
from typing import Any


@dataclass(slots=True)
class UpstreamResponse:
    """A normalized HTTP response from Telkomsel (or a transport failure)."""

    status: int
    body: str = ""
    headers: dict[str, str] = field(default_factory=dict)
    error: str = ""

    @property
    def ok(self) -> bool:
        return self.status > 0 and 200 <= self.status < 300

    @property
    def reachable(self) -> bool:
        return self.status > 0

    def json(self) -> dict[str, Any] | None:
        if not self.body:
            return None

        try:
            decoded = json.loads(self.body)
        except (TypeError, ValueError):
            return None

        return decoded if isinstance(decoded, dict) else None


@dataclass(slots=True)
class CaptchaSolution:
    """A solved challenge, already shaped as Telkomsel payload fields."""

    fields: dict[str, Any]
    mode: str
    solver: str = "none"


@dataclass(slots=True)
class CaptchaConfig:
    mode: str
    raw: dict[str, Any] = field(default_factory=dict)
