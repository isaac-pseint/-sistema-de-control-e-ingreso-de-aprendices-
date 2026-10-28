// pages/instructor/sesiones/entrada.js — Punto de entrada de la vista de
// registro de entrada por llavero. Requiere sesión con rol Instructor.
import { requerirRol } from "../../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../../features/sidebar.js?v=20261028-2";
import { conectarEntrada } from "../../../features/instructor/sesiones/entrada.js?v=20261028-2";

requerirRol("Instructor").then(usuario => montarSidebar("Instructor", usuario));
conectarEntrada();