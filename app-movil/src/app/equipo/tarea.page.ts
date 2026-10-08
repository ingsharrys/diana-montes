import { Component, inject, signal, computed } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import {
  IonContent, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonButton, IonSpinner, IonSegment, IonSegmentButton, IonLabel,
  AlertController,
} from '@ionic/angular/standalone';
import { FormsModule } from '@angular/forms';
import { ApiService, ApiError } from '../core/api.service';
import { AvisosService } from '../core/avisos.service';
import { abrir } from '../core/util';

interface Persona {
  asignacion: number; nombre: string; zona: string | null; rol: string; estado: string; estadoTexto: string; color: string;
  resultado: number | null; nota: string | null; telefono: string | null; puedeValidar: boolean;
}
interface Detalle {
  tarea: { id: number; titulo: string; tipoNombre: string; emoji: string; queReportar: string; descripcion: string | null; lugar: string | null;
    fechaTexto: string; estado: string; alcance: string; creador: string; meta: number | null; puntos: number; puntosAsistencia: number };
  conteo: Record<string, number>;
  estados: { clave: string; nombre: string; color: string }[];
  personas: Persona[];
}

@Component({
  selector: 'app-tarea',
  standalone: true,
  imports: [FormsModule, IonContent, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonButton, IonSpinner, IonSegment, IonSegmentButton, IonLabel],
  template: `
  <ion-header>
    <ion-toolbar class="dm">
      <ion-buttons slot="start"><ion-back-button defaultHref="/equipo/tareas" text=""></ion-back-button></ion-buttons>
      <ion-title>{{ d()?.tarea?.titulo ?? 'Tarea' }}</ion-title>
    </ion-toolbar>
  </ion-header>
  <ion-content class="ion-padding">
    @if (error()) { <div class="tarjeta vacio">{{ error() }}</div> }
    @if (!d() && !error()) { <div class="vacio"><ion-spinner name="crescent"></ion-spinner></div> }
    @if (d(); as d) {
      <section class="tarjeta">
        <h2>{{ d.tarea.emoji }} {{ d.tarea.titulo }}</h2>
        <p class="small muted">{{ d.tarea.tipoNombre }}{{ d.tarea.fechaTexto ? ' · ' + d.tarea.fechaTexto : '' }}{{ d.tarea.lugar ? ' · 📍 ' + d.tarea.lugar : '' }} · por {{ d.tarea.creador }}</p>
        @if (d.tarea.descripcion) { <p>{{ d.tarea.descripcion }}</p> }
        <p class="small">🏅 {{ d.tarea.puntos }} puntos al responsable{{ d.tarea.puntosAsistencia ? ' · ' + d.tarea.puntosAsistencia + ' al asistente' : '' }}
          @if (d.tarea.meta) { · 🎯 meta {{ d.tarea.meta }} }</p>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          @for (e of d.estados; track e.clave) { @if (d.conteo[e.clave]) { <span class="etq" [class]="e.color">{{ e.nombre }}: {{ d.conteo[e.clave] }}</span> } }
        </div>
        @if (d.conteo['hecha']) {
          <ion-button expand="block" color="success" style="margin-top:12px" (click)="validarTodas()">Validar los {{ d.conteo['hecha'] }} reportes</ion-button>
        }
      </section>

      <ion-segment [(ngModel)]="filtro">
        <ion-segment-button value="hecha"><ion-label>Validar</ion-label></ion-segment-button>
        <ion-segment-button value="activas"><ion-label>En curso</ion-label></ion-segment-button>
        <ion-segment-button value="todas"><ion-label>Todas</ion-label></ion-segment-button>
      </ion-segment>

      @for (p of filtradas(); track p.asignacion) {
        <section class="tarjeta">
          <div style="display:flex;justify-content:space-between;gap:8px">
            <div><b>{{ p.nombre }}</b><div class="small muted">{{ p.zona }} · {{ p.rol }}</div></div>
            <span class="etq" [class]="p.color" style="align-self:flex-start">{{ p.estadoTexto }}</span>
          </div>
          @if (p.resultado !== null || p.nota) {
            <p class="small" style="margin:8px 0 0">📝 {{ d.tarea.queReportar }}: <b>{{ p.resultado ?? '—' }}</b>{{ p.nota ? ' · «' + p.nota + '»' : '' }}</p>
          }
          <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
            @if (p.puedeValidar && p.estado !== 'validada') { <ion-button size="small" color="success" (click)="validar(p, true)">Validar</ion-button> }
            @if (p.puedeValidar && p.estado !== 'no_valida') { <ion-button size="small" color="medium" fill="outline" (click)="validar(p, false)">No válida</ion-button> }
            @if (p.telefono) { <ion-button size="small" fill="clear" (click)="wa(p.telefono)">WhatsApp</ion-button> }
          </div>
        </section>
      } @empty { <p class="vacio">Nadie en este filtro.</p> }
    }
  </ion-content>`,
})
export class TareaPage {
  private api = inject(ApiService);
  private avisos = inject(AvisosService);
  private alertas = inject(AlertController);
  private ruta = inject(ActivatedRoute);
  d = signal<Detalle | null>(null);
  error = signal('');
  filtro = 'hecha';
  private filtroSig = signal('hecha');

  filtradas = computed(() => {
    const ps = this.d()?.personas ?? [];
    const f = this.filtroSig();
    if (f === 'hecha') return ps.filter(p => p.estado === 'hecha');
    if (f === 'activas') return ps.filter(p => ['pendiente', 'aceptada'].includes(p.estado));
    return ps;
  });

  ngDoCheck() { if (this.filtro !== this.filtroSig()) this.filtroSig.set(this.filtro); }

  ionViewWillEnter() { void this.cargar(); }

  async cargar() {
    try {
      const r = await this.api.get<Detalle>('equipo/tarea', { id: this.ruta.snapshot.paramMap.get('id') ?? '0' });
      this.d.set(r);
      if (!r.conteo['hecha'] && this.filtro === 'hecha') this.filtro = 'todas';
    } catch (e) { this.error.set((e as ApiError).message); }
  }

  async validar(p: Persona, valida: boolean) {
    try {
      const r = await this.avisos.conCarga(() => this.api.post<{ msg: string }>('equipo/tareas/validar', { asignacion: p.asignacion, valida }));
      await this.avisos.ok(r.msg); await this.cargar();
    } catch (e) { await this.avisos.error((e as ApiError).message); }
  }

  async validarTodas() {
    const d = this.d(); if (!d) return;
    const a = await this.alertas.create({
      header: '¿Validar todos?', message: `Se validarán ${d.conteo['hecha']} reportes y cada persona sumará sus puntos.`,
      buttons: [{ text: 'Cancelar', role: 'cancel' }, { text: 'Validar', handler: () => { void this.hacerValidarTodas(d.tarea.id); } }],
    });
    await a.present();
  }

  private async hacerValidarTodas(id: number) {
    try {
      const r = await this.avisos.conCarga(() => this.api.post<{ msg: string }>('equipo/tareas/validar-todas', { tarea: id }));
      await this.avisos.ok(r.msg); await this.cargar();
    } catch (e) { await this.avisos.error((e as ApiError).message); }
  }

  wa(tel: string) { void abrir('https://wa.me/57' + tel); }
}
