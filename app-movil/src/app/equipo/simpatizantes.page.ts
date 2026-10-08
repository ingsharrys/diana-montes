import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import {
  IonContent, IonHeader, IonToolbar, IonTitle, IonSearchbar, IonSegment, IonSegmentButton, IonLabel, IonList, IonItem,
  IonInfiniteScroll, IonInfiniteScrollContent, IonRefresher, IonRefresherContent, IonSpinner, IonBadge,
} from '@ionic/angular/standalone';
import { ApiService, ApiError } from '../core/api.service';
import { FilaSimpatizante } from '../core/modelos';

interface Respuesta { conteo: Record<string, number>; hayMas: boolean; lista: FilaSimpatizante[]; }

@Component({
  selector: 'app-simpatizantes',
  standalone: true,
  imports: [FormsModule, RouterLink, IonContent, IonHeader, IonToolbar, IonTitle, IonSearchbar, IonSegment, IonSegmentButton, IonLabel,
    IonList, IonItem, IonInfiniteScroll, IonInfiniteScrollContent, IonRefresher, IonRefresherContent, IonSpinner, IonBadge],
  template: `
  <ion-header>
    <ion-toolbar class="dm"><ion-title>Simpatizantes</ion-title></ion-toolbar>
    <ion-toolbar>
      <ion-searchbar placeholder="Buscar nombre o documento" [debounce]="350" [(ngModel)]="q" (ionInput)="reiniciar()"></ion-searchbar>
    </ion-toolbar>
    <ion-toolbar>
      <ion-segment [(ngModel)]="estado" (ionChange)="reiniciar()" scrollable>
        <ion-segment-button value="todos"><ion-label>Todos {{ conteo()['todos'] ?? '' }}</ion-label></ion-segment-button>
        <ion-segment-button value="pendientes"><ion-label>Por completar {{ conteo()['pendientes'] ?? '' }}</ion-label></ion-segment-button>
        <ion-segment-button value="sin_verificar"><ion-label>Sin llamar {{ conteo()['sin_verificar'] ?? '' }}</ion-label></ion-segment-button>
      </ion-segment>
    </ion-toolbar>
  </ion-header>
  <ion-content>
    <ion-refresher slot="fixed" (ionRefresh)="reiniciar($event)"><ion-refresher-content></ion-refresher-content></ion-refresher>
    @if (error()) { <div class="tarjeta vacio" style="margin:14px">{{ error() }}</div> }
    @if (cargando() && !lista().length) { <div class="vacio"><ion-spinner name="crescent"></ion-spinner></div> }
    @if (!cargando() && !lista().length && !error()) { <p class="vacio">No hay registros con ese filtro.</p> }
    <ion-list>
      @for (s of lista(); track s.id) {
        <ion-item [routerLink]="['/equipo/simpatizantes', s.id]" detail>
          <ion-label>
            <h2 style="font-weight:700">{{ s.nombre }}</h2>
            <p>{{ s.zona }} · {{ s.telefono }}</p>
            <p>{{ s.lider }}</p>
          </ion-label>
          <div slot="end" style="display:flex;flex-direction:column;gap:4px;align-items:flex-end">
            <ion-badge [color]="s.nivel === 'indeciso' ? 'medium' : (['voto_seguro','voluntario','testigo'].includes(s.nivel) ? 'success' : 'secondary')">{{ s.nivelNombre }}</ion-badge>
            @if (s.verificado) { <span class="etq verde">✓ verificado</span> } @else if (!s.conPuesto) { <span class="etq oro">sin puesto</span> }
          </div>
        </ion-item>
      }
    </ion-list>
    <ion-infinite-scroll (ionInfinite)="mas($event)" [disabled]="!hayMas()"><ion-infinite-scroll-content></ion-infinite-scroll-content></ion-infinite-scroll>
  </ion-content>`,
})
export class SimpatizantesPage {
  private api = inject(ApiService);
  q = '';
  estado = 'todos';
  lista = signal<FilaSimpatizante[]>([]);
  conteo = signal<Partial<Record<string, number>>>({});
  hayMas = signal(false);
  cargando = signal(false);
  error = signal('');

  ionViewWillEnter() { void this.reiniciar(); }

  async reiniciar(ev?: CustomEvent) {
    await this.traer(0, true);
    (ev?.target as HTMLIonRefresherElement | undefined)?.complete?.();
  }

  async mas(ev: CustomEvent) {
    await this.traer(this.lista().length, false);
    (ev.target as HTMLIonInfiniteScrollElement).complete();
  }

  private async traer(desde: number, nuevo: boolean) {
    this.cargando.set(true);
    try {
      const r = await this.api.get<Respuesta>('equipo/simpatizantes', { q: this.q.trim(), estado: this.estado, desde });
      this.lista.set(nuevo ? r.lista : [...this.lista(), ...r.lista]);
      this.conteo.set(r.conteo);
      this.hayMas.set(r.hayMas);
      this.error.set('');
    } catch (e) { this.error.set((e as ApiError).message); }
    finally { this.cargando.set(false); }
  }
}
