// pages/instructor/sesiones/salida.js — Punto de entrada de la vista de
// registro de salida por llavero. Requiere sesión con rol Instructor.
import { requerirRol } from "../../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-1";
import { conectarSalida } from "../../../features/instructor/sesiones/salida.js?v=20261028-1";

requerirRol("Instructor").then(usuario => montarSidebar("Instructor", usuario));
conectarSalida();