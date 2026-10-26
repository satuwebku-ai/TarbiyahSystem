import numpy as np
import pytest
from fastapi.testclient import TestClient

from app import NoFace, create_app

TOKEN = "token-uji-yang-cukup-panjang"
AUTH = {"Authorization": f"Bearer {TOKEN}"}


class FakeEmbedder:
    """Isi berkas menentukan vektor: b'sama' -> [1,0]; b'lain' -> [0,1]; b'mirip' -> kosinus ~0,45 terhadap [1,0]; b'kosong' -> tanpa wajah."""

    VECTORS = {b"sama": [1.0, 0.0], b"lain": [0.0, 1.0], b"mirip": [0.45, 0.893]}

    def embed(self, data: bytes):
        if data == b"kosong":
            raise NoFace()
        return np.array(self.VECTORS[data])


@pytest.fixture()
def client():
    return TestClient(create_app(embedder=FakeEmbedder(), token=TOKEN))


def post(client, ref, probe, headers=AUTH):
    return client.post("/compare", files={"reference": ("r.jpg", ref), "probe": ("p.jpg", probe)}, headers=headers)


def test_requires_long_token():
    with pytest.raises(RuntimeError):
        create_app(embedder=FakeEmbedder(), token="pendek")


def test_health_is_open(client):
    assert client.get("/health").json() == {"ok": True}


def test_rejects_missing_or_wrong_token(client):
    assert post(client, b"sama", b"sama", headers={}).status_code == 401
    assert post(client, b"sama", b"sama", headers={"Authorization": "Bearer salah"}).status_code == 401


def test_identical_faces_score_100_and_different_score_0(client):
    assert post(client, b"sama", b"sama").json() == {"similarity": 100.0}
    assert post(client, b"sama", b"lain").json() == {"similarity": 0.0}


def test_in_between_score_is_mapped_to_percent(client):
    s = post(client, b"sama", b"mirip").json()["similarity"]
    assert 0 < s < 100


def test_no_face_returns_422_with_error_code(client):
    r = post(client, b"sama", b"kosong")
    assert r.status_code == 422 and r.json() == {"error": "no_face"}


def test_oversized_upload_is_rejected(client):
    assert post(client, b"x" * (5 * 1024 * 1024 + 1), b"sama").status_code == 413


def test_docs_endpoints_are_disabled(client):
    assert client.get("/docs").status_code == 404 and client.get("/openapi.json").status_code == 404
