import { createWorker, PSM } from 'tesseract.js';

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
 * Remove label prefixes like "No Seri", "SN", "Kode Voucher", "HRN", "PIN",
 * USSD dial codes like "*133*", and trailing "#" before parsing numbers.
 * Crucially, does NOT destructively truncate the rest of the line.
 */
export function stripLabels(raw: string): string {
    return raw
        // Remove Serial labels: "No. Seri", "No Seri", "Nomor Seri", "Seri", "SN", "S/N", "Serial No", "Barcode"
        .replace(/(?:no\.?\s*seri|nomor\s*seri|seri\b|s\/n\b|s\.n\b|sn\b|serial(?:\s*no(?:\.|\b)?)?|barcode)\s*[:\.\-]?\s*/gi, '')
        // Remove Voucher code / HRN / PIN labels: "Kode Voucher", "Kode Garuk", "Kode HRN", "Kode", "HRN", "PIN", "Gosok Di Sini"
        .replace(/(?:kode\s*voucher|kode\s*garuk|kode\s*hrn|kode\s*pin|kode\s*rahasia|kode|hrn\b|pin\b|garuk|voucher\s*code|secret\s*code|gosok(?:\s*di\s*sini|\s*disini)?)\s*[:\.\-]?\s*/gi, '')
        // Remove USSD dial prefixes like "*133*", "*556*", "*111*", "*123*"
        .replace(/\*(?:133|556|111|123)\*[\s\-]?/gi, '')
        // Remove trailing USSD hash "#"
        .replace(/#\s*$/g, '')
        // Remove URLs and non-digit keywords without wiping out trailing numbers
        .replace(/https?:\/\/\S+/gi, '')
        .replace(/(?:tsel\.me|tsel\.id|call\s*center\s*188|layanan\s*pelanggan|masa\s*aktif|exp(?:\.|\s*date)?|berlaku\s*s\/?d)\b/gi, '');
}

/**
 * Translates common OCR confusion where numbers are recognized as letters:
 * O/o/Q/D -> 0, I/l/|/!/]/[ -> 1, Z/z -> 2, E -> 3, A -> 4, S/s/$ -> 5,
 * G/b -> 6, T/t/?// -> 7, B/& -> 8, g/q -> 9
 */
export function sanitizeOcrConfusion(str: string): string {
    return str
        .replace(/[oOQD]/g, '0')
        .replace(/[iIlL|!\]\[]/g, '1')
        .replace(/[zZ]/g, '2')
        .replace(/[E]/g, '3')
        .replace(/[A]/g, '4')
        .replace(/[sS$]/g, '5')
        .replace(/[G]/g, '6')
        .replace(/[Tt\/?]/g, '7')
        .replace(/[bB&]/g, '8')
        .replace(/[gq]/g, '9');
}

/**
 * Clean numeric string removing common OCR confusion and stripping non-digit characters.
 */
export function sanitizeDigits(str: string): string {
    return sanitizeOcrConfusion(str).replace(/\D/g, '');
}

/**
 * Attempts to extract an HRN (voucher code) from a line or block of text.
 * Supports:
 * - Telkomsel 17-digit format (5-5-5-2 or continuous, e.g. 71125 61343 18480 01)
 * - Telkomsel/XL/Axis/Smartfren/Tri 16-digit format (4-4-4-4 or continuous)
 * - Indosat 14-digit format
 */
export function extractHrnCandidate(rawText: string): { digits: string; isExplicitHrn: boolean } | null {
    const trimmed = rawText.trim();
    if (!trimmed) return null;

    // Check if line is explicitly a serial number line (e.g. starts with 3003 or labeled No Seri)
    // Avoid mistaking Telkomsel serial (3003xxxx) for an HRN
    const isExplicitSerialLine = /^(?:no\.?\s*seri|nomor\s*seri|sn\b|s\/n\b|serial)/i.test(trimmed) ||
        /\b3003[\s\-]?[0-9\s\-]{8,12}\b/.test(trimmed);

    // 1. Direct regex on rawText for spaced 17-digit: 5-5-5-2
    const p17Raw = trimmed.match(/\b(\d{5})[\s\.\-]+(\d{5})[\s\.\-]+(\d{5})[\s\.\-]+(\d{2})\b/);
    if (p17Raw) {
        return { digits: `${p17Raw[1]}${p17Raw[2]}${p17Raw[3]}${p17Raw[4]}`, isExplicitHrn: true };
    }

    // 2. Direct regex on rawText for spaced 16-digit: 4-4-4-4
    const p16Raw = trimmed.match(/\b(\d{4})[\s\.\-]+(\d{4})[\s\.\-]+(\d{4})[\s\.\-]+(\d{4})\b/);
    if (p16Raw) {
        return { digits: `${p16Raw[1]}${p16Raw[2]}${p16Raw[3]}${p16Raw[4]}`, isExplicitHrn: true };
    }

    // 3. Normalized pass: strip labels, correct OCR confusions
    const cleaned = stripLabels(trimmed);
    const normalized = sanitizeOcrConfusion(cleaned);

    // 3a. Normalized spaced 17-digit
    const p17Norm = normalized.match(/\b(\d{5})[\s\.\-]+(\d{5})[\s\.\-]+(\d{5})[\s\.\-]+(\d{2})\b/);
    if (p17Norm) {
        return { digits: `${p17Norm[1]}${p17Norm[2]}${p17Norm[3]}${p17Norm[4]}`, isExplicitHrn: true };
    }

    // 3b. Normalized spaced 16-digit
    const p16Norm = normalized.match(/\b(\d{4})[\s\.\-]+(\d{4})[\s\.\-]+(\d{4})[\s\.\-]+(\d{4})\b/);
    if (p16Norm) {
        return { digits: `${p16Norm[1]}${p16Norm[2]}${p16Norm[3]}${p16Norm[4]}`, isExplicitHrn: true };
    }

    // 3c. Check for any digit cluster with optional separators (spaces, dots, hyphens)
    const matches = Array.from(normalized.matchAll(/(?:^|[^\d])([0-9\s\.\-]{12,28})(?:$|[^\d])/g));
    for (const m of matches) {
        let digits = m[1].replace(/\D/g, '');

        // If line contains serial keywords or looks like Telkomsel serial, do not treat as HRN
        if (digits.startsWith('3003') && digits.length <= 15 && isExplicitSerialLine) {
            continue;
        }

        // KEY FIX: If digits >= 17, take the LAST 17 digits!
        // Because leading noise (scratch box border, watermark, index "1.") precedes the code on the left!
        if (digits.length >= 17) {
            digits = digits.slice(-17);
        } else if (digits.length === 16) {
            // Keep 16 digits
        } else if (digits.length === 14) {
            // Indosat
        } else {
            // Ignore incomplete fragments (e.g. 15 digits) to avoid premature partial pairing
            continue;
        }

        return {
            digits,
            isExplicitHrn: /kode|voucher|hrn|garuk|pin/i.test(trimmed),
        };
    }

    return null;
}

/**
 * Attempts to extract a Serial Number from a line or block of text.
 * Supports:
 * - Telkomsel serial (starts with 3003)
 * - Serial with prefixes (No Seri, SN, S/N, Barcode)
 */
export function extractSerialCandidate(rawText: string): { digits: string; isExplicitSerial: boolean } | null {
    const trimmed = rawText.trim();
    if (!trimmed) return null;

    // Normalize OCR confusion for serial lines as well
    const normalized = sanitizeOcrConfusion(trimmed);

    // 1. Telkomsel pattern: starts with 3003 followed by digits
    const tselMatch = normalized.match(/\b(3003[\s\-]?[0-9\s\-]{8,15})\b/i);

    // 2. Explicit label match
    const labelMatch = normalized.match(/(?:no\.?\s*seri|nomor\s*seri|seri\b|s\/n\b|s\.n\b|sn\b|serial|barcode)\s*[:\.\-]?\s*([0-9\s\-]{10,18})/i);

    if (tselMatch || labelMatch) {
        const matchStr = tselMatch ? tselMatch[1] : labelMatch![1];
        let sDigits = matchStr.replace(/\D/g, '');

        // Rule: For 15-digit serial numbers, discard the last 3 digits (store 12 digits)
        if (sDigits.length >= 15) {
            sDigits = sDigits.slice(0, 12);
        } else if (sDigits.startsWith('3003') && sDigits.length > 12) {
            sDigits = sDigits.slice(0, 12);
        }

        if (sDigits.length >= 12) {
            return {
                digits: sDigits,
                isExplicitSerial: true,
            };
        }
    }

    return null;
}

/**
 * Preprocesses an image to improve Tesseract OCR recognition accuracy.
 * Uses client-side HTML5 Canvas for grayscale conversion and linear contrast adjustment.
 * Note: If image is already good quality (>= 500px), raw pixels are preserved to prevent
 * JPEG re-compression ringing artifacts on dot-matrix printed text.
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
                const naturalWidth = img.naturalWidth || img.width;
                const naturalHeight = img.naturalHeight || img.height;

                // If image already has good resolution, avoid canvas re-compression & blur
                if (naturalWidth >= 600 && naturalHeight >= 600 && !options.autoContrast) {
                    resolve(objectUrl);
                    return;
                }

                let scale = 1;
                if (options.scaleIfNeeded && naturalWidth < 600) {
                    scale = Math.min(1000 / naturalWidth, 2.0);
                }

                if (scale === 1 && !options.autoContrast) {
                    resolve(objectUrl);
                    return;
                }

                const canvas = document.createElement('canvas');
                canvas.width = Math.round(naturalWidth * scale);
                canvas.height = Math.round(naturalHeight * scale);

                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                if (!ctx) {
                    resolve(objectUrl);
                    return;
                }

                ctx.imageSmoothingEnabled = false; // Prevent blurring dot-matrix numbers
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imgData.data;

                // 1. Grayscale
                let minL = 255;
                let maxL = 0;
                const totalPixels = data.length / 4;
                const lums = new Uint8ClampedArray(totalPixels);

                for (let i = 0, j = 0; i < data.length; i += 4, j++) {
                    const lum = Math.round(0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]);
                    lums[j] = lum;
                    if (lum < minL) minL = lum;
                    if (lum > maxL) maxL = lum;
                }

                // 2. Linear contrast stretching without harsh S-curve clipping
                if (options.autoContrast && maxL > minL + 50) {
                    const range = maxL - minL;
                    for (let i = 0, j = 0; i < data.length; i += 4, j++) {
                        const val = Math.round(((lums[j] - minL) / range) * 255);
                        data[i] = val;
                        data[i + 1] = val;
                        data[i + 2] = val;
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
                // ALWAYS use lossless PNG to eliminate DCT compression artifacts
                const preprocessedUrl = canvas.toDataURL('image/png');
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
 * Features:
 * - PSM.SPARSE_TEXT for reliable full-photo edge detection without document margin clipping
 * - OCR character confusion auto-correction (O->0, I->1, S->5, B->8, etc.)
 * - Left-aligned column-aware spatial pairing (prevents column truncation)
 * - Smart Full-Text Merging: upgrades partial HRN candidates (15-digit -> 17-digit) without creating duplicate orphan rows
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
        // Step 2: Configure enhanced parameters
        // Use PSM.SPARSE_TEXT (11) so text anywhere across the photo (including near edges)
        // is captured reliably without document margin truncation.
        await worker.setParameters({
            tessedit_pageseg_mode: PSM.SPARSE_TEXT,
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

        // Extract all lines from Tesseract.js blocks hierarchy
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

        const serialCandidates: CandidateItem[] = [];
        const hrnCandidates: CandidateItem[] = [];

        for (const line of lines) {
            const rawText = line.text.trim();
            if (!rawText) continue;

            const bbox = line.bbox || { x0: 0, y0: 0, x1: 0, y1: 0 };

            // 1. Check for Serial Number in this line
            const serialResult = extractSerialCandidate(rawText);
            if (serialResult) {
                serialCandidates.push({
                    rawText,
                    digits: serialResult.digits,
                    bbox,
                    isExplicitSerial: serialResult.isExplicitSerial,
                });
            }

            // 2. Check for Voucher Code (HRN / Garukan) in this line
            const hrnResult = extractHrnCandidate(rawText);
            if (hrnResult) {
                // Do not add if identical to serial candidate in same position
                if (!serialResult || serialResult.digits !== hrnResult.digits) {
                    hrnCandidates.push({
                        rawText,
                        digits: hrnResult.digits,
                        bbox,
                        isExplicitHrn: hrnResult.isExplicitHrn,
                    });
                }
            }
        }

        // Step 3: Column-Aware Bounded Spatial Pairing
        // Group items into columns based on LEFT coordinate (x0) to avoid trailing text skew.
        const allCandidates = [...serialCandidates, ...hrnCandidates];
        const columnClusters: number[] = [];

        for (const item of allCandidates) {
            const x0 = item.bbox.x0;
            const existingCol = columnClusters.find((colX) => Math.abs(colX - x0) < 180);
            if (!existingCol) {
                columnClusters.push(x0);
            }
        }

        columnClusters.sort((a, b) => a - b);

        // Helper to find column index based on left coordinate x0
        const getColumnIndex = (bbox: { x0: number; x1: number }) => {
            const x0 = bbox.x0;
            let bestCol = 0;
            let minDiff = Infinity;
            for (let i = 0; i < columnClusters.length; i++) {
                const diff = Math.abs(columnClusters[i] - x0);
                if (diff < minDiff) {
                    minDiff = diff;
                    bestCol = i;
                }
            }
            return bestCol;
        };

        // Sort candidates within each column top-to-bottom
        serialCandidates.sort((a, b) => {
            const colA = getColumnIndex(a.bbox);
            const colB = getColumnIndex(b.bbox);
            if (colA !== colB) return colA - colB;
            return a.bbox.y0 - b.bbox.y0;
        });

        hrnCandidates.sort((a, b) => {
            const colA = getColumnIndex(a.bbox);
            const colB = getColumnIndex(b.bbox);
            if (colA !== colB) return colA - colB;
            return a.bbox.y0 - b.bbox.y0;
        });

        const detectedRows: ScannedVoucherRow[] = [];
        const usedHrnIndices = new Set<number>();
        const usedSerialIndices = new Set<number>();

        // Pair serials with HRNs in the same column
        for (let sIdx = 0; sIdx < serialCandidates.length; sIdx++) {
            const s = serialCandidates[sIdx];
            const sCol = getColumnIndex(s.bbox);
            const sX0 = s.bbox.x0;

            // Find next serial strictly in the SAME column to establish vertical boundary
            let nextSerialInSameCol: CandidateItem | null = null;
            for (let nextIdx = sIdx + 1; nextIdx < serialCandidates.length; nextIdx++) {
                if (getColumnIndex(serialCandidates[nextIdx].bbox) === sCol) {
                    nextSerialInSameCol = serialCandidates[nextIdx];
                    break;
                }
            }

            // Allowed vertical window for HRN relative to Serial:
            // HRN is typically below Serial (dy > 0), but allow slightly above (-40px) or up to next serial
            const maxY = nextSerialInSameCol
                ? Math.max(nextSerialInSameCol.bbox.y0 - 5, s.bbox.y0 + 50)
                : s.bbox.y0 + 260;
            const minY = s.bbox.y0 - 50;

            let bestHrnIndex = -1;
            let minScore = Infinity;

            for (let hIdx = 0; hIdx < hrnCandidates.length; hIdx++) {
                if (usedHrnIndices.has(hIdx)) continue;
                const h = hrnCandidates[hIdx];
                const hCol = getColumnIndex(h.bbox);

                // Column alignment check
                if (hCol !== sCol) continue;

                // Vertical bounds check within card
                if (h.bbox.y0 >= minY && h.bbox.y0 <= maxY) {
                    const dx = Math.abs(sX0 - h.bbox.x0);
                    const dy = h.bbox.y0 - s.bbox.y0;
                    // Strongly favor HRNs directly below the serial
                    const effectiveDy = dy < 0 ? Math.abs(dy) * 2.5 : dy;
                    const score = Math.hypot(dx * 1.2, effectiveDy);

                    if (score < minScore) {
                        minScore = score;
                        bestHrnIndex = hIdx;
                    }
                }
            }

            // Fallback: If no HRN found in tight window, check wider same-column search
            if (bestHrnIndex === -1) {
                for (let hIdx = 0; hIdx < hrnCandidates.length; hIdx++) {
                    if (usedHrnIndices.has(hIdx)) continue;
                    const h = hrnCandidates[hIdx];
                    if (getColumnIndex(h.bbox) !== sCol) continue;

                    const dy = Math.abs(h.bbox.y0 - s.bbox.y0);
                    if (dy < 320) {
                        bestHrnIndex = hIdx;
                        break;
                    }
                }
            }

            let hrnDigits = '';
            if (bestHrnIndex !== -1) {
                hrnDigits = hrnCandidates[bestHrnIndex].digits;
                usedHrnIndices.add(bestHrnIndex);
            }

            usedSerialIndices.add(sIdx);
            detectedRows.push({
                id: `v-${Date.now()}-${sIdx}-${Math.random().toString(36).slice(2, 6)}`,
                serial_number: s.digits,
                hrn: hrnDigits,
                sell_price: 25000,
                serial_status: s.digits.length === 12 ? 'valid' : 'warning',
                hrn_status: hrnDigits.length >= 14 ? 'valid' : 'warning',
            });
        }

        // Step 4: Full-Text Recovery & Smart Upgrade Pass
        // Extract 17-digit and 16-digit voucher codes present in raw text
        const fullTextHrns: string[] = [];
        const p17Matches = fullText.matchAll(/\b(\d{5})[\s\.\-]+(\d{5})[\s\.\-]+(\d{5})[\s\.\-]+(\d{2})\b/g);
        for (const m of p17Matches) {
            fullTextHrns.push(`${m[1]}${m[2]}${m[3]}${m[4]}`);
        }

        const p16Matches = fullText.matchAll(/\b(\d{4})[\s\.\-]+(\d{4})[\s\.\-]+(\d{4})[\s\.\-]+(\d{4})\b/g);
        for (const m of p16Matches) {
            fullTextHrns.push(`${m[1]}${m[2]}${m[3]}${m[4]}`);
        }

        // Match full-text codes to existing rows
        for (const ftHrn of fullTextHrns) {
            // 1. If already accurately present, skip
            if (detectedRows.some((r) => r.hrn === ftHrn)) continue;

            // 2. SMART UPGRADE: If an existing row has a partial/shortened version (e.g. 15-digit vs 17-digit),
            // replace and upgrade that row! Prevents creating duplicate orphan rows!
            const partialRow = detectedRows.find((r) => r.hrn && (ftHrn.startsWith(r.hrn) || r.hrn.startsWith(ftHrn)));
            if (partialRow) {
                partialRow.hrn = ftHrn;
                partialRow.hrn_status = ftHrn.length >= 14 ? 'valid' : 'warning';
                continue;
            }

            // 3. If an existing row has an empty HRN, fill it in
            const emptyRow = detectedRows.find((r) => !r.hrn);
            if (emptyRow) {
                emptyRow.hrn = ftHrn;
                emptyRow.hrn_status = ftHrn.length >= 14 ? 'valid' : 'warning';
                continue;
            }

            // 4. Otherwise append as orphan
            detectedRows.push({
                id: `v-recovered-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
                serial_number: '',
                hrn: ftHrn,
                sell_price: 25000,
                serial_status: 'warning',
                hrn_status: ftHrn.length >= 14 ? 'valid' : 'warning',
            });
        }

        // Step 5: Orphan HRN Recovery
        // Only append candidate HRNs that are not already covered or substrings of an existing row
        for (let hIdx = 0; hIdx < hrnCandidates.length; hIdx++) {
            if (usedHrnIndices.has(hIdx)) continue;
            const candidateHrn = hrnCandidates[hIdx].digits;

            const alreadyCovered = detectedRows.some(
                (r) => r.hrn === candidateHrn || r.hrn.startsWith(candidateHrn) || candidateHrn.startsWith(r.hrn),
            );

            if (!alreadyCovered) {
                detectedRows.push({
                    id: `v-orphan-${Date.now()}-${hIdx}-${Math.random().toString(36).slice(2, 6)}`,
                    serial_number: '',
                    hrn: candidateHrn,
                    sell_price: 25000,
                    serial_status: 'warning',
                    hrn_status: candidateHrn.length >= 14 ? 'valid' : 'warning',
                });
                usedHrnIndices.add(hIdx);
            }
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
