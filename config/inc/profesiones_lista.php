<?php
/**
 * Lista completa de profesiones, oficios y ocupaciones para el formulario de
 * registro (Garzón, Huila: incluye los oficios del campo, del café y la pesca).
 *
 * Se carga con "Actualizar plataforma" sin borrar ni cambiar las que ya
 * existen (hay personas registradas con ellas): solo agrega las que faltan.
 * El día de celebración (MM-DD) es el que se usa en Colombia para el saludo
 * por WhatsApp; donde no hay una fecha fija se deja vacío y se puede poner
 * desde Admin › Catálogos o desde la app.
 *
 * Los nombres solo usan letras, espacios, paréntesis y "/", como pide el catálogo.
 */

/** Sube este número si cambia la lista: "Actualizar plataforma" vuelve a agregar lo que falte. */
const PROFESIONES_VERSION = '2026-10-1';

const PROFESIONES_LISTA = [
    // ---------- Campo, café, ganadería y pesca ----------
    ['Agricultor/a', '06-01'],
    ['Campesino/a', '06-01'],
    ['Caficultor/a', null],
    ['Recolector/a de café', null],
    ['Cacaotero/a', null],
    ['Ganadero/a', null],
    ['Jornalero/a', null],
    ['Mayordomo/a de finca', null],
    ['Arriero/a', null],
    ['Piscicultor/a', null],
    ['Pescador/a', null],
    ['Avicultor/a', null],
    ['Porcicultor/a', null],
    ['Apicultor/a', null],
    ['Horticultor/a', null],
    ['Floricultor/a', null],
    ['Técnico/a agropecuario/a', null],
    ['Ingeniero/a agrónomo/a', '08-17'],
    ['Zootecnista', null],
    ['Médico/a veterinario/a', null],
    ['Minero/a', null],

    // ---------- Salud ----------
    ['Médico/a', '12-03'],
    ['Médico/a especialista', '12-03'],
    ['Odontólogo/a', null],
    ['Enfermero/a', '05-12'],
    ['Auxiliar de enfermería', '05-12'],
    ['Bacteriólogo/a', null],
    ['Fisioterapeuta', null],
    ['Terapeuta ocupacional', null],
    ['Terapeuta respiratorio/a', null],
    ['Fonoaudiólogo/a', null],
    ['Psicólogo/a', '11-20'],
    ['Nutricionista', null],
    ['Optómetra', null],
    ['Químico/a farmacéutico/a', null],
    ['Regente de farmacia', null],
    ['Instrumentador/a quirúrgico/a', null],
    ['Paramédico/a', null],
    ['Auxiliar de salud oral', null],
    ['Promotor/a de salud', null],
    ['Partera', null],

    // ---------- Educación ----------
    ['Docente', '05-15'],
    ['Docente de preescolar', '05-15'],
    ['Profesor/a universitario/a', '05-15'],
    ['Rector/a', '05-15'],
    ['Coordinador/a de colegio', '05-15'],
    ['Orientador/a escolar', '05-15'],
    ['Madre comunitaria', null],
    ['Agente educativo/a', null],
    ['Bibliotecario/a', null],
    ['Investigador/a', null],
    ['Instructor/a', null],

    // ---------- Derecho, administración y comercio ----------
    ['Abogado/a', '06-22'],
    ['Juez/a', null],
    ['Notario/a', null],
    ['Contador/a público/a', '03-01'],
    ['Auxiliar contable', null],
    ['Administrador/a de empresas', null],
    ['Economista', null],
    ['Gerente', null],
    ['Empresario/a', null],
    ['Emprendedor/a', null],
    ['Comerciante', null],
    ['Tendero/a', null],
    ['Vendedor/a', null],
    ['Vendedor/a ambulante', null],
    ['Asesor/a comercial', null],
    ['Asesor/a de seguros', null],
    ['Cajero/a', null],
    ['Empleado/a bancario/a', null],
    ['Mercaderista', null],
    ['Secretario/a', '04-26'],
    ['Recepcionista', null],
    ['Auxiliar administrativo/a', null],
    ['Funcionario/a público/a', null],
    ['Contratista', null],
    ['Talento humano (recursos humanos)', null],

    // ---------- Ingeniería, ciencia y tecnología ----------
    ['Ingeniero/a civil', '08-17'],
    ['Ingeniero/a de sistemas', '08-17'],
    ['Ingeniero/a industrial', '08-17'],
    ['Ingeniero/a ambiental', '08-17'],
    ['Ingeniero/a electrónico/a', '08-17'],
    ['Ingeniero/a eléctrico/a', '08-17'],
    ['Ingeniero/a mecánico/a', '08-17'],
    ['Ingeniero/a de petróleos', '08-17'],
    ['Ingeniero/a forestal', '08-17'],
    ['Arquitecto/a', null],
    ['Topógrafo/a', null],
    ['Geólogo/a', null],
    ['Biólogo/a', null],
    ['Químico/a', null],
    ['Desarrollador/a de software', null],
    ['Técnico/a en sistemas', null],
    ['Técnico/a en reparación de celulares', null],
    ['Tecnólogo/a', null],
    ['Diseñador/a gráfico/a', null],

    // ---------- Construcción y oficios ----------
    ['Albañil', null],
    ['Maestro/a de obra', null],
    ['Carpintero/a', null],
    ['Ebanista', null],
    ['Electricista', null],
    ['Plomero/a', null],
    ['Pintor/a de obra', null],
    ['Soldador/a', null],
    ['Ornamentador/a', null],
    ['Herrero/a', null],
    ['Mecánico/a', null],
    ['Latonero/a', null],
    ['Cerrajero/a', null],
    ['Tapicero/a', null],
    ['Zapatero/a', null],
    ['Modista', null],
    ['Sastre', null],
    ['Confeccionista', null],
    ['Artesano/a', null],
    ['Joyero/a', null],
    ['Operario/a', null],
    ['Operador/a de maquinaria pesada', null],
    ['Bodeguero/a', null],
    ['Reciclador/a', null],
    ['Jardinero/a', null],

    // ---------- Transporte ----------
    ['Conductor/a', '07-16'],
    ['Taxista', '07-16'],
    ['Mototaxista', '07-16'],
    ['Transportador/a', '07-16'],
    ['Conductor/a de bus o buseta', '07-16'],
    ['Domiciliario/a', null],
    ['Mensajero/a', null],

    // ---------- Comida, belleza y servicios ----------
    ['Cocinero/a', null],
    ['Chef', null],
    ['Panadero/a', null],
    ['Carnicero/a', null],
    ['Mesero/a', null],
    ['Barista', null],
    ['Vendedor/a de comidas', null],
    ['Peluquero/a', null],
    ['Barbero/a', null],
    ['Estilista', null],
    ['Manicurista', null],
    ['Esteticista', null],
    ['Trabajador/a doméstico/a', null],
    ['Cuidador/a', null],
    ['Auxiliar de servicios generales', null],
    ['Vigilante (guarda de seguridad)', null],
    ['Trabajador/a de hotelería', null],
    ['Guía turístico/a', null],

    // ---------- Seguridad y servicio público ----------
    ['Policía', '11-05'],
    ['Militar', '08-07'],
    ['Bombero/a', null],
    ['Agente de tránsito', null],
    ['Socorrista', null],

    // ---------- Cultura, deporte y comunicación ----------
    ['Periodista', '02-09'],
    ['Comunicador/a social', '02-09'],
    ['Locutor/a', null],
    ['Publicista', null],
    ['Fotógrafo/a', null],
    ['Músico/a', '11-22'],
    ['Artista', null],
    ['Bailarín/a', null],
    ['Actor / actriz', null],
    ['Escritor/a', null],
    ['Deportista', null],
    ['Entrenador/a deportivo/a', null],

    // ---------- Comunidad y ciencias sociales ----------
    ['Trabajador/a social', null],
    ['Sociólogo/a', null],
    ['Politólogo/a', null],
    ['Líder comunitario/a', null],
    ['Presidente/a de Junta de Acción Comunal', null],
    ['Pastor/a', null],
    ['Sacerdote', null],
    ['Religioso/a', null],

    // ---------- Otras situaciones ----------
    ['Estudiante', null],
    ['Estudiante universitario/a', null],
    ['Ama de casa', null],
    ['Pensionado/a', null],
    ['Trabajador/a independiente', null],
    ['Empleado/a', null],
    ['Desempleado/a', null],
    ['Otra', null],
];

/** ¿Ya se cargó esta versión de la lista? */
function profesiones_cargadas(PDO $db): bool
{
    if (!esquema_tiene($db, 'configuracion')) return false;
    $st = $db->prepare("SELECT valor FROM configuracion WHERE clave = 'profesiones_version'");
    $st->execute();
    return $st->fetchColumn() === PROFESIONES_VERSION;
}

/**
 * Agrega las profesiones que falten (la llave única del nombre evita repetidos,
 * sin importar tildes ni mayúsculas). No toca las existentes.
 */
function profesiones_cargar(PDO $db): void
{
    $ins = $db->prepare('INSERT IGNORE INTO profesiones (nombre, dia_celebracion) VALUES (:n, :d)');
    foreach (PROFESIONES_LISTA as [$nombre, $dia]) {
        $ins->execute(['n' => $nombre, 'd' => $dia ? "2000-$dia" : null]);
    }
    if (esquema_tiene($db, 'configuracion')) {
        $db->prepare("INSERT INTO configuracion (clave, valor) VALUES ('profesiones_version', :v) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
           ->execute(['v' => PROFESIONES_VERSION]);
    }
}
