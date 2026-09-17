// pages/admin/fichas/listado.js — Punto de entrada de la vista de fichas.
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import {
    cargarFiltroProgramas,
    cargarListadoFichas,
    configurarAccionesFichas
} from "../../../features/admin/fichas/listado.js";
import { configurarModalesFicha } from "../../../features/admin/fichas/acciones.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));

cargarFiltroProgramas();
cargarListadoFichas();
configurarAccionesFichas();
configurarModalesFicha();