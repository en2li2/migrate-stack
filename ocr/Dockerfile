FROM python:3.11-slim

ENV DEBIAN_FRONTEND=noninteractive \
    PIP_NO_CACHE_DIR=1 \
    OCR_LANG=en

RUN apt-get update && apt-get install -y --no-install-recommends \
      libgl1 libglib2.0-0 libgomp1 curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# paddlepaddle ayrı katman (büyük indirme; cache''te kalsın).
RUN pip install --no-cache-dir paddlepaddle==3.0.0

# paddlex 3.0.2 PIN — 3.7 API''yi bozuyor (PaddlePredictorOption hatası).
RUN pip install --no-cache-dir paddleocr==3.1.1 \
      fastapi "uvicorn[standard]" python-multipart pillow

# paddleocr 3.7.2 paddlex çekiyor; 3.0.2ye zorla düşür (3.7 APIyi bozuyor).
RUN pip install --no-cache-dir paddlex==3.0.2

COPY app.py /app/app.py

# Modelleri derlemede indir (ilk istek beklemesin; başarısızsa build durmaz).
RUN python -c "from app import get_ocr; get_ocr()" || true

EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --start-period=120s --retries=3 \
  CMD curl -fsS http://127.0.0.1:8000/health || exit 1

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "1"]
