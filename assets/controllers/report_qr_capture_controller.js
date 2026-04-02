import { Controller } from "@hotwired/stimulus";

/**
 * Photo / fichier image → lecture QR (caméra mobile via capture="environment").
 */
export default class extends Controller {
    static values = {
        /** URL de base de la page signalement (sans query), ex. /new */
        reportNewUrl: String,
    };

    static targets = ["feedback", "cameraInput", "galleryInput"];

    openCamera(event) {
        event?.preventDefault();
        if (this.hasCameraInputTarget) {
            this.cameraInputTarget.click();
        }
    }

    openGallery(event) {
        event?.preventDefault();
        if (this.hasGalleryInputTarget) {
            this.galleryInputTarget.click();
        }
    }

    async onCameraChange(event) {
        await this.#handleFile(event.target.files?.[0]);
        event.target.value = "";
    }

    async onGalleryChange(event) {
        await this.#handleFile(event.target.files?.[0]);
        event.target.value = "";
    }

    async #handleFile(file) {
        if (!file || !file.type.startsWith("image/")) {
            return;
        }

        if (!this.hasFeedbackTarget) {
            return;
        }

        this.feedbackTarget.textContent = "Analyse de l’image…";
        this.feedbackTarget.classList.remove("is-error", "is-success");

        try {
            const bitmap = await this.#fileToImageBitmap(file);
            let text = await this.#decodeWithBarcodeDetector(bitmap);
            if (!text) {
                text = await this.#decodeWithJsQR(bitmap);
            }
            if (!text) {
                this.#setFeedback("Aucun QR code lisible. Réessayez avec plus de lumière ou plus près.", true);
                return;
            }

            const busId = this.#extractBusId(text);
            if (!busId) {
                this.#setFeedback("QR lu, mais aucun identifiant de bus trouvé (attendu : busId dans l’URL ou un numéro).", true);
                return;
            }

            this.#setFeedback("Bus trouvé — chargement…", false);
            const url = new URL(this.reportNewUrlValue, window.location.href);
            url.searchParams.set("busId", busId);
            window.location.assign(url.toString());
        } catch (e) {
            this.#setFeedback("Impossible de lire l’image. Réessayez.", true);
        }
    }

    #setFeedback(message, isError) {
        this.feedbackTarget.textContent = message;
        this.feedbackTarget.classList.toggle("is-error", isError);
        this.feedbackTarget.classList.toggle("is-success", !isError);
    }

    #fileToImageBitmap(file) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                URL.revokeObjectURL(url);
                const maxEdge = 1200;
                let w = img.naturalWidth;
                let h = img.naturalHeight;
                if (w > maxEdge || h > maxEdge) {
                    const r = Math.min(maxEdge / w, maxEdge / h);
                    w = Math.round(w * r);
                    h = Math.round(h * r);
                }
                const canvas = document.createElement("canvas");
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext("2d", { willReadFrequently: true });
                ctx.drawImage(img, 0, 0, w, h);
                resolve(canvas);
            };
            img.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error("image load"));
            };
            img.src = url;
        });
    }

    async #decodeWithBarcodeDetector(canvas) {
        if (typeof BarcodeDetector === "undefined") {
            return null;
        }
        try {
            const detector = new BarcodeDetector({ formats: ["qr_code"] });
            const results = await detector.detect(canvas);
            if (results.length > 0 && results[0].rawValue) {
                return results[0].rawValue;
            }
        } catch {
            /* ignore */
        }
        return null;
    }

    async #decodeWithJsQR(canvas) {
        const { default: jsQR } = await import("jsqr");
        const ctx = canvas.getContext("2d", { willReadFrequently: true });
        const { width, height } = canvas;
        const imageData = ctx.getImageData(0, 0, width, height);
        const result = jsQR(imageData.data, width, height, { inversionAttempts: "attemptBoth" });
        return result?.data ?? null;
    }

    #extractBusId(raw) {
        const t = raw.trim();
        try {
            const u = new URL(t, window.location.origin);
            const id =
                u.searchParams.get("busId") ||
                u.searchParams.get("bus_id") ||
                u.searchParams.get("id");
            if (id && /^\d+$/.test(String(id))) {
                return String(id);
            }
        } catch {
            /* not a full URL */
        }
        const m = t.match(/(?:busId|bus_id)[=:]\s*(\d+)/i);
        if (m) {
            return m[1];
        }
        if (/^\d{1,9}$/.test(t)) {
            return t;
        }
        return null;
    }
}
