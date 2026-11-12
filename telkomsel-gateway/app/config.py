from __future__ import annotations

from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Runtime configuration for the Telkomsel gateway.

    Every field maps to a ``TELKOMSEL_*`` environment variable so the service
    can share the Laravel ``.env`` file inside docker-compose.
    """

    model_config = SettingsConfigDict(
        env_prefix="TELKOMSEL_",
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )

    # Upstream API.
    base_url: str = "https://www.telkomsel.com/api/voucher"
    timeout_seconds: float = 45.0

    # Captcha configuration (remote endpoint decides google/geetest/no-captcha).
    captcha_config_url: str = "https://www.telkomsel.com/api/promo/file/captcha.json"
    captcha_config_ttl: int = 300
    captcha_hint: str = "google"
    captcha_page_url: str = "https://www.telkomsel.com/shops/voucher/check"
    captcha_sitekey: str = "6LeqRk4cAAAAABZ4SsS_WxYTxazfNfcb0fzY5rc2"
    geetest_captcha_id: str = "9aeabc156860da6bff0a732724a5779e"

    # Rate limiting. Three successful hits per rolling window, per egress IP.
    rpm: int = 3
    window_seconds: float = 60.0
    max_attempts: int = 3
    rate_limit_backoff_seconds: float = 62.0
    rate_limit_retries: int = 1
    captcha_retry_delay_seconds: float = 1.0

    # Captcha solver (none | 2captcha | capsolver).
    solver_provider: str = "none"
    solver_api_key: str = ""
    solver_poll_interval: float = 5.0
    solver_timeout_seconds: float = 120.0
    solver_proxy: str = ""

    # Optional residential/mobile proxy pool: "http://user:pass@host:port,...".
    proxies: str = ""

    # Shared secret expected in the X-Gateway-Token header.
    gateway_token: str = ""

    # Observability.
    log_level: str = "INFO"

    @property
    def proxy_list(self) -> list[str]:
        return [item.strip() for item in self.proxies.split(",") if item.strip()]


@lru_cache
def get_settings() -> Settings:
    return Settings()
