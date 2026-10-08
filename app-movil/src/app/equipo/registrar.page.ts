import { Component, inject, signal, computed } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { IonContent, IonHeader, IonToolbar, IonIcon, IonSpinner, IonFooter, ModalController } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  personOutline, idCardOutline, logoWhatsapp, calendarOutline, locationOutline, briefcaseOutline, flagOutline,
  chevronForward, chevronBack, checkmark, checkmarkCircle, shieldCheckmarkOutline, peopleOutline, gridOutline,
} from 'ionicons/icons';
import { ApiService, ApiError } from '../core/api.service';
import { AvisosService } from '../core/avisos.service';
import { AuthService } from '../core/auth.service';
import { Catalogos } from '../core/modelos';
import { CatalogosService } from './catalogos.service';
import { abrirSelector } from '../shared/selector.component';
import { celular, formatoCelular, soloDigitos, soloLetras } from '../core/util';

const VACIO = () => ({
  nombre: '', documento: '', telefono: '', fecha_nacimiento: '', genero: '', zona_id: 0, profesion_id: null as number | null,
  puesto_id: null as number | null, mesa: '', nivel: 'simpatizante', lider_id: null as number | null, consentimiento: false,
});

/** Emoji y explicación de cada nivel de compromiso (las claves vienen del servidor). */
const COMPROMISO_INFO: Record<string, [string, string]> = {
  indeciso: ['🤔', 'Aún no ha decidido'],
  simpatizante: ['💜', 'Le gusta la propuesta'],
  voto_seguro: ['✅', 'Votará por Diana'],
  voluntario: ['🙋', 'Quiere ayudar en la campaña'],
  testigo: ['🛡️', 'Cuidará los votos en la mesa'],
};

/** Paso del formulario en el que está cada campo (para llevar a la persona al error). */
const PASO_DE: Record<string, number> = {
  nombre: 0, documento: 0, telefono: 0, fecha_nacimiento: 0, genero: 0,
  zona_id: 1, profesion_id: 1,
  nivel: 2, puesto_id: 2, mesa: 2, lider_id: 2, consentimiento: 2,
};

@Component({
  selector: 'app-registrar',
  standalone: true,
  imports: [FormsModule, IonContent, IonHeader, IonToolbar, IonIcon, IonSpinner, IonFooter],
  templateUrl: './registrar.page.html',
  styleUrls: ['./registrar.page.scss'],
})
export class RegistrarPage {
  private api = inject(ApiService);
  private avisos = inject(AvisosService);
  private catalogos = inject(CatalogosService);
  private modal = inject(ModalController);
  private router = inject(Router);
  auth = inject(AuthService);

  readonly pasos = [
    { titulo: '¿Quién es?', sub: 'Datos personales' },
    { titulo: '¿Dónde vive?', sub: 'Barrio y oficio' },
    { titulo: 'Su compromiso', sub: 'Votación y autorización' },
  ];
  paso = signal(0);
  cat = signal<Catalogos | null>(null);
  f = VACIO();
  errores = signal<Record<string, string>>({});
  guardando = signal(false);
  listo = signal<{ nombre: string } | null>(null);
  hoy = new Date().toISOString().slice(0, 10);
  info = COMPROMISO_INFO;
  /** Fuerza el recálculo de los nombres elegidos (el formulario no es una señal). */
  private version = signal(0);

  zonaNombre = computed(() => (this.version(), this.cat()?.zonas.find(z => z.id === this.f.zona_id)?.nombre ?? ''));
  profesionNombre = computed(() => (this.version(), this.cat()?.profesiones.find(p => p.id === this.f.profesion_id)?.nombre ?? ''));
  puestoNombre = computed(() => (this.version(), this.cat()?.puestos.find(p => p.id === this.f.puesto_id)?.nombre ?? ''));
  liderNombre = computed(() => (this.version(), this.cat()?.lideres.find(l => l.id === this.f.lider_id)?.nombre ?? ''));
  puestoMesas = computed(() => (this.version(), this.cat()?.puestos.find(p => p.id === this.f.puesto_id)?.mesas ?? null));

  constructor() {
    addIcons({ personOutline, idCardOutline, logoWhatsapp, calendarOutline, locationOutline, briefcaseOutline, flagOutline,
      chevronForward, chevronBack, checkmark, checkmarkCircle, shieldCheckmarkOutline, peopleOutline, gridOutline });
  }

  async ionViewWillEnter() {
    try { this.cat.set(await this.catalogos.obtener()); }
    catch (e) { await this.avisos.error((e as ApiError).message); }
  }

  get pideLider() { return !this.auth.usuario()?.permisos?.soloSuRed; }
  celularVisible() { return formatoCelular(this.f.telefono); }

  /** Filtra lo que se escribe (letras, números) y lo devuelve al campo para que no se vea lo que no vale. */
  limpiar(campo: 'nombre' | 'documento' | 'telefono' | 'mesa', ev: Event) {
    const el = ev.target as HTMLInputElement;
    const t = el.value ?? '';
    if (campo === 'nombre') this.f.nombre = soloLetras(t);
    if (campo === 'documento') this.f.documento = soloDigitos(t, 10);
    if (campo === 'telefono') this.f.telefono = celular(t);
    if (campo === 'mesa') this.f.mesa = soloDigitos(t, 4);
    el.value = campo === 'telefono' ? formatoCelular(this.f.telefono) : this.f[campo];
    this.quitarError(campo);
  }

  quitarError(campo: string) {
    if (!this.errores()[campo]) return;
    const e = { ...this.errores() }; delete e[campo]; this.errores.set(e);
  }

  set<K extends 'genero' | 'nivel'>(campo: K, v: string) { this.f[campo] = v; this.quitarError(campo); }
  aceptar() { this.f.consentimiento = !this.f.consentimiento; this.quitarError('consentimiento'); }

  async elegirZona() {
    const c = this.cat(); if (!c) return;
    const o = await abrirSelector(this.modal, {
      titulo: 'Barrio o vereda', buscar: 'Escribe el barrio o la vereda…', seleccion: this.f.zona_id || null,
      opciones: c.zonas.map(z => ({ id: z.id, nombre: z.nombre, grupo: z.grupo })),
    });
    if (o) { this.f.zona_id = o.id; this.quitarError('zona_id'); this.version.update(v => v + 1); }
  }

  async elegirProfesion() {
    const c = this.cat(); if (!c) return;
    const o = await abrirSelector(this.modal, {
      titulo: 'Profesión u oficio', buscar: 'Buscar profesión…', seleccion: this.f.profesion_id,
      opciones: c.profesiones.map(p => ({ id: p.id, nombre: p.nombre })),
    });
    if (o) { this.f.profesion_id = o.id; this.quitarError('profesion_id'); this.version.update(v => v + 1); }
  }

  async elegirPuesto() {
    const c = this.cat(); if (!c) return;
    const o = await abrirSelector(this.modal, {
      titulo: 'Puesto de votación', buscar: 'Buscar puesto…', seleccion: this.f.puesto_id, opcional: true, textoNinguno: 'Aún no lo sabe',
      opciones: c.puestos.map(p => ({ id: p.id, nombre: p.nombre, detalle: [p.zona, p.mesas ? p.mesas + ' mesas' : null].filter(Boolean).join(' · ') || null })),
    });
    if (o !== undefined) { this.f.puesto_id = o?.id ?? null; this.quitarError('puesto_id'); this.version.update(v => v + 1); }
  }

  async elegirLider() {
    const c = this.cat(); if (!c) return;
    const o = await abrirSelector(this.modal, {
      titulo: 'Líder que lo vincula', buscar: 'Buscar líder…', seleccion: this.f.lider_id,
      opciones: c.lideres.map(l => ({ id: l.id, nombre: l.nombre })),
    });
    if (o) { this.f.lider_id = o.id; this.quitarError('lider_id'); this.version.update(v => v + 1); }
  }

  /** Revisión rápida de un paso; el servidor vuelve a validar todo al guardar. */
  private revisar(paso: number): Record<string, string> {
    const e: Record<string, string> = {};
    const c = this.cat();
    if (paso === 0) {
      if (this.f.nombre.trim().split(/\s+/).filter(p => p.length >= 2).length < 2) e['nombre'] = 'Escribe nombre y apellido.';
      if (this.f.documento.length < 5) e['documento'] = 'Escribe el número de documento (solo números).';
      if (!/^3\d{9}$/.test(this.f.telefono)) e['telefono'] = 'El celular debe tener 10 dígitos y empezar por 3.';
      if (!this.f.fecha_nacimiento) e['fecha_nacimiento'] = 'Escribe la fecha de nacimiento.';
      if (c?.generos.length && !this.f.genero) e['genero'] = 'Selecciona el género.';
    }
    if (paso === 1) {
      if (!this.f.zona_id) e['zona_id'] = 'Selecciona el barrio o vereda.';
      if (!this.f.profesion_id) e['profesion_id'] = 'Selecciona la profesión u oficio.';
    }
    if (paso === 2) {
      const mesas = this.puestoMesas();
      if (this.f.mesa && mesas && +this.f.mesa > mesas) e['mesa'] = `Ese puesto solo tiene ${mesas} mesas.`;
      if (this.pideLider && !this.f.lider_id) e['lider_id'] = 'Selecciona el líder que lo vincula.';
      if (!this.f.consentimiento) e['consentimiento'] = 'Sin la autorización de datos no se puede guardar el registro.';
    }
    return e;
  }

  siguiente() {
    const e = this.revisar(this.paso());
    this.errores.set(e);
    if (Object.keys(e).length) return;
    if (this.paso() < this.pasos.length - 1) { this.paso.update(p => p + 1); this.arriba(); }
    else void this.guardar();
  }

  atras() { if (this.paso() > 0) { this.paso.update(p => p - 1); this.errores.set({}); this.arriba(); } }
  irA(i: number) { if (i < this.paso()) { this.paso.set(i); this.errores.set({}); this.arriba(); } }

  private async guardar() {
    this.guardando.set(true);
    try {
      const nombre = this.f.nombre.trim();
      await this.api.post<{ id: number; msg: string }>('equipo/simpatizantes', this.f);
      this.listo.set({ nombre });
      this.arriba();
    } catch (err) {
      const x = err as ApiError;
      if (x.errores) {
        this.errores.set(x.errores);
        const pasoError = Math.min(...Object.keys(x.errores).map(k => PASO_DE[k] ?? 2));
        this.paso.set(pasoError);
        this.arriba();
      }
      await this.avisos.error(x.message);
    } finally { this.guardando.set(false); }
  }

  otro() { this.f = VACIO(); this.errores.set({}); this.paso.set(0); this.listo.set(null); this.version.update(v => v + 1); }
  verLista() { this.otro(); void this.router.navigateByUrl('/equipo/simpatizantes'); }

  private arriba() { setTimeout(() => document.querySelector<HTMLIonContentElement>('app-registrar ion-content')?.scrollToTop(250), 30); }
}
