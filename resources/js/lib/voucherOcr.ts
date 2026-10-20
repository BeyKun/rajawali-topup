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

/**
 * Clean numeric string removing all whitespace, dashes, and common OCR noise.
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
 * Scan an image file and extract voucher serial numbers and HRN codes.
 */
export async function scanVoucherImage(
    imageSource: File | string,
    onProgress?: (p: OcrProgress) => void,
): Promise<ScannedVoucherRow[]> {
    if (onProgress) {
        onProgress({ status: 'Menyiapkan engine OCR...', progress: 0.1 });
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
        const ret = await worker.recognize(imageSource);
        await worker.terminate();

        if (onProgress) {
            onProgress({ status: 'Menganalisis nomor seri & kode...', progress: 0.95 });
        }

        const lines = (ret.data as { lines?: Array<{ text: string; bbox?: { y0: number } }> }).lines || [];
        const detectedRows: ScannedVoucherRow[] = [];

        // Strategy 1: Find lines with "No Seri" / "Seri" and look for the next line with digits
        const serialCandidates: { text: string; digits: string; y: number }[] = [];
        const hrnCandidates: { text: string; digits: string; y: number }[] = [];

        for (const line of lines) {
            const rawText = line.text.trim();
            const y = line.bbox?.y0 ?? 0;

            // Check if line contains "No Seri", "Seri", or starts with typical Telkomsel prefix (3003)
            const isSerialLine = /seri|no\.?\s*seri/i.test(rawText) || /^3003/i.test(rawText);

            if (isSerialLine) {
                // Extract digits after "seri" or entire digits
                const digits = sanitizeDigits(rawText);
                if (digits.length >= 10) {
                    serialCandidates.push({ text: rawText, digits, y });
                    continue;
                }
            }

            // Otherwise, check if this line is an HRN code line (mostly digits, typically 12-18 digits)
            const digits = sanitizeDigits(rawText);
            if (digits.length >= 12 && digits.length <= 22) {
                // Avoid matching phone numbers like 08xxx or URLs
                if (!rawText.includes('http') && !rawText.includes('tsel.me') && !rawText.includes('@')) {
                    hrnCandidates.push({ text: rawText, digits, y });
                }
            }
        }

        // Pair each serial with the closest HRN directly below it (y_hrn > y_serial)
        const usedHrnIndices = new Set<number>();

        for (const s of serialCandidates) {
            let bestHrnIndex = -1;
            let minDistance = Infinity;

            for (let i = 0; i < hrnCandidates.length; i++) {
                if (usedHrnIndices.has(i)) continue;
                const h = hrnCandidates[i];

                // Must be vertically below or close to the serial number
                const diff = h.y - s.y;
                if (diff >= -10 && diff < minDistance) {
                    minDistance = diff;
                    bestHrnIndex = i;
                }
            }

            let hrnDigits = '';
            if (bestHrnIndex !== -1) {
                hrnDigits = hrnCandidates[bestHrnIndex].digits;
                usedHrnIndices.add(bestHrnIndex);
            }

            detectedRows.push({
                id: `v-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
                serial_number: s.digits,
                hrn: hrnDigits,
                sell_price: 25000,
                serial_status: s.digits.length >= 12 ? 'valid' : 'warning',
                hrn_status: hrnDigits.length >= 14 ? 'valid' : 'warning',
            });
        }

        // Fallback: If pairing by Y yielded nothing but we found equal numbers of serial and hrn lines
        if (detectedRows.length === 0 && serialCandidates.length > 0) {
            for (let i = 0; i < serialCandidates.length; i++) {
                const s = serialCandidates[i];
                const h = hrnCandidates[i];
                detectedRows.push({
                    id: `v-${Date.now()}-${i}`,
                    serial_number: s.digits,
                    hrn: h ? h.digits : '',
                    sell_price: 25000,
                    serial_status: s.digits.length >= 12 ? 'valid' : 'warning',
                    hrn_status: h && h.digits.length >= 14 ? 'valid' : 'warning',
                });
            }
        }

        // Fallback 2: Regex across full text if line detection was fragmented
        if (detectedRows.length === 0) {
            const fullText = ret.data.text;
            const serialMatches = Array.from(fullText.matchAll(/(?:no\s*seri\s*[:\.]?|seri\s*[:\.]?)\s*([0-9\s]{12,22})/gi));
            const hrnMatches = Array.from(fullText.matchAll(/\b([0-9]{5}\s+[0-9]{5}\s+[0-9]{5}\s+[0-9]{2})\b/gi));

            const count = Math.max(serialMatches.length, hrnMatches.length);
            for (let i = 0; i < count; i++) {
                const sDigits = serialMatches[i] ? sanitizeDigits(serialMatches[i][1]) : '';
                const hDigits = hrnMatches[i] ? sanitizeDigits(hrnMatches[i][1]) : '';

                if (sDigits || hDigits) {
                    detectedRows.push({
                        id: `v-${Date.now()}-${i}`,
                        serial_number: sDigits,
                        hrn: hDigits,
                        sell_price: 25000,
                        serial_status: sDigits.length >= 12 ? 'valid' : 'warning',
                        hrn_status: hDigits.length >= 14 ? 'valid' : 'warning',
                    });
                }
            }
        }

        if (onProgress) {
            onProgress({ status: 'Selesai!', progress: 1 });
        }

        return detectedRows;
    } catch (error) {
        await worker.terminate();
        throw error;
    }
}
