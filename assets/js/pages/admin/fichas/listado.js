// pages/admin/fichas/listado.js — Punto de entrada de la vista de fichas.
import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import {
    cargarFiltroProgramas,
    cargarListadoFichas,
    configurarAccionesFichas
} from "../../../features/admin/fichas/listado.js?v=20261028-2";
import { configurarModalesFicha } from "../../../features/admin/fichas/acciones.js?v=20261028-2";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));

cargarFiltroProgramas();
cargarListadoFichas();
configurarAccionesFichas();
configurarModalesFicha();