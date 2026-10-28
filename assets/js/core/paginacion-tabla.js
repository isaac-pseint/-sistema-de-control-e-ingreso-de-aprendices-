const estados = new WeakMap();

function crearControles(tbody) {
    const tabla = tbody.closest("table");
    if (!tabla) return null;

    const controles = document.createElement("div");
    controles.className = "paginacion-tabla";
    controles.setAttribute("aria-label", "Paginación de tabla");
    controles.innerHTML = `
        <span class="paginacion-tabla-info" aria-live="polite"></span>
        <div class="paginacion-tabla-acciones">
            <label>
                Filas por página
                <select class="paginacion-tabla-tamano" aria-label="Filas por página">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="0">Todos</option>
                </select>
            </label>
            <button type="button" class="btn btn-sm btn-outline" data-pagina="primera" aria-label="Primera página">«</button>
            <button type="button" class="btn btn-sm btn-outline" data-pagina="anterior" aria-label="Página anterior">‹</button>
            <span class="paginacion-tabla-pagina" aria-live="polite"></span>
            <button type="button" class="btn btn-sm btn-outline" data-pagina="siguiente" aria-label="Página siguiente">›</button>
            <button type="button" class="btn btn-sm btn-outline" data-pagina="ultima" aria-label="Última página">»</button>
        </div>`;
    tabla.insertAdjacentElement("afterend", controles);

    const estado = { filas: [], pagina: 1, tamano: 10, controles, tbody };
    controles.addEventListener("click", event => {
        const boton = event.target.closest("button[data-pagina]");
        if (!boton) return;

        const paginas = estado.tamano === 0
            ? 1
            : Math.max(1, Math.ceil(estado.filas.length / estado.tamano));
        if (boton.dataset.pagina === "primera") estado.pagina = 1;
        if (boton.dataset.pagina === "anterior") estado.pagina--;
        if (boton.dataset.pagina === "siguiente") estado.pagina++;
        if (boton.dataset.pagina === "ultima") estado.pagina = paginas;
        pintar(estado);
    });
    controles.querySelector("select").addEventListener("change", event => {
        estado.tamano = Number(event.target.value);
        estado.pagina = 1;
        pintar(estado);
    });

    return estado;
}

function pintar(estado) {
    const total = estado.filas.length;
    const paginas = estado.tamano === 0
        ? 1
        : Math.max(1, Math.ceil(total / estado.tamano));
    estado.pagina = Math.min(Math.max(1, estado.pagina), paginas);

    const inicio = estado.tamano === 0 ? 0 : (estado.pagina - 1) * estado.tamano;
    const fin = estado.tamano === 0 ? total : Math.min(inicio + estado.tamano, total);
    estado.tbody.innerHTML = estado.filas.slice(inicio, fin).join("");
    estado.controles.hidden = total === 0;
    estado.controles.querySelector(".paginacion-tabla-info").textContent =
        total === 0 ? "" : `Mostrando ${inicio + 1}–${fin} de ${total}`;
    estado.controles.querySelector(".paginacion-tabla-pagina").textContent =
        `Página ${estado.pagina} de ${paginas}`;

    estado.controles.querySelector('[data-pagina="primera"]').disabled = estado.pagina === 1;
    estado.controles.querySelector('[data-pagina="anterior"]').disabled = estado.pagina === 1;
    estado.controles.querySelector('[data-pagina="siguiente"]').disabled = estado.pagina === paginas;
    estado.controles.querySelector('[data-pagina="ultima"]').disabled = estado.pagina === paginas;
}

export function renderizarFilasPaginadas(tbody, filas, reiniciar = true) {
    if (!tbody) return;

    let estado = estados.get(tbody);
    if (!estado) {
        estado = crearControles(tbody);
        if (!estado) {
            tbody.innerHTML = filas.join("");
            return;
        }
        estados.set(tbody, estado);
    }

    estado.filas = filas;
    if (reiniciar) estado.pagina = 1;
    pintar(estado);
}
