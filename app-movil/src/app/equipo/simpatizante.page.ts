import { Component, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import {
  IonContent, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonList, IonItem, IonLabel, IonSelect, IonSelectOption,
  IonInput, IonToggle, IonButton, IonIcon, IonSpinner,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { callOutline, logoWhatsapp } from 'ionicons/icons';
import { ApiService, ApiError } from '../core/api.service';
import { AvisosService } from '../core/avisos.service';
import { Catalogos, DetalleSimpatizante } from '../core/modelos';
import { CatalogosService } from './catalogos.service';
import { abrir, soloDigitos } from '../core/util';

@Component({
  selector: 'app-simpatizante',
  standalone: true,
  imports: [DatePipe, FormsModule, IonContent, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonList, IonItem, IonLabel,
    IonSelect, IonSelectOption, IonInput, IonToggle, IonButton, IonIcon, IonSpinner],
  templateUrl: './simpatizante.page.html',
})
export class SimpatizantePage {
  private api = inject(ApiService);
  private avisos = inject(AvisosService);
  private catalogos = inject(CatalogosService);
  private ruta = inject(ActivatedRoute);
  s = signal<DetalleSimpatizante | null>(null);
  cat = signal<Catalogos | null>(null);
  error = signal('');
  form = { puesto_id: null as number | null, mesa: '', nivel: '', verificado: false };

  constructor() { addIcons({ callOutline, logoWhatsapp }); }

  async ionViewWillEnter() {
    try {
      const [r, c] = await Promise.all([
        this.api.get<{ simpatizante: DetalleSimpatizante }>('equipo/simpatizante', { id: this.ruta.snapshot.paramMap.get('id') ?? '0' }),
        this.catalogos.obtener(),
      ]);
      this.s.set(r.simpatizante);
      this.cat.set(c);
      this.form = { puesto_id: r.simpatizante.puestoId, mesa: r.simpatizante.mesa ?? '', nivel: r.simpatizante.nivel, verificado: r.simpatizante.verificado };
    } catch (e) { this.error.set((e as ApiError).message); }
  }

  limpiarMesa(ev: Event) {
    const el = ev.target as HTMLIonInputElement;
    this.form.mesa = soloDigitos(String(el.value ?? ''), 4);
    el.value = this.form.mesa;
  }

  async guardar() {
    const s = this.s(); if (!s) return;
    try {
      const r = await this.avisos.conCarga(() => this.api.post<{ msg: string }>('equipo/verificar', { id: s.id, ...this.form }));
      await this.avisos.ok(r.msg);
    } catch (e) { await this.avisos.error((e as ApiError).message); }
  }

  llamar() { const t = this.s()?.telefonoCompleto; if (t) void abrir('tel:' + t); }
  whatsapp() { const t = this.s()?.telefonoCompleto; if (t) void abrir('https://wa.me/57' + t); }
}
