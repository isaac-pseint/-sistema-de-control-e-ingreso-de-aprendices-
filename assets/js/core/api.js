// core/api.js — Cliente de la API. Único módulo que hace fetch().
// ../../../ sube desde assets/js/core/ hasta la raíz del proyecto.

const API = new URL("../../../index.php", import.meta.url).href;

// ── Token CSRF sincronizado con la sesión del servidor ──────────────
let _csrfToken = "";
let _csrfPromise = null;

/** Guarda el token solo en memoria (para adjuntarlo a cada petición). */
function _setCsrfToken(token) {
    if (!token) return;
    _csrfToken = token;
}

/** Lee el token que haya devuelto la respuesta y lo almacena. */
function _captureCsrfToken(json) {
    const token = json?.data?.csrf_token;
    if (token) _setCsrfToken(token);
    return json;
}

/**
 * Obtiene el token CSRF bajo demanda. Si ya existe en memoria lo
 * resuelve inmediatamente; si no, hace GET ?action=csrfToken.
 * Deduplica llamadas simultáneas con una promesa compartida.
 */
function _asegurarToken() {
    if (_csrfToken) return Promise.resolve();
    if (_csrfPromise) return _csrfPromise;

    _csrfPromise = fetch(`${API}?action=csrfToken`, {
        headers: { "X-Requested-With": "XMLHttpRequest" }
    })
        .then(res => res.json())
        .then(json => {
            _captureCsrfToken(json);
        })
        .finally(() => {
            _csrfPromise = null;
        });

    return _csrfPromise;
}

// ─────────────────────────────────────────────────────────────────────

/**
 * Adjunta el csrf_token al body (FormData / URLSearchParams) y a las
 * cabeceras. Devuelve las opciones listas para fetch().
 */
function _prepararOpciones(opciones) {
    if (_csrfToken && opciones.body) {
        if (opciones.body instanceof FormData) {
            opciones.body.set("csrf_token", _csrfToken);
        } else if (opciones.body instanceof URLSearchParams) {
            opciones.body.set("csrf_token", _csrfToken);
        }
    }
    return {
        ...opciones,
        headers: {
            "X-Requested-With": "XMLHttpRequest",
            ...(_csrfToken ? { "X-CSRF-Token": _csrfToken } : {}),
            ...(opciones.headers || {})
        }
    };
}

export async function api(action, opciones = {}) {
    const esPOST = (opciones.method || "GET").toUpperCase() === "POST";

    // Antes de cualquier POST, asegurar que tengamos un token CSRF.
    if (esPOST) await _asegurarToken();

    const res = await fetch(
        `${API}?action=${action}`,
        _prepararOpciones(opciones)
    );

    // Sesión caducada → volver al login (excepto al intentar loguearse).
    if (res.status === 401 && action !== "login") {
        window.location.href = new URL(
            "../../../views/public/login.html",
            import.meta.url
        ).href;
        throw new Error("Sesion expirada");
    }

    const json = await res.json();
    _captureCsrfToken(json);

    // Reintento único: si el servidor rechaza el token CSRF, pedimos
    // uno fresco y reenviamos la petición una sola vez.
    if (esPOST && res.status === 403 && json.error === "Token CSRF inválido.") {
        _csrfToken = "";
        await _asegurarToken();

        // Actualizar el body con el token nuevo.
        if (opciones.body instanceof FormData) {
            opciones.body.set("csrf_token", _csrfToken);
        } else if (opciones.body instanceof URLSearchParams) {
            opciones.body.set("csrf_token", _csrfToken);
        }

        const res2 = await fetch(
            `${API}?action=${action}`,
            _prepararOpciones(opciones)
        );

        if (res2.status === 401 && action !== "login") {
            window.location.href = new URL(
                "../../../views/public/login.html",
                import.meta.url
            ).href;
            throw new Error("Sesion expirada");
        }

        const json2 = await res2.json();
        return _captureCsrfToken(json2);
    }

    return json;
}