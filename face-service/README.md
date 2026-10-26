# face-service

Layanan pencocok wajah contoh untuk driver "Layanan sendiri (HTTP)". Panduan lengkap: `../docs/PENCOCOKAN-WAJAH.md`.

    pip install -r requirements.txt
    FACE_SERVICE_TOKEN=<acak, minimal 16 karakter> uvicorn app:create_app --factory --host 127.0.0.1 --port 8099
    python3 -m pytest test_app.py
