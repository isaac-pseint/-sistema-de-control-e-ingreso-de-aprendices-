// pages/admin/dashboard.js — Punto de entrada del dashboard de administrador.
import { requerirRol } from "../../features/auth.js?v=20261028-2";
import { montarSidebar } from "../../features/sidebar.js?v=20261028-2";
import { cargarDashboardAdmin } from "../../features/admin/dashboard.js?v=20261028-2";

requerirRol("Administrador").then(usuario => {
    montarSidebar("Administrador", usuario);
    cargarDashboardAdmin();
});