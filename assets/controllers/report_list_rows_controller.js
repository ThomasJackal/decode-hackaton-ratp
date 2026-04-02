import { Controller } from "@hotwired/stimulus";

/**
 * Lignes du tableau des signalements : navigation vers la fiche (déléguation d’événements, Turbo-safe).
 */
export default class extends Controller {
    connect() {
        this._onClick = this.#onClick.bind(this);
        this._onKeydown = this.#onKeydown.bind(this);
        this.element.addEventListener("click", this._onClick);
        this.element.addEventListener("keydown", this._onKeydown);
    }

    disconnect() {
        this.element.removeEventListener("click", this._onClick);
        this.element.removeEventListener("keydown", this._onKeydown);
    }

    #onClick(event) {
        const row = event.target.closest("tr.report-list-row[data-report-href]");
        if (!row || !this.element.contains(row)) {
            return;
        }
        const url = row.getAttribute("data-report-href");
        if (url) {
            window.location.assign(url);
        }
    }

    #onKeydown(event) {
        if (event.key !== "Enter" && event.key !== " ") {
            return;
        }
        const row = event.target.closest("tr.report-list-row[data-report-href]");
        if (!row || !this.element.contains(row)) {
            return;
        }
        const url = row.getAttribute("data-report-href");
        if (url) {
            event.preventDefault();
            window.location.assign(url);
        }
    }
}
