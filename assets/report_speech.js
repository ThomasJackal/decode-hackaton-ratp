/**
 * Dictée vocale pour le formulaire de signalement (sans Stimulus : cibles fiables sur le <form>).
 * Clic géré en capture sur document pour éviter qu’un autre code ne bloque la propagation.
 */
console.log("[report-speech] module chargé");

const SPEECH_DEBUG_PREFIX = "[report-speech]";

function speechDebug(message, detail = undefined) {
    if (detail !== undefined) {
        console.log(SPEECH_DEBUG_PREFIX, message, detail);
    } else {
        console.log(SPEECH_DEBUG_PREFIX, message);
    }
}

function speechDebugWarn(message, detail = undefined) {
    if (detail !== undefined) {
        console.warn(SPEECH_DEBUG_PREFIX, message, detail);
    } else {
        console.warn(SPEECH_DEBUG_PREFIX, message);
    }
}

/**
 * @typedef {object} ReportSpeechRuntime
 * @property {HTMLButtonElement} toggle
 * @property {HTMLTextAreaElement} textarea
 * @property {HTMLElement | null} status
 * @property {SpeechRecognition | null} recognition
 * @property {boolean} listening
 */

/** @type {WeakMap<HTMLFormElement, ReportSpeechRuntime>} */
const speechRuntimeByForm = new WeakMap();

let documentCaptureInstalled = false;

function ensureDocumentCaptureListener() {
    if (documentCaptureInstalled) {
        return;
    }
    documentCaptureInstalled = true;
    speechDebug("Écoute document (capture) installée pour .report-btn-speech");
    document.addEventListener(
        "click",
        (event) => {
            const raw = event.target;
            const el =
                raw instanceof Element ? raw : raw?.parentElement instanceof Element ? raw.parentElement : null;
            const toggle = el?.closest?.(".report-btn-speech");
            if (!toggle) {
                return;
            }

            const form = toggle.closest("form");
            if (!form || !(form instanceof HTMLFormElement)) {
                speechDebug("capture clic micro: pas de <form> parent", { toggle });
                return;
            }
            if (!form.classList.contains("report-submission-form")) {
                speechDebug("capture clic micro: formulaire ignoré (pas report-submission-form)");
                return;
            }

            const rt = speechRuntimeByForm.get(form);
            if (!rt) {
                speechDebugWarn("capture clic micro: formulaire sans runtime — bindReportSpeechForm a échoué ou pas encore tourné");
                return;
            }
            if (rt.toggle !== toggle) {
                return;
            }

            speechDebug("capture: clic sur Dicter", { listening: rt.listening });

            event.preventDefault();
            event.stopPropagation();

            runSpeechToggle(rt);
        },
        true,
    );
}

/**
 * @param {ReportSpeechRuntime} rt
 */
function runSpeechToggle(rt) {
    const { toggle, textarea, status } = rt;

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
        speechDebug("stop()", { hadRecognition: Boolean(rt.recognition), wasListening: rt.listening });
        rt.listening = false;
        if (rt.recognition) {
            try {
                rt.recognition.stop();
            } catch (e) {
                speechDebugWarn("recognition.stop() a levé", e);
            }
            rt.recognition = null;
        }
        setToggleActive(false);
    };

    const onResult = (event) => {
        speechDebug("onresult", {
            resultIndex: event.resultIndex,
            resultsLength: event.results?.length,
        });
        for (let i = event.resultIndex; i < event.results.length; i++) {
            const chunk = event.results[i];
            speechDebug("  segment", {
                index: i,
                isFinal: chunk.isFinal,
                transcript: chunk[0]?.transcript ?? "",
                confidence: chunk[0]?.confidence,
            });
            if (!chunk.isFinal) {
                continue;
            }
            const text = chunk[0].transcript.trim();
            if (!text) {
                speechDebug("  segment final ignoré (transcript vide)");
                continue;
            }
            const v = textarea.value;
            const sep = v.length > 0 && !/\s$/.test(v) ? " " : "";
            textarea.value = `${v}${sep}${text}`;
            textarea.dispatchEvent(new Event("input", { bubbles: true }));
            speechDebug("texte inséré", { inserted: text, newLength: textarea.value.length });
        }
    };

    const onError = (event) => {
        speechDebug("onerror", { error: event.error, message: event.message });
        if (event.error === "no-speech" || event.error === "aborted") {
            speechDebug("(erreur ignorée côté UI)", event.error);
            return;
        }
        if (event.error === "not-allowed") {
            setStatus(
                "Accès au micro refusé. Autorisez le microphone pour ce site (icône dans la barre d’adresse).",
            );
        } else if (event.error === "network") {
            setStatus(
                "La reconnaissance vocale n’a pas pu joindre le service du navigateur (réseau ou pare-feu). Essayez hors réseau restreint (école, VPN) ou un autre navigateur / connexion.",
            );
        } else if (event.error === "service-not-allowed") {
            setStatus(
                "Le service de dictée n’est pas autorisé (navigateur, politique de l’appareil ou région). Essayez Chrome ou Edge à jour.",
            );
        } else {
            setStatus(`Dictée interrompue (${event.error}). Réessayez.`);
        }
        rt.listening = false;
        rt.recognition = null;
        setToggleActive(false);
    };

    const onEnd = () => {
        speechDebug("onend", { listening: rt.listening, hasRecognition: Boolean(rt.recognition) });
        if (!rt.listening || !rt.recognition) {
            speechDebug("onend: pas de redémarrage (listening ou recognition absent)");
            return;
        }
        window.setTimeout(() => {
            if (!rt.listening || !rt.recognition) {
                speechDebug("onend timeout: état changé, pas de restart");
                return;
            }
            try {
                speechDebug("onend: redémarrage recognition.start()");
                rt.recognition.start();
            } catch (e) {
                speechDebugWarn("onend: recognition.start() a échoué", e);
                stop();
            }
        }, 120);
    };

    clearStatus();

    if (rt.listening) {
        speechDebug("clic: arrêt demandé (était en écoute)");
        stop();
        return;
    }

    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!Recognition) {
        speechDebugWarn("SpeechRecognition indisponible au moment du clic");
        setStatus("Dictée non supportée par ce navigateur (essayez Chrome ou Edge).");
        return;
    }

    speechDebug("clic: démarrage dictée", {
        lang: "fr-FR",
        continuous: true,
        interimResults: false,
    });

    rt.recognition = new Recognition();
    rt.recognition.lang = "fr-FR";
    rt.recognition.continuous = true;
    rt.recognition.interimResults = false;
    rt.recognition.onstart = () => {
        speechDebug("onstart — écoute active");
    };
    rt.recognition.onspeechstart = () => {
        speechDebug("onspeechstart");
    };
    rt.recognition.onspeechend = () => {
        speechDebug("onspeechend");
    };
    rt.recognition.onresult = onResult;
    rt.recognition.onerror = onError;
    rt.recognition.onend = onEnd;

    try {
        rt.recognition.start();
        rt.listening = true;
        setToggleActive(true);
        speechDebug("recognition.start() OK");
    } catch (e) {
        speechDebugWarn("recognition.start() exception", e);
        setStatus(
            "Impossible de démarrer la dictée. Réessayez ou vérifiez les permissions du navigateur.",
        );
        rt.listening = false;
        rt.recognition = null;
        setToggleActive(false);
    }
}

function bindReportSpeechForm(form) {
    if (!(form instanceof HTMLFormElement)) {
        speechDebug("bindReportSpeechForm: ignoré (pas un HTMLFormElement)", form);
        return;
    }

    const toggle = form.querySelector(".report-btn-speech");
    const textarea = form.querySelector("textarea");
    const status = form.querySelector(".report-btn-speech__status");

    if (!toggle || toggle.dataset.reportSpeechBound === "1") {
        speechDebug("bindReportSpeechForm: skip toggle", {
            hasToggle: Boolean(toggle),
            alreadyBound: toggle?.dataset.reportSpeechBound === "1",
        });
        return;
    }
    if (!textarea) {
        speechDebug("bindReportSpeechForm: pas de textarea dans le form");
        return;
    }

    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!Recognition) {
        speechDebugWarn(
            "SpeechRecognition indisponible (essayez Chrome, Edge ou Safari)",
            { userAgent: typeof navigator !== "undefined" ? navigator.userAgent : "n/a" },
        );
        toggle.hidden = true;
        return;
    }

    speechDebug("Formulaire lié à la dictée", {
        hasStatus: Boolean(status),
        recognitionCtor: Recognition.name || "SpeechRecognition",
    });

    /** @type {ReportSpeechRuntime} */
    const rt = {
        toggle,
        textarea,
        status,
        recognition: null,
        listening: false,
    };
    speechRuntimeByForm.set(form, rt);
    toggle.dataset.reportSpeechBound = "1";
    ensureDocumentCaptureListener();
}

export function initReportSpeech() {
    const scan = () => {
        const forms = document.querySelectorAll("form.report-submission-form");
        speechDebug("scan()", { formCount: forms.length });
        forms.forEach(bindReportSpeechForm);
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", scan, { once: true });
    } else {
        scan();
    }

    document.addEventListener("turbo:load", scan);
    document.addEventListener("turbo:render", scan);

    let scanDebounceTimer = null;
    const scheduleScan = () => {
        window.clearTimeout(scanDebounceTimer);
        scanDebounceTimer = window.setTimeout(scan, 150);
    };

    const observer = new MutationObserver(() => {
        scheduleScan();
    });
    if (document.body) {
        observer.observe(document.body, { childList: true, subtree: true });
    } else {
        document.addEventListener("DOMContentLoaded", () => {
            if (document.body) {
                observer.observe(document.body, { childList: true, subtree: true });
            }
        }, { once: true });
    }
}

initReportSpeech();
