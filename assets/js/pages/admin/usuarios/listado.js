// pages/admin/usuarios/listado.js — Punto de entrada de la vista de usuarios.
import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import { cargarListado, cargarFiltroFichas, configurarFiltros } from "../../../features/admin/usuarios/listado.js?v=20261028-2";
import { configurarAccionesListado } from "../../../features/admin/usuarios/acciones.js?v=20261028-2";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
cargarFiltroFichas().then(() => cargarListado());
configurarFiltros();
configurarAccionesListado();