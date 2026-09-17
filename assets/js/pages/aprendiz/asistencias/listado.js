// pages/aprendiz/asistencias/listado.js — Punto de entrada de la vista de asistencias.
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { cargarListado } from "../../../features/aprendiz/asistencias/listado.js";

requerirRol("Aprendiz").then(usuario => montarSidebar("Aprendiz", usuario));
cargarListado();