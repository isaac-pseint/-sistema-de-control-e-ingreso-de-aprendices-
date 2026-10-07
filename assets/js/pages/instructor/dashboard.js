// pages/instructor/dashboard.js — Punto de entrada del dashboard del instructor.
import { requerirRol } from "../../features/auth.js";
import { montarSidebar } from "../../features/sidebar.js";
import { cargarDashboardInstructor } from "../../features/instructor/dashboard.js";

requerirRol("Instructor").then(usuario => {
    montarSidebar("Instructor", usuario);
    cargarDashboardInstructor();
});