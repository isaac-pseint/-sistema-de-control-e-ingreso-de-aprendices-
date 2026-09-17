// features/sidebar.js — Barra de navegación lateral por rol.
// Conoce roles (por eso vive en features/, no en core/). Pinta marca, usuario,
// enlaces de esa navegación y el cierre de sesión en el <aside> de la vista
// autenticada; aparece en todas las secciones (listado, crear, editar, etc.)
// y en móvil opera como panel off-canvas con botón hamburguesa y overlay.

import { cerrarSesion } from "./auth.js";
import { esc } from "../core/ui.js";

// Enlaces por rol, relativos a la carpeta raíz del rol (views/<rol>/).
// "seccion" agrupa los enlaces de una misma parte para que la vista quede
// activa también dentro de sus sub-páginas (p. ej. "Usuarios" en
// usuarios/editar.html o usuarios/crear.html).
const ENLACES_POR_ROL = {
    "Administrador": [
        { texto: "Inicio", ruta: "dashboard.html", seccion: "dashboard" },
        { texto: "Usuarios", ruta: "usuarios/listado.html", seccion: "usuarios" },
        { texto: "Fichas", ruta: "fichas/listado.html", seccion: "fichas" },
        { texto: "Programas", ruta: "programas/listado.html", seccion: "programas" }
    ],
    "Aprendiz": [
        { texto: "Inicio", ruta: "dashboard.html", seccion: "dashboard" },
        { texto: "Asistencias", ruta: "asistencias/listado.html", seccion: "asistencias" }
    ],
    "Instructor": [
        { texto: "Inicio", ruta: "dashboard.html", seccion: "dashboard" }
    ]
};

const CARPETA_ROL = {
    "Administrador": "admin",
    "Aprendiz": "aprendiz",
    "Instructor": "instructor"
};

// Título del header según la sección activa de la vista.
const TITULO_POR_SECCION = {
    dashboard: "Dashboard",
    usuarios: "Gestión de Usuarios",
    fichas: "Gestión de Fichas",
    programas: "Gestión de Programas",
    asistencias: "Gestión de Asistencias"
};

// Devuelve el prefijo "../" que sube desde la carpeta de la vista actual hasta
// la raíz del rol. Ej. .../admin/usuarios/listado.html → "../".
function prefijoRelativo(rol) {
    const carpeta = CARPETA_ROL[rol];
    if (!carpeta) return "";

    const partes = location.pathname.split("/").filter(Boolean);
    const indiceRol = partes.indexOf(carpeta);
    if (indiceRol < 0) return "";

    const pisos = partes.length - 1 - indiceRol - 1;
    return pisos > 0 ? "../".repeat(pisos) : "";
}

// Sección activa de la vista actual: la subcarpeta bajo la raíz del rol, o el
// nombre del archivo si está directamente en la raíz.
// Ej. .../admin/usuarios/editar.html → "usuarios", .../admin/dashboard.html → "dashboard".
function seccionDeRuta(rol) {
    const carpeta = CARPETA_ROL[rol];
    const partes = location.pathname.split("/").filter(Boolean);
    const indiceRol = carpeta ? partes.indexOf(carpeta) : -1;
    const resto = indiceRol >= 0 ? partes.slice(indiceRol + 1) : [];
    const primero = resto[0] || "";
    return primero.endsWith(".html") ? primero.slice(0, -5) : primero;
}

export function montarSidebar(rol, usuario = null) {
    const sidebar = document.getElementById("sidebar");
    if (!sidebar) return;

    const prefijo = prefijoRelativo(rol);
    const seccion = seccionDeRuta(rol);
    const enlaces = (ENLACES_POR_ROL[rol] || [])
        .map(e => {
            const href = prefijo + e.ruta;
            const activo = e.seccion === seccion;
            return `<a href="${href}"${activo ? ' class="activo"' : ""}>${esc(e.texto)}</a>`;
        })
        .join("");

    const nombre = usuario ? usuario.nombre || usuario.email || "" : "";

    // El header muestra el título de la sección en la que se está.
    const titulo = document.getElementById("tituloSeccion");
    if (titulo) titulo.textContent = TITULO_POR_SECCION[seccion] || "";

    sidebar.innerHTML = `
        <div class="sidebar-marca">Sistema de Ingreso</div>
        <div class="sidebar-usuario">
            <span class="sidebar-usuario-nombre">${esc(nombre)}</span>
            <span class="sidebar-usuario-rol">${esc(rol)}</span>
        </div>
        <nav class="sidebar-nav">${enlaces}</nav>
        <div class="sidebar-pie">
            <button type="button" id="btnLogout" class="sidebar-logout">Cerrar Sesión</button>
        </div>
    `;

    document.getElementById("btnLogout")?.addEventListener("click", cerrarSesion);

    const overlay = document.getElementById("sidebarOverlay");
    const cerrar = () => {
        sidebar.classList.remove("abierto");
        if (overlay) overlay.hidden = true;
    };

    document.getElementById("btnMenu")?.addEventListener("click", () => {
        sidebar.classList.add("abierto");
        if (overlay) overlay.hidden = false;
    });
    overlay?.addEventListener("click", cerrar);
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") cerrar();
    });

    // En móvil el panel se desliza debajo del header; usa su altura real.
    const aplicarAltoHeader = () => {
        if (window.matchMedia("(max-width: 767px)").matches) {
            sidebar.style.top = (document.querySelector(".app-header")?.offsetHeight || 60) + "px";
        } else {
            sidebar.style.top = "";
        }
    };
    aplicarAltoHeader();

    // Si la ventana se ensancha con el panel abierto, se cierra y limpia el estado.
    window.matchMedia("(min-width: 768px)").addEventListener("change", (e) => {
        if (e.matches) {
            cerrar();
            aplicarAltoHeader();
        }
    });
}