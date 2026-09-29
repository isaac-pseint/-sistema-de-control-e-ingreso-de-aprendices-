// pages/instructor/sesiones/salida.js — Punto de entrada de la vista de
// registro de salida por llavero. Requiere sesión con rol Instructor.
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { conectarSalida } from "../../../features/instructor/sesiones/salida.js";

requerirRol("Instructor").then(usuario => montarSidebar("Instructor", usuario));
conectarSalida();