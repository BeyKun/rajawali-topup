FROM python:3.11-slim

WORKDIR /app

# Install minimal OS dependencies for image processing
RUN apt-get update && apt-get install -y --no-install-recommends \
    libgl1 \
    libglib2.0-0 \
    libgomp1 \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .

RUN pip install --no-cache-dir -r requirements.txt

# Pre-download RapidOCR ONNX models during build so container starts immediately
RUN python -c "from rapidocr_onnxruntime import RapidOCR; ocr = RapidOCR(); print('RapidOCR initialized successfully')"

COPY . .

EXPOSE 8000

ENV PYTHONUNBUFFERED=1

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]
