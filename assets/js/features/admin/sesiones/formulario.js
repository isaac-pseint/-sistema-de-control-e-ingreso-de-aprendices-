import { api } from "../../../core/api.js";
import { showToast, esc } from "../../../core/ui.js";
import { conectarFormulario } from "../../../core/forms.js";

let catalogo = { fichas: [], competencias: [] };

function llenarSelect(select, opciones, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>` +
        opciones.map(o => `<option value="${o.id}">${esc(o.label)}</option>`).join("");
}

function competenciasDelPrograma(programaId) {
    return catalogo.competencias
        .filter(c => String(c.Programa_id) === String(programaId))
        .map(c => ({
            id: c.id,
            label: c.nombre,
            instructor: c.instructor || "—",
            Instructor_id: c.Instructor_id
        }));
}

function programaDeFicha(fichaId) {
    const f = catalogo.fichas.find(x => String(x.id) === String(fichaId));
    return f ? f.Programa_id : null;
}

function mostrarInstructorCompetencia() {
    const compId = document.getElementById("competencia_id")?.value;
    const el = document.getElementById("instructorSesionTexto");
    if (!el) return;
    const comp = catalogo.competencias.find(c => String(c.id) === String(compId));
    el.textContent = comp?.instructor ? comp.instructor : "Seleccione una competencia";
}

function actualizarCompetencias() {
    const fichaId = document.getElementById("ficha_id")?.value;
    const selectComp = document.getElementById("competencia_id");
    const programaId = programaDeFicha(fichaId);
    if (!programaId) {
        llenarSelect(selectComp, [], "Seleccione ficha primero...");
        mostrarInstructorCompetencia();
        return;
    }
    llenarSelect(selectComp, competenciasDelPrograma(programaId), "Seleccione competencia...");
    mostrarInstructorCompetencia();
}

export function cargarCatalogoSesion() {
    return api("datosFormularioSesion").then(data => {
        if (!data.ok) {
            showToast("danger", data.error || "No se pudo cargar el formulario.");
            return null;
        }
        catalogo.fichas = (data.data.fichas || []).map(f => ({
            id: f.id,
            codigo: f.codigo,
            Programa_id: f.Programa_id,
            label: String(f.codigo)
        }));
        catalogo.competencias = data.data.competencias || [];

        const selectFicha = document.getElementById("ficha_id");
        llenarSelect(selectFicha, catalogo.fichas.map(f => ({ id: f.id, label: f.label })), "Seleccione ficha...");
        selectFicha?.addEventListener("change", actualizarCompetencias);
        document.getElementById("competencia_id")?.addEventListener("change", mostrarInstructorCompetencia);
        return data;
    });
}

export function conectarFormularioCrearSesion() {
    conectarFormulario("formCrearSesion", "crearSesion", {
        textoEnviando: "Guardando...",
        textoRestaurar: "Guardar sesión"
    });
}

export function cargarSesionPorId(id) {
    if (!id) {
        showToast("danger", "ID de sesión no proporcionado.");
        return Promise.resolve(null);
    }
    return api(`obtenerSesion&id=${id}`).then(data => {
        if (!data.ok || !data.data?.sesion) {
            showToast("danger", data.error || "Sesión no encontrada.");
            return null;
        }
        return {
            sesion: data.data.sesion,
            permisos: data.data.permisos || {}
        };
    });
}

function bloquearCampo(el, bloquear) {
    if (!el) return;
    if (el.tagName === "SELECT") {
        el.disabled = bloquear;
        return;
    }
    el.disabled = bloquear;
    if (bloquear) {
        el.setAttribute("readonly", "readonly");
    } else {
        el.removeAttribute("readonly");
    }
}

export async function prellenarFormularioSesion(sesion, permisos = {}) {
    await cargarCatalogoSesion();
    document.getElementById("id").value = sesion.id;
    document.getElementById("ficha_id").value = sesion.Ficha_id;
    actualizarCompetencias();
    document.getElementById("competencia_id").value = sesion.Competencia_id;
    mostrarInstructorCompetencia();
    document.getElementById("fecha").value = sesion.fecha;
    document.getElementById("hora_inicio").value = String(sesion.hora_inicio).slice(0, 5);
    document.getElementById("hora_fin").value = String(sesion.hora_fin).slice(0, 5);
    const estado = document.getElementById("estado");
    if (estado) estado.value = sesion.estado || "Activo";

    const prog = Boolean(permisos.editar_programacion);
    bloquearCampo(document.getElementById("ficha_id"), !prog);
    bloquearCampo(document.getElementById("competencia_id"), !prog);
    bloquearCampo(document.getElementById("fecha"), !prog);
    bloquearCampo(document.getElementById("hora_inicio"), !prog);
    bloquearCampo(document.getElementById("hora_fin"), !prog);

    if (estado) {
        estado.disabled = !permisos.editar_estado;
        const optActivo = estado.querySelector('option[value="Activo"]');
        if (optActivo) {
            const puedeElegirActivo = permisos.antes_de_inicio
                || (sesion.estado === "Cancelado" && permisos.antes_de_fin);
            optActivo.disabled = !puedeElegirActivo && sesion.estado !== "Activo";
        }
    }

    const aviso = document.getElementById("avisoSesionProgramacion");
    const btnGuardar = document.querySelector("#formEditarSesion button[type=submit]");
    if (aviso) {
        if (!prog && permisos.editar_estado) {
            if (permisos.antes_de_fin) {
                aviso.textContent = "La sesión ya inició: solo puede cambiar el estado (ficha, competencia, fecha y horario bloqueados).";
            } else {
                aviso.textContent = "La sesión ya terminó: solo puede cambiar el estado (ficha, competencia, fecha y horario bloqueados).";
            }
            aviso.hidden = false;
        } else if (!permisos.guardar_admin) {
            aviso.textContent = "Esta sesión no admite cambios desde aquí.";
            aviso.hidden = false;
        } else {
            aviso.textContent = "";
            aviso.hidden = true;
        }
    }
    if (btnGuardar) btnGuardar.disabled = !permisos.guardar_admin;
}

export function conectarFormularioEditarSesion() {
    conectarFormulario("formEditarSesion", "actualizarSesion", {
        textoEnviando: "Guardando...",
        textoRestaurar: "Guardar cambios"
    });
}
