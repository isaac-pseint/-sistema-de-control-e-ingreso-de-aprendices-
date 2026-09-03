// pages/asistencia.js — Punto de entrada de la vista de las asistencias.
import { comprobarSesion } from "../auth.js";
import { cargarListado, configurarAccionesListado } from "../admin-usuarios.js";

comprobarSesion();
cargarListado();
configurarAccionesListado();