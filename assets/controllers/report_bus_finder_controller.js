import { Controller } from "@hotwired/stimulus";

/**
 * Appelle l’endpoint find-bus (ligne / arrêt / direction), déverrouille la suite du formulaire.
 */
export default class extends Controller {
    static values = {
        findUrl: String,
        csrfToken: String,
        /** @type {string} présélection via ?busId= */
        prefilledBusId: { type: String, default: "" },
    };

    static targets = ["message", "identifyBlock", "identifyDoneText"];

    connect() {
        const id = (this.prefilledBusIdValue || "").trim();
        if (id) {
            this.element.classList.add("report-submission-form--bus-resolved");
            if (this.hasIdentifyBlockTarget) {
                this.identifyBlockTarget.classList.add("is-collapsed");
            }
            if (this.hasMessageTarget && this.messageTarget.textContent.trim() === "") {
                this.#setMessage(`Bus n°${id} est présélectionné (paramètre d’URL).`, "success");
            }
            return;
        }
    }

    expandIdentify() {
        if (!this.hasIdentifyBlockTarget) {
            return;
        }
        this.identifyBlockTarget.classList.remove("is-collapsed");
        const prefilled = (this.prefilledBusIdValue || "").trim();
        if (!prefilled) {
            this.element.classList.remove("report-submission-form--bus-resolved");
            this.#clearMessage();
            if (this.hasIdentifyDoneTextTarget) {
                this.identifyDoneTextTarget.textContent = "";
            }
        }
    }

    async find(event) {
        event.preventDefault();
        const form = this.element.closest("form");
        if (!form) {
            return;
        }

        const lineField = form.querySelector('[name="report_submission[lineId]"]');
        const stopField = form.querySelector('[name="report_submission[stopId]"]');
        const directionField = form.querySelector('[name="report_submission[direction]"]');
        const dateField = form.querySelector('[name="report_submission[reportDate]"]');

        if (!lineField || !stopField || !directionField || !dateField) {
            this.#setMessage("Formulaire incomplet.", "error");
            return;
        }

        const lineId = lineField.value.trim();
        const stopId = stopField.value.trim();
        const direction = directionField.value.trim();
        const reportDate = dateField.value;

        if (!lineId || !stopId || !direction) {
            this.#setMessage("Renseignez la ligne, l’arrêt et la direction.", "error");
            if (!lineId) {
                lineField.focus();
            } else if (!direction) {
                directionField.focus();
            } else {
                stopField.focus();
            }
            return;
        }

        this.#setMessage("Recherche en cours…", "info");

        try {
            const response = await fetch(this.findUrlValue, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": this.csrfTokenValue,
                },
                body: JSON.stringify({ lineId, stopId, direction, reportDate }),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.ok) {
                const err =
                    (typeof data.error === "string" && data.error) ||
                    `Échec (${response.status}). Réessayez.`;
                this.#setMessage(err, "error");
                this.element.classList.remove("report-submission-form--bus-resolved");
                if (this.hasIdentifyBlockTarget) {
                    this.identifyBlockTarget.classList.remove("is-collapsed");
                }
                return;
            }

            this.#setMessage("Bus identifié. Renseignez l’horaire et la description ci-dessous.", "success");
            this.element.classList.add("report-submission-form--bus-resolved");
            this.#collapseIdentifyFromApi(data);
        } catch (e) {
            this.#setMessage("Erreur réseau. Vérifiez votre connexion.", "error");
            this.element.classList.remove("report-submission-form--bus-resolved");
            if (this.hasIdentifyBlockTarget) {
                this.identifyBlockTarget.classList.remove("is-collapsed");
            }
        }
    }

    /**
     * @param {Record<string, unknown>} data
     */
    #collapseIdentifyFromApi(data) {
        if (!this.hasIdentifyBlockTarget || !this.hasIdentifyDoneTextTarget) {
            return;
        }
        this.identifyBlockTarget.classList.add("is-collapsed");
        const finder = /** @type {Record<string, string>} */ (data.finder || {});
        const parts = [];
        if (data.busId != null) {
            parts.push(`Bus n°${data.busId}`);
        }
        if (finder.lineId) {
            parts.push(`Ligne ${finder.lineId}`);
        }
        if (finder.direction) {
            parts.push(finder.direction);
        }
        if (finder.stopId) {
            parts.push(`Arrêt ${finder.stopId}`);
        }
        this.identifyDoneTextTarget.textContent = parts.join(" · ");
    }

    #clearMessage() {
        if (!this.hasMessageTarget) {
            return;
        }
        this.messageTarget.textContent = "";
        this.messageTarget.classList.remove("bus-finder__message--error", "bus-finder__message--success", "bus-finder__message--info");
        this.messageTarget.classList.add("is-empty");
    }

    /**
     * @param {"error"|"success"|"info"} variant
     */
    #setMessage(text, variant) {
        if (!this.hasMessageTarget) {
            return;
        }
        this.messageTarget.textContent = text;
        this.messageTarget.classList.remove("bus-finder__message--error", "bus-finder__message--success", "bus-finder__message--info");
        if (!text || text.trim() === "") {
            this.messageTarget.classList.add("is-empty");
            return;
        }
        this.messageTarget.classList.remove("is-empty");
        if (variant === "error") {
            this.messageTarget.classList.add("bus-finder__message--error");
        } else if (variant === "success") {
            this.messageTarget.classList.add("bus-finder__message--success");
        } else {
            this.messageTarget.classList.add("bus-finder__message--info");
        }
    }
}
