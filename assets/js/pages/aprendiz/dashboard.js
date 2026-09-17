// pages/aprendiz/dashboard.js — Punto de entrada del dashboard de aprendiz.
import { requerirRol } from "../../features/auth.js";
import { montarSidebar } from "../../features/sidebar.js";

requerirRol("Aprendiz").then(usuario => montarSidebar("Aprendiz", usuario));