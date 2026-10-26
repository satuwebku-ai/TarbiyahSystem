"""
Layanan pencocok wajah contoh untuk TarbiyahSystem (driver "http").

Protokol (harus sama dengan app/Services/Face/HttpFaceMatcher.php):
  POST /compare   multipart: reference=<berkas foto acuan>, probe=<berkas selfie>
                  Header: Authorization: Bearer <FACE_SERVICE_TOKEN>
  200 {"similarity": 0-100}     422 {"error": "no_face"}     401 token salah     413 berkas terlalu besar

Layanan ini TIDAK menyimpan foto: berkas dibaca ke memori, dibandingkan, lalu dibuang.
Jalankan:  uvicorn app:create_app --factory --host 127.0.0.1 --port 8099
"""

import hmac
import os

import numpy as np
from fastapi import FastAPI, Header, HTTPException, UploadFile
from fastapi.responses import JSONResponse

MAX_BYTES = 5 * 1024 * 1024


class NoFace(Exception):
    """Tidak ada wajah pada gambar (atau gambar tidak bisa dibaca)."""


class InsightFaceEmbedder:
    """Pengubah wajah -> vektor memakai InsightFace (dimuat saat dipakai pertama kali).

    PERIKSA LISENSI model yang dipakai: sebagian model bawaan InsightFace hanya untuk riset/nonkomersial.
    """

    def __init__(self, model: str = "buffalo_l"):
        self.model = model
        self._app = None

    def _load(self):
        if self._app is None:
            from insightface.app import FaceAnalysis

            app = FaceAnalysis(name=self.model, providers=["CPUExecutionProvider"])
            app.prepare(ctx_id=-1, det_size=(640, 640))
            self._app = app
        return self._app

    def embed(self, data: bytes) -> np.ndarray:
        import cv2

        img = cv2.imdecode(np.frombuffer(data, np.uint8), cv2.IMREAD_COLOR)
        if img is None:
            raise NoFace()
        faces = self._load().get(img)
        if not faces:
            raise NoFace()
        face = max(faces, key=lambda f: (f.bbox[2] - f.bbox[0]) * (f.bbox[3] - f.bbox[1]))
        return face.normed_embedding


def to_percent(cosine: float, low: float, high: float) -> float:
    """Petakan kemiripan kosinus ke 0-100. low -> 0%, high -> 100%. Angka perlu disetel dengan data sekolah sendiri."""
    return max(0.0, min(100.0, (cosine - low) / (high - low) * 100.0))


def create_app(embedder=None, token: str | None = None) -> FastAPI:
    token = token if token is not None else os.environ.get("FACE_SERVICE_TOKEN", "")
    if len(token) < 16:
        raise RuntimeError("FACE_SERVICE_TOKEN wajib diisi (minimal 16 karakter acak).")
    low = float(os.environ.get("FACE_COS_LOW", "0.2"))
    high = float(os.environ.get("FACE_COS_HIGH", "0.7"))
    embedder = embedder or InsightFaceEmbedder(os.environ.get("FACE_MODEL", "buffalo_l"))

    app = FastAPI(title="Pencocok wajah", docs_url=None, redoc_url=None, openapi_url=None)

    @app.get("/health")
    def health():
        return {"ok": True}

    # def (bukan async): FastAPI menjalankannya di threadpool sehingga perhitungan berat tidak memblokir.
    @app.post("/compare")
    def compare(reference: UploadFile, probe: UploadFile, authorization: str = Header(default="")):
        if not hmac.compare_digest(authorization.encode(), f"Bearer {token}".encode()):
            raise HTTPException(status_code=401, detail="unauthorized")

        ref_bytes = reference.file.read(MAX_BYTES + 1)
        probe_bytes = probe.file.read(MAX_BYTES + 1)
        if len(ref_bytes) > MAX_BYTES or len(probe_bytes) > MAX_BYTES:
            raise HTTPException(status_code=413, detail="berkas terlalu besar")

        try:
            a = embedder.embed(ref_bytes)
            b = embedder.embed(probe_bytes)
        except NoFace:
            return JSONResponse({"error": "no_face"}, status_code=422)

        cosine = float(np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b)))
        return {"similarity": round(to_percent(cosine, low, high), 2)}

    return app
