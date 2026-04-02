/**
 * Dictée vocale pour le formulaire de signalement (sans Stimulus : cibles fiables sur le <form>).
 */
function bindReportSpeechForm(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const toggle = form.querySelector(".report-btn-speech");
    const textarea = form.querySelector("textarea");
    const status = form.querySelector(".report-btn-speech__status");

    if (!toggle || toggle.dataset.reportSpeechBound === "1") {
        return;
    }
    if (!textarea) {
        return;
    }

    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!Recognition) {
        toggle.hidden = true;
        return;
    }

    toggle.dataset.reportSpeechBound = "1";

    let recognition = null;
    let listening = false;

    const setStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const clearStatus = () => {
        if (status) {
            status.textContent = "";
        }
    };

    const setToggleActive = (active) => {
        toggle.classList.toggle("report-btn-speech--active", active);
        toggle.setAttribute("aria-pressed", active ? "true" : "false");
        toggle.setAttribute(
            "aria-label",
            active ? "Arrêter la dictée vocale" : "Dicter le texte à la voix",
        );
        const label = toggle.querySelector(".report-btn-speech__text");
        if (label) {
            label.textContent = active ? "Arrêter" : "Dicter";
        }
    };

    const stop = () => {
        listening = false;
        if (recognition) {
            try {
                recognition.stop();
            } catch {
                /* ignore */
            }
            recognition = null;
        }
        setToggleActive(false);
    };

    const onResult = (event) => {
        for (let i = event.resultIndex; i < event.results.length; i++) {
            if (!event.results[i].isFinal) {
                continue;
            }
            const text = event.results[i][0].transcript.trim();
            if (!text) {
                continue;
            }
            const v = textarea.value;
            const sep = v.length > 0 && !/\s$/.test(v) ? " " : "";
            textarea.value = `${v}${sep}${text}`;
            textarea.dispatchEvent(new Event("input", { bubbles: true }));
        }
    };

    const onError = (event) => {
        if (event.error === "no-speech" || event.error === "aborted") {
            return;
        }
        if (event.error === "not-allowed") {
            setStatus(
                "Accès au micro refusé. Autorisez le microphone pour ce site (icône dans la barre d’adresse).",
            );
        } else {
            setStatus(`Dictée interrompue (${event.error}). Réessayez.`);
        }
        listening = false;
        recognition = null;
        setToggleActive(false);
    };

    const onEnd = () => {
        if (!listening || !recognition) {
            return;
        }
        window.setTimeout(() => {
            if (!listening || !recognition) {
                return;
            }
            try {
                recognition.start();
            } catch {
                stop();
            }
        }, 120);
    };

    toggle.addEventListener(
        "click",
        (event) => {
            event.preventDefault();
            event.stopPropagation();
            clearStatus();

            if (listening) {
                stop();
                return;
            }

            recognition = new Recognition();
            recognition.lang = "fr-FR";
            recognition.continuous = true;
            recognition.interimResults = false;
            recognition.onresult = onResult;
            recognition.onerror = onError;
            recognition.onend = onEnd;

            try {
                recognition.start();
                listening = true;
                setToggleActive(true);
            } catch {
                setStatus(
                    "Impossible de démarrer la dictée. Réessayez ou vérifiez les permissions du navigateur.",
                );
                listening = false;
                recognition = null;
                setToggleActive(false);
            }
        },
        { passive: false },
    );
}

export function initReportSpeech() {
    const scan = () => {
        document.querySelectorAll("form.report-submission-form").forEach(bindReportSpeechForm);
    };

    scan();
    document.addEventListener("turbo:load", scan);
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", scan, { once: true });
    }
}
