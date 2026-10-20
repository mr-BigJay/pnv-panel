FROM mcr.microsoft.com/playwright/python:v1.49.1-jammy

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY worker ./worker

ENV TEJARAT_BANK_URL=https://hb.tejaratbank.ir \
    PNV_WORKER_STORAGE=/data/storage \
    PYTHONUNBUFFERED=1

VOLUME ["/data/storage"]

CMD ["python", "-m", "worker.main"]
