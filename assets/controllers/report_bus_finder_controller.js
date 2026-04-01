import { Controller } from "@hotwired/stimulus";

/**
 * Appelle l’endpoint find-bus (ligne / arrêt / direction), puis ferme le panneau si succès.
 */
export default class extends Controller {
    static values = {
        findUrl: String,
        csrfToken: String,
        /** @type {string} présélection via ?busId= */
        prefilledBusId: { type: String, default: "" },
    };

    static targets = ["details", "message"];

    connect() {
        const id = (this.prefilledBusIdValue || "").trim();
        if (!id) {
            return;
        }
        if (this.hasDetailsTarget) {
            this.detailsTarget.open = false;
            this.detailsTarget.classList.add("bus-finder--resolved");
        }
        if (this.hasMessageTarget && this.messageTarget.textContent.trim() === "") {
            this.#setMessage(`Bus n°${id} est présélectionné (paramètre d’URL).`, false);
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
        const dateField = form.querySelector('[name="report_submission[ReportDate]"]');

        if (!lineField || !stopField || !directionField || !dateField) {
            this.#setMessage("Formulaire incomplet.", true);
            return;
        }

        const lineId = lineField.value.trim();
        const stopId = stopField.value.trim();
        const direction = directionField.value.trim();
        const reportDate = dateField.value;

        if (!lineId || !stopId || !direction) {
            this.#setMessage("Renseignez la ligne, l’arrêt et la direction.", true);
            if (!lineId) {
                lineField.focus();
            } else if (!stopId) {
                stopField.focus();
            } else {
                directionField.focus();
            }
            return;
        }

        this.#setMessage("Recherche en cours…", false);

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
                this.#setMessage(err, true);
                return;
            }

            this.#setMessage("Bus identifié. Vous pouvez rédiger votre retour ci-dessous.", false);
            if (this.hasDetailsTarget) {
                this.detailsTarget.open = false;
                this.detailsTarget.classList.add("bus-finder--resolved");
            }
        } catch (e) {
            this.#setMessage("Erreur réseau. Vérifiez votre connexion.", true);
        }
    }

    #setMessage(text, isError) {
        if (!this.hasMessageTarget) {
            return;
        }
        this.messageTarget.textContent = text;
        this.messageTarget.classList.toggle("bus-finder__message--error", isError);
        this.messageTarget.classList.toggle("bus-finder__message--success", !isError);
    }
}
