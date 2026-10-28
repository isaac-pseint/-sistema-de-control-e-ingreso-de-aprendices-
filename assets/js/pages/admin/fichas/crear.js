// pages/admin/fichas/crear.js — Punto de entrada de la vista crear ficha.
import { requerirRol } from "../../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-1";
import { cargarDatosFormularioFicha, conectarFormularioCrearFicha } from "../../../features/admin/fichas/formulario.js?v=20261028-1";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
cargarDatosFormularioFicha();
conectarFormularioCrearFicha();