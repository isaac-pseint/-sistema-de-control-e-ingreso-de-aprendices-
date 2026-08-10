import { api } from "../api.js";
import { showToast } from "../ui.js";
import { mostrarErrorCampo } from "../forms.js";

const form = document.getElementById("loginForm");

function validarEmail(input) {
    const valor = input.value.trim();
    if (!valor) return mostrarErrorCampo(input, "El correo es obligatorio.");
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) return mostrarErrorCampo(input, "El correo no es válido.");
    return mostrarErrorCampo(input, "");
}

function validarPassword(input) {
    const valor = input.value.trim();
    if (!valor) return mostrarErrorCampo(input, "La contraseña es obligatoria.");
    return mostrarErrorCampo(input, "");
}

if (form) {
    form.email.addEventListener("input", () => validarEmail(form.email));
    form.email.addEventListener("blur", () => validarEmail(form.email));
    form.password.addEventListener("input", () => validarPassword(form.password));
    form.password.addEventListener("blur", () => validarPassword(form.password));

    form.addEventListener("submit", (e) => {
        e.preventDefault();

        const emailOk = validarEmail(form.email);
        const passwordOk = validarPassword(form.password);

        if (!emailOk) {
            form.email.focus();
            return;
        } else if (!passwordOk) {
            form.password.focus();
            return;
        }

        const boton = form.querySelector("[type=submit]");
        if (boton) {
            boton.disabled = true;
            boton.textContent = "Ingresando...";
        }

        api("login", { method: "POST", body: new FormData(form) })
            .then(data => {
                if (data.ok) {
                    showToast("success", data.mensaje || "Sesión iniciada correctamente.");
                    const rol = data.data ? data.data.rol : null;
                    
                    if (rol === "Administrador") {
                        window.location.href = "admin/usuarios.html";
                    } else {
                        // TODO: definir panel propio
                        window.location.href = "dashboard.html";
                    }
                } else {
                    showToast("danger", data.error || "Credenciales inválidas.");
                }
            })
            .catch(() => showToast("danger", "Error de conexión con el servidor."))
            .finally(() => {
                if (boton) {
                    boton.disabled = false;
                    boton.textContent = "Ingresar";
                }
            });
    });
}
