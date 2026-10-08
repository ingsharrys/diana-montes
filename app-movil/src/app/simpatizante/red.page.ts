import { Component, computed, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { IonContent, IonHeader, IonToolbar, IonTitle, IonRefresher, IonRefresherContent, IonButton, IonIcon, IonSpinner } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { logoWhatsapp, lockClosed } from 'ionicons/icons';
import { ApiService, ApiError } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { MiRed } from '../core/modelos';
import { abrir, iniciales } from '../core/util';

@Component({
  selector: 'app-mi-red',
  standalone: true,
  imports: [DatePipe, IonContent, IonHeader, IonToolbar, IonTitle, IonRefresher, IonRefresherContent, IonButton, IonIcon, IonSpinner],
  template: `
  <ion-header><ion-toolbar class="dm"><ion-title>Mi red</ion-title></ion-toolbar></ion-header>
  <ion-content class="ion-padding">
    <ion-refresher slot="fixed" (ionRefresh)="cargar($event)"><ion-refresher-content></ion-refresher-content></ion-refresher>
    @if (error()) { <div class="tarjeta vacio">{{ error() }}</div> }
    @if (!d() && !error()) { <div class="tarjeta vacio"><ion-spinner name="crescent"></ion-spinner></div> }
    @if (d(); as d) {
      <section class="tarjeta">
        <h2>🤝 Tus invitados <span class="der">{{ d.invitados.length }} directos · {{ d.total }} en total</span></h2>
        @if (!d.invitados.length) { <p class="vacio">Aún no has invitado a nadie. Comparte tu enlace desde Inicio.</p> }
        @for (i of d.invitados; track i.id) {
          <div style="display:flex;gap:10px;align-items:center;padding:9px 0;border-bottom:1px solid var(--dm-linea)">
            <span style="width:36px;height:36px;border-radius:50%;background:#F3EEFD;color:#7C3AED;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;flex:none">{{ ini(i.nombre) }}</span>
            <div style="flex:1;min-width:0">
              <b style="font-size:14px">{{ i.nombre }} {{ i.nivelEmoji }}</b>
              <div class="small muted">{{ i.zona }} · desde {{ i.desde | date:'dd/MM/yyyy' }}{{ i.invitados ? ' · invitó a ' + i.invitados : '' }}</div>
            </div>
            @if (i.telefono) {
              <ion-button fill="clear" color="success" (click)="escribir(i.telefono, i.nombre)" aria-label="Escribir por WhatsApp"><ion-icon slot="icon-only" name="logo-whatsapp"></ion-icon></ion-button>
            }
          </div>
        }
        @if (!d.puedeContactar && d.invitados.length) {
          <p class="small muted" style="margin-top:10px"><ion-icon name="lock-closed"></ion-icon> Escribir por WhatsApp a tus invitados se desbloquea en el nivel {{ d.nivelParaContactar }}.</p>
        }
      </section>
      <section class="tarjeta">
        <h2>🌳 Toda tu red</h2>
        @if (!d.puedeVerTodo) {
          <p class="vacio"><ion-icon name="lock-closed"></ion-icon> Llega al nivel {{ d.nivelParaVerTodo }} para ver a los invitados de tus invitados.</p>
        } @else if (!d.red?.length) {
          <p class="vacio">Cuando tus invitados inviten a otras personas, aparecerán aquí.</p>
        } @else {
          @for (c of capas(); track c.capa) {
            <div style="font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#7C3AED;margin:12px 0 4px">{{ c.capa }}.º nivel de tu red</div>
            @for (r of c.personas; track $index) {
              <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--dm-linea);font-size:14px">{{ r.nombre }}<span class="small muted">invitado por {{ r.invito }}</span></div>
            }
          }
        }
      </section>
    }
  </ion-content>`,
})
export class MiRedPage {
  private api = inject(ApiService);
  private auth = inject(AuthService);
  d = signal<MiRed | null>(null);
  error = signal('');
  ini = iniciales;
  capas = computed(() => {
    const grupos = new Map<number, { nombre: string; invito: string }[]>();
    for (const r of this.d()?.red ?? []) grupos.set(r.capa, [...(grupos.get(r.capa) ?? []), r]);
    return [...grupos.entries()].map(([capa, personas]) => ({ capa, personas }));
  });

  constructor() { addIcons({ logoWhatsapp, lockClosed }); }
  ionViewWillEnter() { void this.cargar(); }

  async cargar(ev?: CustomEvent) {
    try { this.d.set(await this.api.get<MiRed>('mi/red')); this.error.set(''); }
    catch (e) { this.error.set((e as ApiError).message); }
    finally { (ev?.target as HTMLIonRefresherElement | undefined)?.complete(); }
  }

  escribir(tel: string, nombre: string) {
    const yo = this.auth.simpatizante()?.primerNombre ?? '';
    const texto = `¡Hola, ${nombre.split(' ')[0]}! Te escribe ${yo}, de la red de Diana Lucía Montes. `;
    void abrir(`https://wa.me/57${tel}?text=${encodeURIComponent(texto)}`);
  }
}
