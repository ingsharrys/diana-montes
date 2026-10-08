import { Component, Input, computed, inject, signal } from '@angular/core';
import {
  IonHeader, IonToolbar, IonTitle, IonButtons, IonButton, IonSearchbar, IonContent, IonIcon, ModalController,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { checkmarkCircle, closeOutline } from 'ionicons/icons';

export interface OpcionSelector { id: number; nombre: string; grupo?: string | null; detalle?: string | null; }

const normal = (v: string) => v.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

/**
 * Lista con buscador que se abre como hoja inferior (barrios, profesiones, puestos, líderes).
 * Se abre con ModalController y devuelve la opción elegida en `data` (o null si la quitan).
 */
@Component({
  selector: 'app-selector',
  standalone: true,
  imports: [IonHeader, IonToolbar, IonTitle, IonButtons, IonButton, IonSearchbar, IonContent, IonIcon],
  template: `
  <ion-header class="ion-no-border">
    <ion-toolbar>
      <ion-title>{{ titulo }}</ion-title>
      <ion-buttons slot="end"><ion-button (click)="cerrar()"><ion-icon slot="icon-only" name="close-outline"></ion-icon></ion-button></ion-buttons>
    </ion-toolbar>
    <ion-toolbar>
      <ion-searchbar [placeholder]="buscar" [debounce]="0" (ionInput)="q.set($any($event).detail.value ?? '')" animated></ion-searchbar>
    </ion-toolbar>
  </ion-header>
  <ion-content>
    @if (opcional && !q()) {
      <button class="opcion ninguna" type="button" (click)="elegir(null)">{{ textoNinguno }}</button>
    }
    @for (g of grupos(); track g.grupo) {
      @if (g.grupo) { <div class="grupo">{{ g.grupo }} <span>{{ g.items.length }}</span></div> }
      @for (o of g.items; track o.id) {
        <button class="opcion" type="button" [class.activa]="o.id === seleccion" (click)="elegir(o)">
          <span class="txt"><b>{{ o.nombre }}</b>@if (o.detalle) { <small>{{ o.detalle }}</small> }</span>
          @if (o.id === seleccion) { <ion-icon name="checkmark-circle" color="primary"></ion-icon> }
        </button>
      }
    } @empty {
      <div class="vacio">No encontramos «{{ q() }}».</div>
    }
  </ion-content>`,
  styles: [`
    ion-toolbar { --background: #fff; }
    ion-searchbar { --border-radius: 14px; --background: #F3F2F8; --box-shadow: none; padding-top: 0; }
    .grupo { position: sticky; top: 0; z-index: 1; background: #F6F5FA; color: #5A6072; font-size: 11.5px; font-weight: 800;
             letter-spacing: .06em; text-transform: uppercase; padding: 8px 18px; }
    .grupo span { float: right; font-weight: 700; }
    .opcion { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; background: #fff; border: 0;
              border-bottom: 1px solid #F0EFF5; padding: 14px 18px; font: inherit; color: #101226; }
    .opcion:active { background: #FDEBF3; }
    .opcion.activa { background: #FDF2F7; }
    .opcion .txt { flex: 1; display: flex; flex-direction: column; }
    .opcion b { font-weight: 600; font-size: 15.5px; }
    .opcion small { color: #5A6072; font-size: 12.5px; margin-top: 2px; }
    .opcion ion-icon { font-size: 22px; }
    .ninguna { color: #5A6072; font-style: italic; }
  `],
})
export class SelectorComponent {
  private hoja = inject(ModalController);
  @Input() titulo = 'Elegir';
  @Input() buscar = 'Buscar…';
  @Input() opciones: OpcionSelector[] = [];
  @Input() seleccion: number | null = null;
  @Input() opcional = false;
  @Input() textoNinguno = 'Ninguno';
  q = signal('');

  grupos = computed(() => {
    const q = normal(this.q());
    const r: { grupo: string; items: OpcionSelector[] }[] = [];
    for (const o of this.opciones) {
      if (q && !normal(o.nombre + ' ' + (o.detalle ?? '')).includes(q)) continue;
      const g = o.grupo ?? '';
      let x = r.find(y => y.grupo === g);
      if (!x) r.push(x = { grupo: g, items: [] });
      x.items.push(o);
    }
    return r;
  });

  constructor() { addIcons({ checkmarkCircle, closeOutline }); }
  elegir(o: OpcionSelector | null) { void this.hoja.dismiss(o, 'elegir'); }
  cerrar() { void this.hoja.dismiss(undefined, 'cancel'); }
}

/** Abre el selector como hoja inferior y devuelve la opción (null = "ninguno", undefined = canceló). */
export async function abrirSelector(modal: ModalController, props: Partial<SelectorComponent>): Promise<OpcionSelector | null | undefined> {
  const m = await modal.create({
    component: SelectorComponent, componentProps: props,
    breakpoints: [0, 0.6, 0.95], initialBreakpoint: 0.95, handle: true,
  });
  await m.present();
  const { data, role } = await m.onWillDismiss<OpcionSelector | null>();
  return role === 'elegir' ? data : undefined;
}
