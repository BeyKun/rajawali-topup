import { createWorker } from 'tesseract.js';

export interface ScannedVoucherRow {
    id: string;
    serial_number: string;
    hrn: string;
    sell_price: number;
    serial_status: 'valid' | 'warning' | 'invalid';
    hrn_status: 'valid' | 'warning' | 'invalid';
}

export interface OcrProgress {
    status: string;
    progress: number;
}

export interface ScanOptions {
    autoContrast?: boolean;
    scaleIfNeeded?: boolean;
    rawTextCallback?: (rawText: string) => void;
}

export interface CandidateItem {
    rawText: string;
    digits: string;
    bbox: { x0: number; y0: number; x1: number; y1: number };
    isExplicitSerial?: boolean;
    isExplicitHrn?: boolean;
}

/**
 * Remove label prefixes like "No Seri", "SN", "Kode Voucher", "HRN", "PIN"
 * before sanitizing digits so letters (like 'o', 'S', 'i') do not turn into fake numbers.
 */
export function stripLabels(raw: string): string {
    return raw
        // Remove Serial labels: "No. Seri", "No Seri", "Nomor Seri", "Seri", "SN", "S/N", "Serial No", "Barcode"
        .replace(/(?:no\.?\s*seri|nomor\s*seri|seri|s\/n|s\.n\b|sn\b|serial(?:\s*no(?:\.|\b)?)?|barcode)\s*[:\.\-]?\s*/gi, '')
        // Remove Voucher code / HRN / PIN labels: "Kode Voucher", "Kode Garuk", "Kode HRN", "Kode", "HRN", "PIN", "Gosok Di Sini"
        .replace(/(?:kode\s*voucher|kode\s*garuk|kode\s*hrn|kode\s*pin|kode\s*rahasia|kode|hrn\b|pin\b|garuk|voucher\s*code|secret\s*code|gosok(?:\s*di\s*sini|\s*disini)?)\s*[:\.\-]?\s*/gi, '')
        // Remove common voucher card noise like URLs and expiry notes
        .replace(/(?:masa\s*aktif|exp(?:\.|\s*date)?|berlaku\s*s\/?d|call\s*center|tsel\.me|http[s]?:\/\/\S+)\b[^\n]*/gi, '');
}

/**
 * Clean numeric string removing common OCR confusion (O->0, I/l->1, S->5, B->8)
 * and stripping non-digit characters.
 */
export function sanitizeDigits(str: string): string {
    return str
        .replace(/[oO]/g, '0')
        .replace(/[iIlL|]/g, '1')
        .replace(/[sS]/g, '5')
        .replace(/[bB]/g, '8')
        .replace(/\D/g, '');
}

/**
 * Preprocesses an image to improve Tesseract OCR recognition accuracy.
 * Uses client-side HTML5 Canvas for grayscale conversion, dynamic contrast stretching,
 * and smart resolution scaling.
 */
export async function preprocessVoucherImage(
    source: File | string,
    options: { autoContrast?: boolean; scaleIfNeeded?: boolean } = { autoContrast: true, scaleIfNeeded: true },
): Promise<string> {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return typeof source === 'string' ? source : URL.createObjectURL(source);
    }

    return new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';

        const objectUrl = typeof source === 'string' ? source : URL.createObjectURL(source);

        img.onload = () => {
            try {
                const canvas = document.createElement('canvas');
                const naturalWidth = img.naturalWidth || img.width;
                const naturalHeight = img.naturalHeight || img.height;

                // Character height around 30-35px yields best accuracy in Tesseract.
                // Scale small images up (min ~1400px), limit overly large images to ~2400px.
                let scale = 1;
                if (options.scaleIfNeeded) {
                    if (naturalWidth < 1200) {
                        scale = Math.min(1600 / naturalWidth, 2.5);
                    } else if (naturalWidth > 2600) {
                        scale = 2400 / naturalWidth;
                    }
                }

                canvas.width = Math.round(naturalWidth * scale);
                canvas.height = Math.round(naturalHeight * scale);

                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                if (!ctx) {
                    resolve(objectUrl);
                    return;
                }

                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imgData.data;

                // 1. Grayscale & compute luminance range for dynamic contrast stretching
                let minL = 255;
                let maxL = 0;
                const totalPixels = data.length / 4;
                const lums = new Uint8ClampedArray(totalPixels);

                for (let i = 0, j = 0; i < data.length; i += 4, j++) {
                    // Rec. 601 luma formula
                    const lum = Math.round(0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]);
                    lums[j] = lum;
                    if (lum < minL) minL = lum;
                    if (lum > maxL) maxL = lum;
                }

                // 2. Dynamic contrast stretching (stretches dark text to near black, background to white)
                if (options.autoContrast && maxL > minL + 20) {
                    const range = Math.max(maxL - minL, 1);
                    for (let i = 0, j = 0; i < data.length; i += 4, j++) {
                        let stretched = ((lums[j] - minL) / range) * 255;
                        // S-curve contrast boost: darken darks, brighten lights
                        if (stretched < 120) {
                            stretched = Math.max(0, stretched * 0.85);
                        } else {
                            stretched = Math.min(255, 120 + (stretched - 120) * 1.15);
                        }

                        data[i] = stretched;
                        data[i + 1] = stretched;
                        data[i + 2] = stretched;
                    }
                } else {
                    for (let i = 0, j = 0; i < data.length; i += 4, j++) {
                        const val = lums[j];
                        data[i] = val;
                        data[i + 1] = val;
                        data[i + 2] = val;
                    }
                }

                ctx.putImageData(imgData, 0, 0);
                const preprocessedUrl = canvas.toDataURL('image/jpeg', 0.92);
                resolve(preprocessedUrl);
            } catch (err) {
                console.warn('Preprocessing canvas error, using original image:', err);
                resolve(objectUrl);
            }
        };

        img.onerror = () => {
            resolve(objectUrl);
        };

        img.src = objectUrl;
    });
}

/**
 * Scan an image file and extract voucher serial numbers and HRN codes.
 */
export async function scanVoucherImage(
    imageSource: File | string,
    onProgress?: (p: OcrProgress) => void,
    options: ScanOptions = { autoContrast: true, scaleIfNeeded: true },
): Promise<ScannedVoucherRow[] & { rawText?: string }> {
    if (onProgress) {
        onProgress({ status: 'Mempersiapkan & mengoptimalkan gambar...', progress: 0.05 });
    }

    // Step 1: Preprocess image with HTML5 canvas
    let optimizedImageSource: string = typeof imageSource === 'string' ? imageSource : URL.createObjectURL(imageSource);
    try {
        if (options.autoContrast !== false) {
            optimizedImageSource = await preprocessVoucherImage(imageSource, {
                autoContrast: options.autoContrast,
                scaleIfNeeded: options.scaleIfNeeded,
            });
        }
    } catch (e) {
        console.warn('Canvas preprocessing skipped:', e);
    }

    if (onProgress) {
        onProgress({ status: 'Menyiapkan engine OCR Tesseract...', progress: 0.15 });
    }

    const worker = await createWorker('eng', 1, {
        logger: (m) => {
            if (m.status === 'recognizing text' && onProgress) {
                onProgress({
                    status: 'Memindai teks pada gambar...',
                    progress: 0.2 + (m.progress || 0) * 0.7,
                });
            }
        },
    });

    try {
        // Step 2: Configure enhanced parameters for voucher reading
        await worker.setParameters({
            tessedit_pageseg_mode: '4', // Single column layout (prevents mixing QR code & right-hand instructions)
            tessedit_char_whitelist: '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz:.-/ ()#*',
            preserve_interword_spaces: '1',
        });

        const ret = await worker.recognize(optimizedImageSource, {}, { blocks: true });
        await worker.terminate();

        const fullText = ret.data.text || '';
        if (options.rawTextCallback) {
            options.rawTextCallback(fullText);
        }

        if (onProgress) {
            onProgress({ status: 'Menganalisis nomor seri & kode voucher...', progress: 0.95 });
        }

        // Extract all lines from Tesseract.js v7 blocks hierarchy
        const lines: Array<{
            text: string;
            bbox: { x0: number; y0: number; x1: number; y1: number };
        }> = [];

        if (ret.data.blocks && Array.isArray(ret.data.blocks)) {
            for (const b of ret.data.blocks) {
                for (const p of b.paragraphs || []) {
                    for (const l of p.lines || []) {
                        const trimmed = l.text.trim();
                        if (trimmed) {
                            lines.push({ text: trimmed, bbox: l.bbox });
                        }
                    }
                }
            }
        }

        // Sort all lines top-to-bottom
        lines.sort((a, b) => (a.bbox?.y0 ?? 0) - (b.bbox?.y0 ?? 0));

        const serialCandidates: CandidateItem[] = [];
        const hrnCandidates: CandidateItem[] = [];

        for (const line of lines) {
            const rawText = line.text.trim();
            if (!rawText) continue;

            const bbox = line.bbox || { x0: 0, y0: 0, x1: 0, y1: 0 };

            // 1. Check for Serial Number in this line
            // Telkomsel pattern: starts with 3003 followed by digits
            const tselSerialMatch = rawText.match(/\b(3003[\s\-]?[0-9\s\-]{8,15})\b/i);
            const labelSerialMatch = rawText.match(/(?:no\.?\s*seri|nomor\s*seri|seri\b|s\/n\b|s\.n\b|sn\b|serial)\s*[:\.\-]?\s*([0-9\s\-]{10,18})/i);

            if (tselSerialMatch || labelSerialMatch) {
                const matchStr = tselSerialMatch ? tselSerialMatch[1] : labelSerialMatch![1];
                let sDigits = matchStr.replace(/\D/g, '');

                // Rule: For 15-digit serial numbers, discard the last 3 digits (store 12 digits)
                if (sDigits.length >= 15) {
                    sDigits = sDigits.slice(0, 12);
                } else if (sDigits.startsWith('3003') && sDigits.length > 12) {
                    sDigits = sDigits.slice(0, 12);
                }

                if (sDigits.length >= 12) {
                    serialCandidates.push({
                        rawText,
                        digits: sDigits,
                        bbox,
                        isExplicitSerial: true,
                    });
                    continue;
                }
            }

            // 2. Check for Voucher Code (HRN / Garukan)
            // Exclude lines with serial keywords, URLs, or contact center 188
            if (!/no\.?\s*seri|nomor\s*seri|^3003/i.test(rawText)) {
                // A. Exact 17-digit pattern: 5-5-5-2 (e.g. 71125 61343 18480 01)
                const p17 = rawText.match(/\b(\d{5})[\s\-]+(\d{5})[\s\-]+(\d{5})[\s\-]+(\d{2})\b/);
                if (p17) {
                    const hDigits = `${p17[1]}${p17[2]}${p17[3]}${p17[4]}`;
                    hrnCandidates.push({
                        rawText,
                        digits: hDigits,
                        bbox,
                        isExplicitHrn: true,
                    });
                    continue;
                }

                // B. 16-digit pattern: 4-4-4-4
                const p16 = rawText.match(/\b(\d{4})[\s\-]+(\d{4})[\s\-]+(\d{4})[\s\-]+(\d{4})\b/);
                if (p16) {
                    const hDigits = `${p16[1]}${p16[2]}${p16[3]}${p16[4]}`;
                    hrnCandidates.push({
                        rawText,
                        digits: hDigits,
                        bbox,
                        isExplicitHrn: true,
                    });
                    continue;
                }

                // C. General digit cluster
                const cleaned = rawText
                    .replace(/https?:\/\/\S+/gi, '')
                    .replace(/(?:contact|center|layanan|call|voucher|wilayah|aktif|tsel\.id|tsel\.me)\b[^\n]*/gi, '');

                const match = cleaned.match(/([0-9\s\-]{12,25})/);
                if (match) {
                    let hDigits = match[0].replace(/\D/g, '');
                    // Handle speck like "1.29225..."
                    if (hDigits.length === 18 && rawText.includes('.')) {
                        hDigits = hDigits.slice(1);
                    }
                    if (hDigits.length > 17) {
                        hDigits = hDigits.slice(0, 17);
                    }
                    if (hDigits.length >= 12) {
                        hrnCandidates.push({
                            rawText,
                            digits: hDigits,
                            bbox,
                            isExplicitHrn: false,
                        });
                        continue;
                    }
                }
            }
        }

        // Sort candidates strictly top-to-bottom
        serialCandidates.sort((a, b) => a.bbox.y0 - b.bbox.y0);
        hrnCandidates.sort((a, b) => a.bbox.y0 - b.bbox.y0);

        const detectedRows: ScannedVoucherRow[] = [];
        const usedHrnIndices = new Set<number>();

        // Step 3: Bounded Spatial Pairing
        // For each Serial(i), its HRN MUST be below Serial(i) and ABOVE Serial(i+1)!
        for (let idx = 0; idx < serialCandidates.length; idx++) {
            const s = serialCandidates[idx];
            const nextSerial = serialCandidates[idx + 1];

            const sCenterX = (s.bbox.x0 + s.bbox.x1) / 2;
            const minY = s.bbox.y0 + 15;
            // HRN must be before next serial (with small margin) or within 120px
            const maxY = nextSerial ? Math.max(nextSerial.bbox.y0 - 5, minY + 30) : s.bbox.y0 + 130;

            let bestHrnIndex = -1;
            let minScore = Infinity;

            for (let i = 0; i < hrnCandidates.length; i++) {
                if (usedHrnIndices.has(i)) continue;
                const h = hrnCandidates[i];

                // Check horizontal column alignment (must be in same column)
                const hCenterX = (h.bbox.x0 + h.bbox.x1) / 2;
                const dx = Math.abs(sCenterX - hCenterX);
                if (dx > 250) continue; // Different column

                // Must be within vertical boundary of this voucher card
                if (h.bbox.y0 >= minY && h.bbox.y0 <= maxY) {
                    const dy = h.bbox.y0 - s.bbox.y0;
                    const score = Math.hypot(dx * 1.5, dy);
                    if (score < minScore) {
                        minScore = score;
                        bestHrnIndex = i;
                    }
                }
            }

            let hrnDigits = '';
            if (bestHrnIndex !== -1) {
                hrnDigits = hrnCandidates[bestHrnIndex].digits;
                usedHrnIndices.add(bestHrnIndex);
            }

            detectedRows.push({
                id: `v-${Date.now()}-${idx}-${Math.random().toString(36).slice(2, 6)}`,
                serial_number: s.digits,
                hrn: hrnDigits,
                sell_price: 25000,
                serial_status: s.digits.length === 12 ? 'valid' : 'warning',
                hrn_status: hrnDigits.length === 17 ? 'valid' : 'warning',
            });
        }

        if (onProgress) {
            onProgress({ status: 'Selesai!', progress: 1 });
        }

        const resultRows = detectedRows as ScannedVoucherRow[] & { rawText?: string };
        resultRows.rawText = fullText;
        return resultRows;
    } catch (error) {
        await worker.terminate();
        throw error;
    }
}

