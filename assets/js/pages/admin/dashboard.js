// pages/admin/dashboard.js — Punto de entrada del dashboard de administrador.
import { requerirRol } from "../../features/auth.js";
import { montarSidebar } from "../../features/sidebar.js";

requerirRol("Administrador").then(usuario => montarSidebar("Administrador", usuario));