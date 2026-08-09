import { comprobarSesion } from "../auth.js";
import { cargarDatosFormulario } from "../admin-usuarios.js";
import { conectarFormulario } from "../forms.js";

comprobarSesion();
cargarDatosFormulario();
conectarFormulario('formCrearUsuario', 'crearUsuario');
