from __future__ import annotations

from app.captcha import (
    NoopSolver,
    build_captcha_fields,
    build_solver,
    normalize_mode,
)
from app.config import Settings
from app.models import CaptchaSolution


def test_normalize_mode_maps_unknown_values_to_google() -> None:
    assert normalize_mode("google") == "google"
    assert normalize_mode(" Geetest ") == "geetest"
    assert normalize_mode("no-captcha") == "no-captcha"
    assert normalize_mode("something-else") == "google"
    assert normalize_mode(None) == "google"


def test_google_solution_becomes_recaptcharesponse() -> None:
    fields = build_captcha_fields("google", CaptchaSolution({"token": "abc123"}, "google"))

    assert fields == {"recaptcharesponse": "abc123"}


def test_geetest_solution_becomes_v4_payload_fields() -> None:
    solution = CaptchaSolution(
        {
            "lot_number": "lot",
            "captcha_output": "output",
            "pass_token": "pass",
            "gen_time": "123",
        },
        "geetest",
    )

    assert build_captcha_fields("geetest", solution) == {
        "captcha_type": "geetest",
        "lotnumber": "lot",
        "output": "output",
        "token": "pass",
        "gentime": "123",
    }


def test_no_captcha_mode_never_adds_fields() -> None:
    assert build_captcha_fields("no-captcha", None) == {}
    assert build_captcha_fields("google", None) == {}


def test_build_solver_defaults_to_noop() -> None:
    settings = Settings(solver_provider="none")

    assert isinstance(build_solver(settings), NoopSolver)


def test_noop_solver_reports_a_clear_error() -> None:
    solver = NoopSolver()

    try:
        import asyncio

        asyncio.run(solver.solve("google"))
    except RuntimeError as exception:
        assert "TELKOMSEL_SOLVER_PROVIDER" in str(exception)
    else:  # pragma: no cover - defensive
        raise AssertionError("NoopSolver should raise")
