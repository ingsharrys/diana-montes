/** Tipos de las respuestas de la API (admin/api.php). */

export interface Tarea {
  asignacion: number | null; tarea: number; tipo: string; tipoNombre: string; emoji: string;
  titulo: string; descripcion: string | null; fecha: string | null; fechaTexto: string; lugar: string | null;
  meta: number | null; puntos: number; rol: 'responsable' | 'asistente' | null; esInvitacion: boolean;
  estado: string | null; estadoTexto: string | null; color: string | null; asignadaPor: string | null;
  resultado: number | null; nota: string | null; queReportar: string; abierta: boolean;
}

export interface MiInicio {
  nombre: string; primerNombre: string; puntos: number;
  nivel: { indice: number; nombre: string; emoji: string; siguiente: { nombre: string; emoji: string; puntos: number } | null; faltan: number; progreso: number };
  kpis: { invitados: number; red: number; porHacer: number; puesto: number | null };
  enlace: string; textoInvitacion: string; proximas: Tarea[];
  permisos: { clave: string; texto: string; desbloqueado: boolean; nivel: string; emoji: string; puntos: number }[];
  niveles: { nombre: string; emoji: string; puntos: number }[];
  puntosPor: { invitado: number; seguro: number };
  ranking: { puesto: number; nombre: string; puntos: number; esYo: boolean }[];
  zona: string | null; lider: string | null;
}

export interface MiRed {
  puedeContactar: boolean; puedeVerTodo: boolean; nivelParaContactar: string; nivelParaVerTodo: string; total: number;
  invitados: { id: number; nombre: string; zona: string | null; desde: string; invitados: number; nivelEmoji: string; telefono: string | null }[];
  red: { nombre: string; capa: number; invito: string; zona: string | null }[] | null;
}

export interface MisTareas {
  porResponder: Tarea[]; enCurso: Tarea[]; historial: Tarea[]; abiertas: Tarea[];
  organizo: { tarea: number; titulo: string; emoji: string; fechaTexto: string; esConvocatoria: boolean; invitados: number; confirmados: number; responsables: number; cumplidas: number; abierta: boolean }[];
  puedeConvocar: boolean; puedeAsignar: boolean; portalWeb: string;
}

export interface Resumen {
  soloSuRed: boolean; total: number; meta: number; progreso: number | null;
  avance: { vinculados: number; meta: number; progreso: number | null; puesto: number; equipo: number } | null;
  semana: { etiqueta: string; fecha: string; total: number; hoy: boolean }[];
  compromiso: { clave: string; nombre: string; total: number; seguro: boolean }[];
  calidad: { total: number; seguros: number; con_puesto: number; verificados: number | null };
  metaVotos: number;
  promotores: { activos: number; promotores: number; super: number } | null;
  topPromotores: { nombre: string; zona: string | null; lider: string; invitados: number; puntos: number; nivelEmoji: string }[];
  rankingEquipo: { nombre: string; rol: string; vinculados: number; meta: number | null }[];
  porZona: { nombre: string; total: number }[];
  tareas: { abiertas: number; por_validar: number; comprometidas: number; personas: number } | null;
}

export interface FilaSimpatizante {
  id: number; nombre: string; documento: string; telefono: string; zona: string | null; lider: string;
  nivel: string; nivelNombre: string; conPuesto: boolean; verificado: boolean;
}

export interface DetalleSimpatizante {
  id: number; nombre: string; documento: string; telefono: string; telefonoCompleto: string | null;
  fechaNacimiento: string | null; genero: string | null; zona: string | null; profesion: string | null; lider: string | null;
  puestoId: number | null; puesto: string | null; mesa: string | null; nivel: string; nivelNombre: string;
  verificado: boolean; verificadoAt: string | null; invitados: number; invitadoPor: string | null;
  puntos: number; nivelPromotor: string; nivelEmoji: string; registrado: string;
}

export interface Catalogos {
  zonas: { id: number; nombre: string; clase: string | null; grupo: string }[];
  profesiones: { id: number; nombre: string }[];
  puestos: { id: number; nombre: string; zona: string | null; mesas: number | null }[];
  lideres: { id: number; nombre: string }[];
  compromisos: { clave: string; nombre: string }[];
  generos: { clave: string; nombre: string }[];
  edadMinima: number;
}

export interface FilaTarea {
  id: number; titulo: string; tipoNombre: string; emoji: string; estado: string; alcance: string; fechaTexto: string; creador: string;
  total: number; aceptadas: number; porValidar: number; validadas: number; resultado: number; meta: number | null;
}

export interface ZonaAdmin { id: number; nombre: string; clase: string | null; grupo: string; uso: number; }
export interface PuestoAdmin { id: number; nombre: string; direccion: string | null; zonaId: number | null; zona: string | null; mesas: number | null; potencial: number | null; uso: number; }
export interface ProfesionAdmin { id: number; nombre: string; dia: string | null; uso: number; }
export interface CatalogosAdmin {
  clases: { clave: string; nombre: string }[];
  zonas: ZonaAdmin[]; puestos: PuestoAdmin[]; profesiones: ProfesionAdmin[];
}
