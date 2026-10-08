import { Component, inject } from '@angular/core';
import { IonContent, IonHeader, IonToolbar, IonTitle, IonList, IonItem, IonLabel, IonButton, IonIcon, AlertController } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { logOutOutline, globeOutline } from 'ionicons/icons';
import { AuthService } from '../core/auth.service';
import { abrir } from '../core/util';

const ROLES: Record<string, string> = { direccion: 'Dirección de campaña', coordinador: 'Coordinador/a de zona', lider: 'Líder', digitador: 'Digitador/a' };

@Component({
  selector: 'app-mas',
  standalone: true,
  imports: [IonContent, IonHeader, IonToolbar, IonTitle, IonList, IonItem, IonLabel, IonButton, IonIcon],
  template: `
  <ion-header><ion-toolbar class="dm"><ion-title>Mi cuenta</ion-title></ion-toolbar></ion-header>
  <ion-content class="ion-padding">
    @if (auth.usuario(); as u) {
      <section class="tarjeta">
        <h2>👤 {{ u.nombre }}</h2>
        <ion-list lines="full">
          <ion-item><ion-label><p>Correo</p><h3>{{ u.email }}</h3></ion-label></ion-item>
          <ion-item><ion-label><p>Rol</p><h3>{{ rol(u.rol) }}</h3></ion-label></ion-item>
          <ion-item><ion-label><p>Alcance</p><h3>{{ u.permisos.soloSuRed ? 'Solo tu red' : 'Toda la campaña' }}{{ u.permisos.verDatosCompletos ? ' · datos completos' : ' · datos protegidos' }}</h3></ion-label></ion-item>
        </ion-list>
      </section>
      @if (u.panelWeb) {
        <ion-button expand="block" fill="outline" (click)="web(u.panelWeb)"><ion-icon slot="start" name="globe-outline"></ion-icon>Abrir el panel web completo</ion-button>
        <p class="small muted" style="text-align:center">Mensajes masivos, WhatsApp, reportes y configuración se manejan en el panel web.</p>
      }
    }
    <ion-button expand="block" color="medium" fill="clear" (click)="salir()"><ion-icon slot="start" name="log-out-outline"></ion-icon>Cerrar sesión</ion-button>
  </ion-content>`,
})
export class MasPage {
  auth = inject(AuthService);
  private alertas = inject(AlertController);
  constructor() { addIcons({ logOutOutline, globeOutline }); }
  ionViewWillEnter() { void this.auth.refrescarPerfil().catch(() => undefined); }
  rol(r: string) { return ROLES[r] ?? r; }
  web(url: string) { void abrir(url); }
  async salir() {
    const a = await this.alertas.create({
      header: '¿Cerrar sesión?', message: 'Tendrás que volver a ingresar con tu correo y contraseña.',
      buttons: [{ text: 'Cancelar', role: 'cancel' }, { text: 'Cerrar sesión', handler: () => { void this.auth.salir(); } }],
    });
    await a.present();
  }
}
