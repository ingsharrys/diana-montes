import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  IonContent, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonSegment, IonSegmentButton, IonLabel, IonSearchbar,
  IonFab, IonFabButton, IonIcon, IonRefresher, IonRefresherContent, IonSpinner, ModalController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { add, chevronForward, locationOutline, flagOutline, briefcaseOutline } from 'ionicons/icons';
import { ApiService, ApiError } from '../core/api.service';
import { CatalogosAdmin, ProfesionAdmin, PuestoAdmin, ZonaAdmin } from '../core/modelos';
import { CatalogosService } from './catalogos.service';
import { CatalogoFormComponent, TipoCatalogo } from './catalogo-form.component';

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const normal = (v: string) => v.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

interface Fila { id: number; nombre: string; detalle: string; grupo: string; uso: number; item: ZonaAdmin | PuestoAdmin | ProfesionAdmin; }

/** Barrios y veredas, puestos de votación y profesiones (solo dirección). */
@Component({
  selector: 'app-admin-catalogos',
  standalone: true,
  imports: [FormsModule, IonContent, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonSegment, IonSegmentButton, IonLabel,
    IonSearchbar, IonFab, IonFabButton, IonIcon, IonRefresher, IonRefresherContent, IonSpinner],
  template: `
  <ion-header class="ion-no-border">
    <ion-toolbar class="dm">
      <ion-buttons slot="start"><ion-back-button defaultHref="/equipo/mas" text=""></ion-back-button></ion-buttons>
      <ion-title>Catálogos</ion-title>
    </ion-toolbar>
    <ion-toolbar class="blanca">
      <ion-segment [ngModel]="tipo()" (ngModelChange)="tipo.set($event); q.set('')">
        <ion-segment-button value="zona"><ion-label>Zonas <b>{{ datos()?.zonas?.length ?? '' }}</b></ion-label></ion-segment-button>
        <ion-segment-button value="puesto"><ion-label>Puestos <b>{{ datos()?.puestos?.length ?? '' }}</b></ion-label></ion-segment-button>
        <ion-segment-button value="profesion"><ion-label>Profesiones <b>{{ datos()?.profesiones?.length ?? '' }}</b></ion-label></ion-segment-button>
      </ion-segment>
    </ion-toolbar>
    <ion-toolbar class="blanca">
      <ion-searchbar [value]="q()" (ionInput)="q.set($any($event).detail.value ?? '')" [placeholder]="'Buscar ' + nombres[tipo()] + '…'"></ion-searchbar>
    </ion-toolbar>
  </ion-header>
  <ion-content>
    <ion-refresher slot="fixed" (ionRefresh)="cargar($event)"><ion-refresher-content></ion-refresher-content></ion-refresher>
    @if (error()) { <div class="tarjeta vacio" style="margin:14px">{{ error() }}</div> }
    @if (!datos() && !error()) { <div class="vacio" style="padding-top:40px"><ion-spinner name="crescent"></ion-spinner></div> }
    @for (g of filas(); track g.grupo) {
      @if (g.grupo) { <div class="grupo">{{ g.grupo }} <span>{{ g.items.length }}</span></div> }
      <div class="lista">
        @for (f of g.items; track f.id) {
          <button type="button" class="fila" (click)="abrir(f.item)">
            <span class="ico" [class]="tipo()"><ion-icon [name]="iconos[tipo()]"></ion-icon></span>
            <span class="txt"><b>{{ f.nombre }}</b>@if (f.detalle) { <small>{{ f.detalle }}</small> }</span>
            <span class="uso" [class.cero]="!f.uso">{{ f.uso }}</span>
            <ion-icon name="chevron-forward" class="flecha"></ion-icon>
          </button>
        }
      </div>
    } @empty { @if (datos()) { <p class="vacio" style="padding-top:30px">No hay resultados.</p> } }
    <div style="height:90px"></div>
    <ion-fab slot="fixed" vertical="bottom" horizontal="end">
      <ion-fab-button (click)="abrir(null)" aria-label="Agregar"><ion-icon name="add"></ion-icon></ion-fab-button>
    </ion-fab>
  </ion-content>`,
  styles: [`
    ion-toolbar.blanca { --background: #fff; }
    ion-segment-button { text-transform: none; letter-spacing: 0; font-size: 14px; font-weight: 700; min-width: 0; --padding-start: 4px; --padding-end: 4px; }
    ion-segment-button b { font-weight: 800; opacity: .5; margin-left: 2px; }
    ion-searchbar { --border-radius: 14px; --background: #F3F2F8; --box-shadow: none; padding-top: 0; }
    ion-content { --background: #F6F5FA; }
    .grupo { color: #5A6072; font-size: 11.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; padding: 16px 20px 6px; }
    .grupo span { float: right; }
    .lista { background: #fff; margin: 0 14px; border-radius: 18px; overflow: hidden; box-shadow: 0 1px 2px rgba(16,18,38,.04), 0 8px 22px rgba(16,18,38,.05); }
    .grupo:first-child { padding-top: 12px; }
    .fila { display: flex; align-items: center; gap: 12px; width: 100%; background: #fff; border: 0; border-bottom: 1px solid #F0EFF5; padding: 12px 14px; font: inherit; text-align: left; color: #101226; }
    .fila:last-child { border-bottom: 0; }
    .fila:active { background: #FDF2F7; }
    .ico { width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; font-size: 19px; flex: none; background: #FDEBF3; color: #E0186C; }
    .ico.puesto { background: #E7F8EE; color: #16A34A; }
    .ico.profesion { background: #F1EBFE; color: #7C3AED; }
    .txt { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    .txt b { font-weight: 600; font-size: 15px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .txt small { color: #6B7084; font-size: 12.5px; margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .uso { min-width: 28px; padding: 3px 8px; border-radius: 99px; background: #F3F2F8; color: #101226; font-size: 12px; font-weight: 800; text-align: center; }
    .uso.cero { color: #A9ACBD; }
    .flecha { color: #C3C5D3; }
    ion-fab-button { --background: linear-gradient(120deg, #E0186C, #7C3AED); --box-shadow: 0 10px 24px rgba(224,24,108,.35); }
  `],
})
export class AdminCatalogosPage {
  private api = inject(ApiService);
  private modal = inject(ModalController);
  private catalogos = inject(CatalogosService);
  datos = signal<CatalogosAdmin | null>(null);
  error = signal('');
  tipo = signal<TipoCatalogo>('zona');
  q = signal('');
  nombres: Record<TipoCatalogo, string> = { zona: 'barrio o vereda', puesto: 'puesto', profesion: 'profesión' };
  iconos: Record<TipoCatalogo, string> = { zona: 'location-outline', puesto: 'flag-outline', profesion: 'briefcase-outline' };

  /** Lista del tipo elegido, filtrada y agrupada. El número a la derecha es cuántas personas lo usan. */
  filas = computed(() => {
    const d = this.datos(); if (!d) return [];
    const q = normal(this.q());
    let todas: Fila[];
    if (this.tipo() === 'zona') todas = d.zonas.map(z => ({ id: z.id, nombre: z.nombre, detalle: z.clase ?? '', grupo: z.grupo, uso: z.uso, item: z }));
    else if (this.tipo() === 'puesto') todas = d.puestos.map(p => ({
      id: p.id, nombre: p.nombre, grupo: '', uso: p.uso, item: p,
      detalle: [p.zona, p.mesas ? p.mesas + ' mesas' : null, p.potencial ? p.potencial.toLocaleString('es-CO') + ' votantes' : null].filter(Boolean).join(' · '),
    }));
    else todas = d.profesiones.map(p => ({ id: p.id, nombre: p.nombre, grupo: '', uso: p.uso, item: p, detalle: p.dia ? 'Su día: ' + this.fecha(p.dia) : 'Sin día de celebración' }));
    const r: { grupo: string; items: Fila[] }[] = [];
    for (const f of todas) {
      if (q && !normal(f.nombre + ' ' + f.detalle).includes(q)) continue;
      let g = r.find(x => x.grupo === f.grupo);
      if (!g) r.push(g = { grupo: f.grupo, items: [] });
      g.items.push(f);
    }
    return r;
  });

  constructor() { addIcons({ add, chevronForward, locationOutline, flagOutline, briefcaseOutline }); }
  ionViewWillEnter() { void this.cargar(); }

  fecha(mmdd: string) { const [m, d] = mmdd.split('-'); return `${+d} ${MESES[+m - 1]}`; }

  async cargar(ev?: CustomEvent) {
    try { this.datos.set(await this.api.get<CatalogosAdmin>('equipo/catalogos/admin')); this.error.set(''); }
    catch (e) { this.error.set((e as ApiError).message); }
    finally { (ev?.target as HTMLIonRefresherElement | undefined)?.complete?.(); }
  }

  async abrir(item: ZonaAdmin | PuestoAdmin | ProfesionAdmin | null) {
    const d = this.datos(); if (!d) return;
    const m = await this.modal.create({
      component: CatalogoFormComponent, componentProps: { tipo: this.tipo(), item, datos: d },
      breakpoints: [0, 0.75, 1], initialBreakpoint: this.tipo() === 'puesto' ? 1 : 0.75, handle: true,
    });
    await m.present();
    const { role } = await m.onWillDismiss();
    if (role === 'guardado') {
      await this.cargar();
      void this.catalogos.obtener(true).catch(() => undefined);   // el formulario de registro ve los cambios
    }
  }
}
