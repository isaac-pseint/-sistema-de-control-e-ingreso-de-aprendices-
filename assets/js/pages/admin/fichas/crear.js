// pages/admin/fichas/crear.js — Punto de entrada de la vista crear ficha.
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { cargarDatosFormularioFicha, conectarFormularioCrearFicha } from "../../../features/admin/fichas/formulario.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
cargarDatosFormularioFicha();
conectarFormularioCrearFicha();