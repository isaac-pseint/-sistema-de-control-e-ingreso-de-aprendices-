// pages/login.js — Punto de entrada de la vista login: activa el login.
// El redirect por rol lo decide el servidor (AuthController::login → data.redirect),
// así que aquí solo se conecta el formulario con el patrón genérico.
import { conectarLogin } from "../auth.js";

conectarLogin();
