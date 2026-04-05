import { Controller } from "@hotwired/stimulus";

/**
 * Combobox (champ + liste) — suggestions transit via /report/new/transit-suggest.
 */
export default class extends Controller {
    static targets = ["input", "menu"];

    static values = {
        suggestUrl: String,
        field: String,
        lineInputSelector: { type: String, default: "" },
    };

    /** @type {{ value: string, label: string }[]} */
    #items = [];
    /** @type {ReturnType<typeof setTimeout> | null} */
    #fetchTimer = null;
    /** @type {boolean} */
    #menuOpen = false;
    /** @type {boolean} */
    #loading = false;
    /** @type {boolean} */
    #directionBlocked = false;
    /** @type {number} */
    #activeIndex = -1;

    /** @type {HTMLInputElement | null} */
    #lineInput = null;

    /** @type {(e: MouseEvent) => void} */
    #boundDocClick;

    connect() {
        if (!this.hasInputTarget || !this.hasMenuTarget) {
            return;
        }

        this.#boundDocClick = this.#onDocumentClick.bind(this);
        document.addEventListener("click", this.#boundDocClick, true);

        this.inputTarget.addEventListener("blur", this.#onBlur);

        if (this.fieldValue === "direction") {
            const sel = (this.lineInputSelectorValue || "").trim();
            this.#lineInput = sel ? /** @type {HTMLInputElement | null} */ (document.querySelector(sel)) : null;
            if (this.#lineInput) {
                this.#lineInput.addEventListener("input", this.#onLineChanged);
            }
        }

        this.inputTarget.setAttribute("aria-expanded", "false");
        this.inputTarget.setAttribute("aria-autocomplete", "list");
        if (!this.inputTarget.getAttribute("aria-controls") && this.menuTarget.id) {
            this.inputTarget.setAttribute("aria-controls", this.menuTarget.id);
        }

        this.menuTarget.hidden = true;
    }

    disconnect() {
        document.removeEventListener("click", this.#boundDocClick, true);
        if (this.hasInputTarget) {
            this.inputTarget.removeEventListener("blur", this.#onBlur);
        }
        if (this.#lineInput) {
            this.#lineInput.removeEventListener("input", this.#onLineChanged);
        }
        if (this.#fetchTimer !== null) {
            clearTimeout(this.#fetchTimer);
        }
        this.#setComboboxOpen(false);
    }

    /** Ouvre la liste au focus. */
    onFocus() {
        this.#openAndScheduleFetch();
    }

    /** Saisie : debounce vers l’API. */
    onInput() {
        if (this.#menuOpen) {
            this.#activeIndex = -1;
        }
        this.#scheduleFetch();
    }

    /** Maintient le focus sur le champ quand on clique dans la liste (évite blur avant click). */
    onMenuMouseDown(event) {
        event.preventDefault();
    }

    /**
     * @param {KeyboardEvent} event
     */
    onKeydown(event) {
        if (!this.hasInputTarget || !this.hasMenuTarget) {
            return;
        }

        if (event.key === "Escape") {
            if (this.#menuOpen) {
                event.preventDefault();
                this.#closeMenu();
            }
            return;
        }

        if (!this.#menuOpen && (event.key === "ArrowDown" || event.key === "ArrowUp")) {
            event.preventDefault();
            this.#setComboboxOpen(true);
            void this.#fetchAndShow();
            return;
        }

        if (!this.#menuOpen) {
            return;
        }

        const n = this.#items.length;
        if (n === 0) {
            return;
        }

        if (event.key === "ArrowDown") {
            event.preventDefault();
            this.#activeIndex = Math.min(n - 1, this.#activeIndex + 1);
            this.#highlightActive();
        } else if (event.key === "ArrowUp") {
            event.preventDefault();
            this.#activeIndex = Math.max(0, this.#activeIndex === -1 ? n - 1 : this.#activeIndex - 1);
            this.#highlightActive();
        } else if (event.key === "Enter") {
            if (this.#activeIndex >= 0 && this.#activeIndex < n) {
                event.preventDefault();
                this.#selectItem(this.#items[this.#activeIndex]);
            } else if (n === 1) {
                event.preventDefault();
                this.#selectItem(this.#items[0]);
            }
        }
    }

    #onLineChanged = () => {
        if (this.fieldValue !== "direction") {
            return;
        }
        this.#activeIndex = -1;
        this.#scheduleFetch();
    };

    #onBlur = () => {
        requestAnimationFrame(() => {
            if (!this.element.contains(document.activeElement)) {
                this.#closeMenu();
            }
        });
    };

    /** @param {MouseEvent} event */
    #onDocumentClick(event) {
        if (!(event.target instanceof Node)) {
            return;
        }
        if (!this.element.contains(event.target)) {
            this.#closeMenu();
        }
    }

    #openAndScheduleFetch() {
        this.#setComboboxOpen(true);
        this.#scheduleFetch();
    }

    #scheduleFetch() {
        if (this.#fetchTimer !== null) {
            clearTimeout(this.#fetchTimer);
        }
        this.#fetchTimer = window.setTimeout(() => {
            this.#fetchTimer = null;
            void this.#fetchAndShow();
        }, 180);
    }

    async #fetchAndShow() {
        if (!this.hasInputTarget || !this.hasMenuTarget) {
            return;
        }

        const url = new URL(this.suggestUrlValue, window.location.origin);
        url.searchParams.set("field", this.fieldValue);
        url.searchParams.set("q", this.inputTarget.value.trim());

        this.#directionBlocked = false;
        if (this.fieldValue === "direction") {
            const sel = (this.lineInputSelectorValue || "").trim();
            const lineEl = sel ? document.querySelector(sel) : null;
            const lineVal = lineEl && "value" in lineEl ? String(/** @type {HTMLInputElement} */ (lineEl).value).trim() : "";
            if (!lineVal) {
                this.#directionBlocked = true;
                this.#items = [];
                this.#loading = false;
                this.#renderMenu();
                if (!this.#menuOpen && document.activeElement === this.inputTarget) {
                    this.#setComboboxOpen(true);
                }
                return;
            }
            url.searchParams.set("line", lineVal);
        }

        this.#loading = true;
        this.#renderMenu();

        try {
            const res = await fetch(url.toString(), { headers: { Accept: "application/json" } });
            const data = await res.json().catch(() => ({}));
            const raw = Array.isArray(data.suggestions) ? data.suggestions : [];
            this.#items = raw.map((row) => ({
                value: typeof row.value === "string" ? row.value : "",
                label: typeof row.label === "string" ? row.label : "",
            }));
        } catch {
            this.#items = [];
        } finally {
            this.#loading = false;
            this.#renderMenu();
        }
    }

    #renderMenu() {
        if (!this.hasMenuTarget) {
            return;
        }

        const menu = this.menuTarget;
        menu.innerHTML = "";

        if (this.#directionBlocked) {
            this.#appendStatus(menu, "Sélectionnez d’abord une ligne.");
            return;
        }

        if (this.#loading) {
            this.#appendStatus(menu, "Chargement…");
            return;
        }

        if (this.#items.length === 0) {
            this.#appendStatus(menu, "Aucun résultat");
            return;
        }

        this.#items.forEach((item, index) => {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.setAttribute("role", "option");
            btn.className = "report-combobox__option";
            btn.id = `${menu.id}_opt_${index}`;
            btn.dataset.index = String(index);

            const main = document.createElement("span");
            main.className = "report-combobox__option-value";
            main.textContent = item.value || "—";

            btn.appendChild(main);
            if (item.label && item.label !== item.value) {
                const sub = document.createElement("span");
                sub.className = "report-combobox__option-label";
                sub.textContent = item.label;
                btn.appendChild(sub);
            }

            btn.addEventListener("click", () => this.#selectItem(item));
            menu.appendChild(btn);
        });

        this.#highlightActive();
    }

    /**
     * @param {HTMLElement} menu
     * @param {string} text
     */
    #appendStatus(menu, text) {
        const el = document.createElement("div");
        el.className = "report-combobox__status";
        el.setAttribute("role", "presentation");
        el.textContent = text;
        menu.appendChild(el);
    }

    #highlightActive() {
        if (!this.hasMenuTarget) {
            return;
        }
        const buttons = this.menuTarget.querySelectorAll('button[role="option"]');
        buttons.forEach((btn, i) => {
            const on = i === this.#activeIndex;
            btn.classList.toggle("is-active", on);
            btn.setAttribute("aria-selected", on ? "true" : "false");
            if (on) {
                btn.scrollIntoView({ block: "nearest" });
            }
        });

        const activeId = this.#activeIndex >= 0 ? `${this.menuTarget.id}_opt_${this.#activeIndex}` : "";
        if (activeId && this.hasInputTarget) {
            this.inputTarget.setAttribute("aria-activedescendant", activeId);
        } else if (this.hasInputTarget) {
            this.inputTarget.removeAttribute("aria-activedescendant");
        }
    }

    /**
     * @param {{ value: string, label: string }} item
     */
    #selectItem(item) {
        if (!this.hasInputTarget) {
            return;
        }
        this.inputTarget.value = item.value;
        this.inputTarget.dispatchEvent(new Event("input", { bubbles: true }));
        this.#closeMenu();
        this.inputTarget.focus();
    }

    #closeMenu() {
        this.#setComboboxOpen(false);
        this.#activeIndex = -1;
    }

    /** @param {boolean} open */
    #setComboboxOpen(open) {
        this.#menuOpen = open;
        if (!this.hasMenuTarget || !this.hasInputTarget) {
            return;
        }
        this.menuTarget.hidden = !open;
        this.inputTarget.setAttribute("aria-expanded", open ? "true" : "false");
        const root = this.element.querySelector(".report-combobox");
        root?.classList.toggle("report-combobox--open", open);
        if (!open) {
            this.inputTarget.removeAttribute("aria-activedescendant");
        }
    }
}
