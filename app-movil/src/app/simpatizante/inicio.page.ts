import { Component, inject, signal } from '@angular/core';
import { IonContent, IonRefresher, IonRefresherContent, IonButton, IonIcon, IonSpinner } from '@ionic/angular/standalone';
import { Share } from '@capacitor/share';
import { addIcons } from 'ionicons';
import { logoWhatsapp, copyOutline, shareSocialOutline, lockClosed, checkmarkCircle } from 'ionicons/icons';
import { ApiService, ApiError } from '../core/api.service';
import { AvisosService } from '../core/avisos.service';
import { MiInicio } from '../core/modelos';
import { abrir } from '../core/util';

@Component({
  selector: 'app-mi-inicio',
  standalone: true,
  imports: [IonContent, IonRefresher, IonRefresherContent, IonButton, IonIcon, IonSpinner],
  templateUrl: './inicio.page.html',
})
export class MiInicioPage {
  private api = inject(ApiService);
  private avisos = inject(AvisosService);
  d = signal<MiInicio | null>(null);
  error = signal('');

  constructor() { addIcons({ logoWhatsapp, copyOutline, shareSocialOutline, lockClosed, checkmarkCircle }); }

  ionViewWillEnter() { void this.cargar(); }

  async cargar(ev?: CustomEvent) {
    try { this.d.set(await this.api.get<MiInicio>('mi/inicio')); this.error.set(''); }
    catch (e) { this.error.set((e as ApiError).message); }
    finally { (ev?.target as HTMLIonRefresherElement | undefined)?.complete(); }
  }

  async compartir() {
    const d = this.d(); if (!d) return;
    try { await Share.share({ title: 'Súmate a la red de Diana', text: d.textoInvitacion, dialogTitle: 'Invitar a la red' }); }
    catch { await this.copiar(); }
  }
  invitarWhatsapp() { const d = this.d(); if (d) void abrir('https://wa.me/?text=' + encodeURIComponent(d.textoInvitacion)); }
  async copiar() {
    const d = this.d(); if (!d) return;
    try { await navigator.clipboard.writeText(d.enlace); await this.avisos.ok('Enlace copiado'); } catch { await this.avisos.error('No se pudo copiar.'); }
  }
}
