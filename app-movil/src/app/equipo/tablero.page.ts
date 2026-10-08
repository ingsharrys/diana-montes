import { Component, computed, inject, signal } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { IonContent, IonRefresher, IonRefresherContent, IonSpinner, IonButton } from '@ionic/angular/standalone';
import { ApiService, ApiError } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { Resumen } from '../core/modelos';

@Component({
  selector: 'app-tablero',
  standalone: true,
  imports: [DecimalPipe, IonContent, IonRefresher, IonRefresherContent, IonSpinner, IonButton],
  templateUrl: './tablero.page.html',
})
export class TableroPage {
  private api = inject(ApiService);
  auth = inject(AuthService);
  d = signal<Resumen | null>(null);
  error = signal('');
  maxSemana = computed(() => Math.max(1, ...(this.d()?.semana ?? []).map(s => s.total)));
  totalSemana = computed(() => (this.d()?.semana ?? []).reduce((a, s) => a + s.total, 0));
  maxCompromiso = computed(() => Math.max(1, ...(this.d()?.compromiso ?? []).map(c => c.total)));
  maxZona = computed(() => Math.max(1, ...(this.d()?.porZona ?? []).map(z => z.total)));

  ionViewWillEnter() { void this.cargar(); }

  async cargar(ev?: CustomEvent) {
    try { this.d.set(await this.api.get<Resumen>('equipo/resumen')); this.error.set(''); }
    catch (e) { this.error.set((e as ApiError).message); }
    finally { (ev?.target as HTMLIonRefresherElement | undefined)?.complete(); }
  }

  pct(a: number, b: number) { return b > 0 ? Math.round(a * 100 / b) : 0; }
}
