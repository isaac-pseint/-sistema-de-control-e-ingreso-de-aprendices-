// api.js — Cliente de la API. Único módulo que hace fetch().
// ../../ sube desde assets/js/ hasta la raíz del proyecto.

const API = new URL("../../index.php", import.meta.url).href;

export function api(action, opciones = {}) {
    return fetch(`${API}?action=${action}`, {
        ...opciones,
        headers: {
            "X-Requested-With": "XMLHttpRequest",
            ...(opciones.headers || {})
        }
    }).then(res => {
        // Sesión caducada → volver al login.
        if (res.status === 401) {
            window.location.href = new URL("../../views/login.html", import.meta.url).href;
            throw new Error("Sesion expirada");
        }
        return res.json();
    });
}
