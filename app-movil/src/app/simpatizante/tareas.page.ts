import { Component, inject, signal } from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { IonContent, IonHeader, IonToolbar, IonTitle, IonRefresher, IonRefresherContent, IonButton, IonSpinner, AlertController } from '@ionic/angular/standalone';
import { ApiService, ApiError } from '../core/api.service';
import { AvisosService } from '../core/avisos.service';
import { MisTareas, Tarea } from '../core/modelos';
import { abrir } from '../core/util';

@Component({
  selector: 'app-mis-tareas',
  standalone: true,
  imports: [NgTemplateOutlet, IonContent, IonHeader, IonToolbar, IonTitle, IonRefresher, IonRefresherContent, IonButton, IonSpinner],
  templateUrl: './tareas.page.html',
})
export class MisTareasPage {
  private api = inject(ApiService);
  private avisos = inject(AvisosService);
  private alertas = inject(AlertController);
  d = signal<MisTareas | null>(null);
  error = signal('');

  ionViewWillEnter() { void this.cargar(); }

  async cargar(ev?: CustomEvent) {
    try { this.d.set(await this.api.get<MisTareas>('mi/tareas')); this.error.set(''); }
    catch (e) { this.error.set((e as ApiError).message); }
    finally { (ev?.target as HTMLIonRefresherElement | undefined)?.complete(); }
  }

  private async hacer(ruta: string, cuerpo: object) {
    try {
      const r = await this.avisos.conCarga(() => this.api.post<{ msg: string }>(ruta, cuerpo));
      await this.avisos.ok(r.msg);
      await this.cargar();
    } catch (e) { await this.avisos.error((e as ApiError).message); }
  }

  responder(t: Tarea, respuesta: 'aceptar' | 'rechazar') { void this.hacer('mi/tareas/responder', { asignacion: t.asignacion, respuesta }); }
  apuntarme(t: Tarea) { void this.hacer('mi/tareas/apuntarme', { tarea: t.tarea }); }

  async reportar(t: Tarea) {
    const a = await this.alertas.create({
      header: '¿Cómo te fue?',
      subHeader: t.titulo,
      inputs: [
        { name: 'resultado', type: 'number', min: 0, placeholder: '¿Cuántas ' + t.queReportar + '?', value: t.resultado ?? '' },
        { name: 'nota', type: 'textarea', placeholder: 'Comentario (opcional)', value: t.nota ?? '' },
      ],
      buttons: [
        { text: 'Cancelar', role: 'cancel' },
        {
          text: 'Enviar reporte',
          handler: (v: { resultado: string; nota: string }) => {
            if (!/^\d{1,6}$/.test(String(v.resultado ?? '').trim())) { void this.avisos.error('Escribe un número (puede ser 0).'); return false; }
            void this.hacer('mi/tareas/reportar', { asignacion: t.asignacion, resultado: v.resultado, nota: v.nota });
            return true;
          },
        },
      ],
    });
    await a.present();
  }

  abrirWeb(p: string) { const d = this.d(); if (d) void abrir(d.portalWeb + p); }
}
