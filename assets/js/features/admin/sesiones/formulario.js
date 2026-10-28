import { api } from "../../../core/api.js?v=20261028-2";
import { showToast, esc } from "../../../core/ui.js?v=20261028-2";
import { conectarFormulario } from "../../../core/forms.js?v=20261028-2";

let catalogo = { fichas: [], competencias: [], instructores: [] };

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

// El select de instructor se puede bloquear por dos motivos distintos:
//  1) fija la competencia elegida (el instructor viene del catálogo), o
//  2) permisos: la sesión ya no es editable desde el formulario.
// Solo en el caso (1) hay que limpiar el valor cuando no hay competencia;
// con la bandera se evita borrar el instructor de sesiones ya bloqueadas
// (p. ej. las creadas por el importador de horario, sin competencia).
let instructorFijadoPorCompetencia = false;

function mostrarInstructorCompetencia() {
    const compId = document.getElementById("competencia_id")?.value;
    const selectInst = document.getElementById("instructor_id");
    if (!selectInst) return;
    const comp = catalogo.competencias.find(c => String(c.id) === String(compId));
    if (comp && comp.Instructor_id) {
        selectInst.value = String(comp.Instructor_id);
        instructorFijadoPorCompetencia = true;
        selectInst.disabled = true;
    } else {
        if (instructorFijadoPorCompetencia) {
            selectInst.value = "";
            instructorFijadoPorCompetencia = false;
        }
        selectInst.disabled = false;
    }
}

function actualizarCompetencias() {
    const fichaId = document.getElementById("ficha_id")?.value;
    const selectComp = document.getElementById("competencia_id");
    const programaId = programaDeFicha(fichaId);
    if (!programaId) {
        llenarSelect(selectComp, [], "Sin competencia (solo horario)");
        mostrarInstructorCompetencia();
        return;
    }
    llenarSelect(selectComp, competenciasDelPrograma(programaId), "Sin competencia (solo horario)");
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
        catalogo.instructores = data.data.instructores || [];

        const selectFicha = document.getElementById("ficha_id");
        llenarSelect(selectFicha, catalogo.fichas.map(f => ({ id: f.id, label: f.label })), "Seleccione ficha...");

        const selectInst = document.getElementById("instructor_id");
        llenarSelect(selectInst, catalogo.instructores.map(u => ({
            id: u.id,
            label: `${u.nombre} ${u.apellido}`
        })), "Seleccione instructor...");

        selectFicha?.addEventListener("change", actualizarCompetencias);
        document.getElementById("competencia_id")?.addEventListener("change", mostrarInstructorCompetencia);
        return data;
    });
}

export function conectarFormularioCrearSesion() {
    conectarFormulario("formCrearSesion", "crearSesion", {
        textoEnviando: "Guardando...",
        textoRestaurar: "Guardar sesión",
        resetOnSuccess: true
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

function aplicarBloqueoFormularioSesion(sesion, permisos = {}) {
    const estado = document.getElementById("estado");
    const selectInst = document.getElementById("instructor_id");
    const aviso = document.getElementById("avisoSesionProgramacion");
    const btnGuardar = document.querySelector("#formEditarSesion button[type=submit]");
    const cerrada = Boolean(permisos.sesion_finalizada) || sesion.estado === "Finalizada";
    const prog = Boolean(permisos.editar_programacion);

    if (cerrada) {
        bloquearCampo(document.getElementById("ficha_id"), true);
        bloquearCampo(document.getElementById("competencia_id"), true);
        bloquearCampo(selectInst, true);
        bloquearCampo(document.getElementById("fecha"), true);
        bloquearCampo(document.getElementById("hora_inicio"), true);
        bloquearCampo(document.getElementById("hora_fin"), true);
        bloquearCampo(estado, true);
        if (aviso) {
            aviso.textContent = "Esta sesión está finalizada; no admite más cambios.";
            aviso.hidden = false;
        }
        if (btnGuardar) btnGuardar.disabled = true;
        return;
    }

    bloquearCampo(document.getElementById("ficha_id"), !prog);
    bloquearCampo(document.getElementById("competencia_id"), !prog);
    bloquearCampo(selectInst, !prog);
    bloquearCampo(document.getElementById("fecha"), !prog);
    bloquearCampo(document.getElementById("hora_inicio"), !prog);
    bloquearCampo(document.getElementById("hora_fin"), !prog);

    if (estado) {
        estado.disabled = !permisos.editar_estado;
        const optActivo = estado.querySelector('option[value="Activo"]');
        const optFinalizada = estado.querySelector('option[value="Finalizada"]');
        const optCancelado = estado.querySelector('option[value="Cancelado"]');
        if (optActivo) {
            const puedeElegirActivo = permisos.antes_de_inicio
                || (sesion.estado === "Cancelado" && permisos.antes_de_fin);
            optActivo.disabled = !puedeElegirActivo && sesion.estado !== "Activo";
        }
        if (optFinalizada) {
            optFinalizada.disabled = sesion.estado === "Cancelado";
        }
        if (optCancelado) {
            optCancelado.disabled = sesion.estado !== "Activo";
        }
        if (sesion.estado === "Cancelado" && estado.value === "Finalizada") {
            estado.value = "Cancelado";
        }
    }

    if (aviso) {
        if (!prog && permisos.editar_estado) {
            if (sesion.estado === "Cancelado") {
                aviso.textContent = "Sesión cancelada: puede reactivarla (Activo) si aún está dentro del horario programado. No se puede finalizar una sesión cancelada.";
                aviso.hidden = false;
            } else if (permisos.antes_de_fin) {
                aviso.textContent = "La sesión ya inició: puede cambiar el estado. Al finalizar, se completan salidas pendientes a la hora de fin programada y las inasistencias de quien no marcó entrada.";
            } else {
                aviso.textContent = "La sesión ya terminó su horario: solo puede reactivar una sesión cancelada antes de que venza el bloque, o dejar el estado actual.";
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

export async function prellenarFormularioSesion(sesion, permisos = {}) {
    await cargarCatalogoSesion();
    document.getElementById("id").value = sesion.id;
    document.getElementById("ficha_id").value = sesion.Ficha_id;
    actualizarCompetencias();
    document.getElementById("competencia_id").value = sesion.Competencia_id || "";
    mostrarInstructorCompetencia();
    const selectInst = document.getElementById("instructor_id");
    if (selectInst) selectInst.value = String(sesion.Instructor_id);
    document.getElementById("fecha").value = sesion.fecha;
    document.getElementById("hora_inicio").value = String(sesion.hora_inicio).slice(0, 5);
    document.getElementById("hora_fin").value = String(sesion.hora_fin).slice(0, 5);
    const estado = document.getElementById("estado");
    if (estado) estado.value = sesion.estado || "Activo";

    aplicarBloqueoFormularioSesion(sesion, permisos);
}

export async function refrescarSesionEnEdicion() {
    const id = document.getElementById("id")?.value;
    if (!id) return;
    const payload = await cargarSesionPorId(id);
    if (payload) {
        await prellenarFormularioSesion(payload.sesion, payload.permisos);
    }
}

export function conectarFormularioEditarSesion() {
    conectarFormulario("formEditarSesion", "actualizarSesion", {
        textoEnviando: "Guardando...",
        textoRestaurar: "Guardar cambios",
        onExito: () => {
            refrescarSesionEnEdicion();
        }
    });
}
