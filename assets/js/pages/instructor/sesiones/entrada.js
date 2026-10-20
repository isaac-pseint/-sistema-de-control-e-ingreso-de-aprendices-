// pages/instructor/sesiones/entrada.js — Punto de entrada de la vista de
// registro de entrada por llavero. Requiere sesión con rol Instructor.
import { requerirRol } from "../../../features/auth.js";
import { montarSidebar } from "../../../features/sidebar.js";
import { conectarEntrada } from "../../../features/instructor/sesiones/entrada.js";

requerirRol("Instructor").then(usuario => montarSidebar("Instructor", usuario));
conectarEntrada();