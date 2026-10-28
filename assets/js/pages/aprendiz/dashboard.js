// pages/aprendiz/dashboard.js — Punto de entrada del dashboard de aprendiz.
import { requerirRol } from "../../features/auth.js?v=20261028-1";
import { montarSidebar } from "../../features/sidebar.js?v=20261028-1";
import { cargarDashboardAprendiz } from "../../features/aprendiz/dashboard.js?v=20261028-1";

requerirRol("Aprendiz").then(usuario => {
    montarSidebar("Aprendiz", usuario);
    cargarDashboardAprendiz();
});