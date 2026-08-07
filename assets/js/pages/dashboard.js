import { cerrarSesion, comprobarSesion } from "../auth.js";

// En los módulos ES (type="module") el código se ejecuta de forma diferida,
// por lo que no hace falta usar DOMContentLoaded.

comprobarSesion();

const btnLogout = document.getElementById("btnLogout");
if (btnLogout) {
    btnLogout.addEventListener("click", cerrarSesion);
}
