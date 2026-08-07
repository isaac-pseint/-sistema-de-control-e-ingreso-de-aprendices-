// ui.js — Helpers de presentación (toasts).

export function showToast(tipo, mensaje) {
    let container = document.getElementById("toast-container");

    // Crear el contenedor de toasts si no existe.
    if (!container) {
        container = document.createElement("div");
        container.id = "toast-container";
        document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    toast.classList.add("toast", `toast-${tipo}`);

    const icon = document.createElement("div");
    icon.classList.add("toast-icon");

    const content = document.createElement("div");
    content.classList.add("toast-content");
    content.textContent = mensaje;

    icon.textContent = tipo === "success" ? "✓" :
        tipo === "danger" ? "!" :
            tipo === "warning" ? "⚠" :
                "i";

    toast.appendChild(icon);
    toast.appendChild(content);
    container.appendChild(toast);

    // Animar entrada y auto-eliminar a los 7 segundos.
    setTimeout(() => {
        toast.classList.add("show");
    }, 10);

    setTimeout(() => {
        toast.classList.remove("show");

        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 7000);
}
