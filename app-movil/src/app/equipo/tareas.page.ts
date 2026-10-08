import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import {
  IonContent, IonHeader, IonToolbar, IonTitle, IonSegment, IonSegmentButton, IonLabel, IonRefresher, IonRefresherContent, IonSpinner,
  IonButton, IonIcon,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { addOutline } from 'ionicons/icons';
import { ApiService, ApiError } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { FilaTarea, Resumen } from '../core/modelos';
import { abrir } from '../core/util';

@Component({
  selector: 'app-tareas',
  standalone: true,
  imports: [FormsModule, RouterLink, IonContent, IonHeader, IonToolbar, IonTitle, IonSegment, IonSegmentButton, IonLabel, IonRefresher,
    IonRefresherContent, IonSpinner, IonButton, IonIcon],
  template: `
  <ion-header>
    <ion-toolbar class="dm"><ion-title>Tareas de la red</ion-title></ion-toolbar>
    <ion-toolbar>
      <ion-segment [(ngModel)]="estado" (ionChange)="cargar()">
        <ion-segment-button value="abierta"><ion-label>Abiertas</ion-label></ion-segment-button>
        <ion-segment-button value="cerrada"><ion-label>Cerradas</ion-label></ion-segment-button>
        <ion-segment-button value="todas"><ion-label>Todas</ion-label></ion-segment-button>
      </ion-segment>
    </ion-toolbar>
  </ion-header>
  <ion-content class="ion-padding">
    <ion-refresher slot="fixed" (ionRefresh)="cargar($event)"><ion-refresher-content></ion-refresher-content></ion-refresher>
    @if (resumen(); as r) {
      <div class="kpis">
        <div class="kpi"><b>{{ r.abiertas }}</b><span>abiertas</span></div>
        <div class="kpi"><b>{{ r.por_validar }}</b><span>por validar</span></div>
        <div class="kpi"><b>{{ r.personas }}</b><span>personas activas</span></div>
      </div>
    }
    @if (auth.usuario()?.panelWeb) {
      <ion-button expand="block" fill="outline" (click)="nueva()"><ion-icon slot="start" name="add-outline"></ion-icon>Crear tarea (panel web)</ion-button>
    }
    @if (error()) { <div class="tarjeta vacio">{{ error() }}</div> }
    @if (cargando() && !tareas().length) { <div class="vacio"><ion-spinner name="crescent"></ion-spinner></div> }
    @for (t of tareas(); track t.id) {
      <section class="tarjeta" [routerLink]="['/equipo/tareas', t.id]" style="cursor:pointer">
        <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
          <h2 style="margin:0">{{ t.emoji }} {{ t.titulo }}</h2>
          @if (t.porValidar) { <span class="etq oro">{{ t.porValidar }} por validar</span> }
          @else if (t.estado !== 'abierta') { <span class="etq gris">{{ t.estado }}</span> }
        </div>
        <p class="small muted" style="margin:6px 0">{{ t.tipoNombre }}{{ t.fechaTexto ? ' · ' + t.fechaTexto : '' }} · por {{ t.creador }}</p>
        <div class="small">👥 {{ t.total }} convocados · ✅ {{ t.aceptadas }} aceptaron · 🏅 {{ t.validadas }} validadas
          @if (t.meta) { · 🎯 {{ t.resultado }}/{{ t.meta }} }</div>
        @if (t.meta) { <div class="barra" style="margin-top:8px"><span [style.width.%]="pct(t)"></span></div> }
      </section>
    } @empty { @if (!cargando() && !error()) { <p class="vacio">No hay tareas {{ estado === 'todas' ? '' : estado + 's' }}.</p> } }
  </ion-content>`,
})
export class TareasPage {
  private api = inject(ApiService);
  auth = inject(AuthService);
  estado = 'abierta';
  tareas = signal<FilaTarea[]>([]);
  resumen = signal<NonNullable<Resumen['tareas']> | null>(null);
  cargando = signal(false);
  error = signal('');

  constructor() { addIcons({ addOutline }); }
  ionViewWillEnter() { void this.cargar(); }

  async cargar(ev?: CustomEvent) {
    this.cargando.set(true);
    try {
      const r = await this.api.get<{ resumen: NonNullable<Resumen['tareas']>; tareas: FilaTarea[] }>('equipo/tareas', { estado: this.estado });
      this.tareas.set(r.tareas); this.resumen.set(r.resumen); this.error.set('');
    } catch (e) { this.error.set((e as ApiError).message); }
    finally { this.cargando.set(false); (ev?.target as HTMLIonRefresherElement | undefined)?.complete?.(); }
  }

  pct(t: FilaTarea) { return t.meta ? Math.min(100, Math.round(t.resultado * 100 / t.meta)) : 0; }
  nueva() { const w = this.auth.usuario()?.panelWeb; if (w) void abrir(w + '?url=tareas/crear'); }
}
