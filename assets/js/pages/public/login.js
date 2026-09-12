// pages/public/login.js — Punto de entrada de la vista login: activa el login.
// El redirect por rol lo decide el servidor (AuthController::login → data.redirect).
import { conectarLogin } from "../../features/auth.js";

conectarLogin();