import { Injectable, inject } from '@angular/core';
import { ToastController, LoadingController } from '@ionic/angular/standalone';

/** Mensajes cortos (toasts) y "cargando…". */
@Injectable({ providedIn: 'root' })
export class AvisosService {
  private toasts = inject(ToastController);
  private loading = inject(LoadingController);

  async ok(msg: string) {
    const t = await this.toasts.create({ message: msg, duration: 2500, color: 'success', position: 'top' });
    await t.present();
  }

  async error(msg: string) {
    const t = await this.toasts.create({ message: msg, duration: 4000, color: 'danger', position: 'top' });
    await t.present();
  }

  /** Ejecuta la promesa mostrando "cargando…". */
  async conCarga<T>(trabajo: () => Promise<T>, mensaje = 'Un momento…'): Promise<T> {
    const l = await this.loading.create({ message: mensaje, spinner: 'crescent' });
    await l.present();
    try { return await trabajo(); } finally { await l.dismiss(); }
  }
}
