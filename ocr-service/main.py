import io
import math
import re
from typing import Any, Dict, List, Optional
from fastapi import FastAPI, File, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from PIL import Image
from rapidocr_onnxruntime import RapidOCR

app = FastAPI(title="Rajawali Voucher OCR Service", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize RapidOCR engine
engine = RapidOCR()


def sanitize_ocr_confusion(s: str) -> str:
    """Normalize common OCR confusions for voucher numbers."""
    trans = str.maketrans({
        'O': '0', 'o': '0', 'Q': '0', 'D': '0',
        'I': '1', 'i': '1', 'l': '1', '|': '1', '!': '1', '[': '1', ']': '1',
        'Z': '2', 'z': '2',
        'E': '3',
        'A': '4',
        'S': '5', 's': '5', '$': '5',
        'G': '6',
        'T': '7', 't': '7', '/': '7', '?': '7',
        'B': '8', '&': '8',
        'g': '9', 'q': '9'
    })
    return s.translate(trans)


def strip_labels(s: str) -> str:
    """Strip common label prefixes and USSD symbols."""
    # Serial prefixes
    s = re.sub(r'(?i)(?:no\.?\s*seri|nomor\s*seri|seri\b|s/n\b|s\.n\b|sn\b|serial(?:\s*no(?:\.|\b)?)?|barcode)\s*[:.\-]?\s*', '', s)
    # Voucher code prefixes
    s = re.sub(r'(?i)(?:kode\s*voucher|kode\s*garuk|kode\s*hrn|kode\s*pin|kode\s*rahasia|kode|hrn\b|pin\b|garuk|voucher\s*code|secret\s*code|gosok(?:\s*di\s*sini|\s*disini)?)\s*[:.\-]?\s*', '', s)
    # USSD prefixes & suffixes
    s = re.sub(r'(?i)\*(?:133|556|111|123)\*[\s\-]*', '', s)
    s = re.sub(r'#\s*$', '', s)
    # Promo / marketing noise
    s = re.sub(r'(?i)https?://\S+', '', s)
    s = re.sub(r'(?i)(?:tsel\.me|tsel\.id|call\s*center\s*188|layanan\s*pelanggan|masa\s*aktif|exp(?:\.|\s*date)?|berlaku\s*s/?d)\b', '', s)
    return s.strip()


def extract_serial_from_text(text: str) -> Optional[str]:
    """
    Extract serial number from candidate line.
    Rule: Only the first 12 digits are used; any trailing digits (e.g. 3 digits) are discarded.
    """
    cleaned = strip_labels(text)
    norm = sanitize_ocr_confusion(cleaned)
    digits = re.sub(r'\D', '', norm)

    result = None
    # 1. Telkomsel pattern: starts with 3003 or 5007 (commonly 12 to 16 digits)
    m_tsel = re.search(r'\b((?:3003|5007)\d{8,12})\b', digits)
    if m_tsel:
        result = m_tsel.group(1)
    # 2. Labeled or explicit serial line: "No Seri : ..."
    elif re.search(r'(?i)(?:seri|sn|s/n|serial|barcode)', text):
        if 12 <= len(digits) <= 18:
            result = digits
    # 3. Direct 12 or 15 or 16 digit number (common serial lengths)
    elif len(digits) in (12, 13, 14, 15, 16):
        result = digits

    if result:
        # Serial number only uses the first 12 digits (trailing 3 digits are not used)
        if len(result) > 12:
            result = result[:12]
        return result

    return None

    return None


def extract_hrn_from_text(text: str) -> Optional[str]:
    """Extract HRN / Voucher Code (14-17 digits) from candidate line."""
    cleaned = strip_labels(text)
    norm = sanitize_ocr_confusion(cleaned)
    digits = re.sub(r'\D', '', norm)

    # Do not treat Telkomsel serials (starts with 3003/5007 <= 15 digits) as HRN
    if (digits.startswith('3003') or digits.startswith('5007')) and len(digits) <= 15:
        return None

    # Telkomsel 17 digits
    if len(digits) >= 17:
        return digits[-17:]

    # Standard 16 digits (Telkomsel/XL/Axis/Tri/Smartfren)
    if len(digits) == 16:
        return digits

    # Indosat 14 digits
    if len(digits) == 14:
        return digits

    return None


@app.get("/health")
def health() -> Dict[str, str]:
    return {"status": "ok", "service": "rajawali-ocr"}


@app.post("/predict")
async def predict(file: UploadFile = File(...)) -> Dict[str, Any]:
    """Run raw RapidOCR on uploaded image."""
    try:
        contents = await file.read()
        image = Image.open(io.BytesIO(contents)).convert("RGB")
        ocr_result, _ = engine(image)

        items = []
        if ocr_result:
            for box, text, score in ocr_result:
                items.append({
                    "box": box,
                    "text": text,
                    "score": float(score)
                })

        return {"success": True, "count": len(items), "items": items}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/extract-vouchers")
async def extract_vouchers(file: UploadFile = File(...)) -> Dict[str, Any]:
    """
    Run RapidOCR, detect serial numbers and voucher codes, and pair them
    spatially based on voucher card orientation and layout.
    """
    try:
        contents = await file.read()
        image = Image.open(io.BytesIO(contents)).convert("RGB")
        ocr_result, _ = engine(image)

        if not ocr_result:
            return {"success": True, "vouchers": [], "raw_texts": []}

        raw_texts: List[str] = []
        candidates_serial: List[Dict[str, Any]] = []
        candidates_hrn: List[Dict[str, Any]] = []

        for item in ocr_result:
            box, text, score = item
            score_f = float(score)
            raw_texts.append(text)

            # Compute bounding box metrics
            xs = [p[0] for p in box]
            ys = [p[1] for p in box]
            x_min, x_max = min(xs), max(xs)
            y_min, y_max = min(ys), max(ys)
            cx = (x_min + x_max) / 2.0
            cy = (y_min + y_max) / 2.0

            serial = extract_serial_from_text(text)
            if serial:
                candidates_serial.append({
                    "digits": serial,
                    "raw": text,
                    "box": box,
                    "cx": cx,
                    "cy": cy,
                    "x0": x_min,
                    "y0": y_min,
                    "score": score_f
                })

            hrn = extract_hrn_from_text(text)
            if hrn:
                # Avoid duplicate if it matches the serial candidate in same position
                if not serial or serial != hrn:
                    candidates_hrn.append({
                        "digits": hrn,
                        "raw": text,
                        "box": box,
                        "cx": cx,
                        "cy": cy,
                        "x0": x_min,
                        "y0": y_min,
                        "score": score_f
                    })

        # Pair Serials and HRNs using spatial proximity
        # Sort candidates top-to-bottom
        candidates_serial.sort(key=lambda s: s["y0"])
        candidates_hrn.sort(key=lambda h: h["y0"])

        vouchers: List[Dict[str, Any]] = []
        used_hrn_indices = set()

        for s_idx, s in enumerate(candidates_serial):
            best_h_idx = -1
            min_dist = float("inf")

            for h_idx, h in enumerate(candidates_hrn):
                if h_idx in used_hrn_indices:
                    continue

                dx = abs(s["cx"] - h["cx"])
                dy = h["cy"] - s["cy"]

                # HRN is directly below serial on the voucher card
                effective_dy = dy if dy >= -20 else abs(dy) * 3.0
                dist = math.hypot(dx * 1.5, effective_dy)

                if dist < min_dist:
                    min_dist = dist
                    best_h_idx = h_idx

            hrn_val = ""
            conf = s["score"]
            if best_h_idx != -1 and min_dist < 450:
                hrn_val = candidates_hrn[best_h_idx]["digits"]
                conf = (s["score"] + candidates_hrn[best_h_idx]["score"]) / 2.0
                used_hrn_indices.add(best_h_idx)

            vouchers.append({
                "serial_number": s["digits"],
                "hrn": hrn_val,
                "confidence": round(conf, 2),
                "serial_status": "valid" if len(s["digits"]) in (12, 15, 16) else "warning",
                "hrn_status": "valid" if len(hrn_val) in (14, 16, 17) else "warning"
            })

        # Capture any orphan HRNs not paired with a serial
        for h_idx, h in enumerate(candidates_hrn):
            if h_idx not in used_hrn_indices:
                vouchers.append({
                    "serial_number": "",
                    "hrn": h["digits"],
                    "confidence": round(h["score"], 2),
                    "serial_status": "warning",
                    "hrn_status": "valid" if len(h["digits"]) in (14, 16, 17) else "warning"
                })

        return {
            "success": True,
            "vouchers": vouchers,
            "raw_texts": raw_texts
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
