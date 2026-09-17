// pages/admin/usuarios/listado.js — Punto de entrada de la vista de usuarios.
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { cargarListado, configurarFiltros } from "../../../features/admin/usuarios/listado.js";
import { configurarAccionesListado } from "../../../features/admin/usuarios/acciones.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));
cargarListado();
configurarFiltros();
configurarAccionesListado();