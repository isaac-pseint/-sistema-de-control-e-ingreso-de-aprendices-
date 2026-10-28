// pages/instructor/dashboard.js — Punto de entrada del dashboard del instructor.
import { requerirRol } from "../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../features/sidebar.js?v=20261028-2";
import { cargarDashboardInstructor } from "../../features/instructor/dashboard.js?v=20261028-2";

requerirRol("Instructor").then(usuario => {
    montarSidebar("Instructor", usuario);
    cargarDashboardInstructor();
});