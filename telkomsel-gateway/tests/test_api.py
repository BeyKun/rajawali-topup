from __future__ import annotations

from fastapi.testclient import TestClient

from main import app


def test_health_reports_runtime_configuration() -> None:
    with TestClient(app) as client:
        response = client.get("/health")

    assert response.status_code == 200
    assert response.json()["service"] == "rajawali-telkomsel-gateway"
    assert response.json()["status"] == "ok"
