// core/modal.js — Modal de confirmación genérico.
// Reemplaza el patrón de confirmación que se repetía en usuarios, fichas y programas.
// La responsabilidad se divide: este módulo solo abre/cierra modales y ejecuta la
// acción; el dominio (feature) define overlay, botones, acción de API y refresco.

import { showToast } from "./ui.js";

export function crearModalConfirmacion(opciones = {}) {
    const {
        overlay,                  // id o [ids candidatos] del .modal-overlay
        detalle = [],             // [selectores candidatos] del texto de detalle
        btnCerrar = [],           // [selectores candidatos] botón cerrar (X)
        btnCancelar = [],         // [selectores candidatos] botón cancelar
        btnConfirmar = [],        // [selectores candidatos] botón confirmar
        accion,                   // (id) => Promise — la llamada a la API (FormData ya listo)
        mensajeOk = "Operación exitosa.",
        mensajeError = "No se pudo completar la operación.",
        mensajeConexion = "Error de conexión con el servidor.",
        despuesDeConfirmar = null // () => refrescar el listado tras confirmar con ok
    } = opciones;

    const list = (x) => (Array.isArray(x) ? x : [x]);

    let idPendiente = null;
    let conectado = false;

    const overlayEl = list(overlay)
        .map(sel => document.getElementById(sel))
        .find(el => el !== null) || null;

    const buscar = (seleccionadores) => {
        for (const s of list(seleccionadores)) {
            if (!s) continue;
            const sel = String(s).trim();
            if (sel.startsWith("#")) {
                const el = document.getElementById(sel.slice(1));
                if (el) return el;
            } else if (sel.startsWith(".")) {
                if (overlayEl) {
                    const el = overlayEl.querySelector(sel);
                    if (el) return el;
                }
            } else {
                const el = document.getElementById(sel);
                if (el) return el;
            }
        }
        return null;
    };

    const detalleEl = buscar(detalle);
    const btnCerrarEl = buscar(btnCerrar);
    const btnCancelarEl = buscar(btnCancelar);
    const btnConfirmarEl = buscar(btnConfirmar);

    function abrir(id, texto = "") {
        idPendiente = id;
        if (detalleEl) detalleEl.textContent = texto;
        if (overlayEl) overlayEl.classList.add("abierto");
    }

    function cerrar() {
        idPendiente = null;
        if (overlayEl) overlayEl.classList.remove("abierto");
    }

    function conectarse() {
        if (!overlayEl || conectado) return;
        conectado = true;

        // Cerrar al hacer clic fuera del modal.
        overlayEl.addEventListener("click", (e) => {
            if (e.target === overlayEl) cerrar();
        });

        btnCerrarEl?.addEventListener("click", cerrar);
        btnCancelarEl?.addEventListener("click", cerrar);
        btnConfirmarEl?.addEventListener("click", () => {
            if (idPendiente === null) return;

            const id = idPendiente;

            Promise.resolve(accion ? accion(id) : null)
                .then(data => {
                    cerrar();
                    if (data && data.ok) {
                        showToast("success", data.mensaje || mensajeOk);
                        if (despuesDeConfirmar) despuesDeConfirmar();
                    } else {
                        showToast("danger", (data && data.error) || mensajeError);
                    }
                })
                .catch(() => {
                    cerrar();
                    showToast("danger", mensajeConexion);
                });
        });
    }

    return { abrir, cerrar, conectarse };
}