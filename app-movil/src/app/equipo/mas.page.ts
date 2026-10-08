import { Component, inject } from '@angular/core';
import { IonContent, IonHeader, IonToolbar, IonTitle, IonList, IonItem, IonLabel, IonButton, IonIcon, AlertController } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { logOutOutline, globeOutline, listOutline, chevronForward } from 'ionicons/icons';
import { RouterLink } from '@angular/router';
import { AuthService } from '../core/auth.service';
import { abrir } from '../core/util';

const ROLES: Record<string, string> = { direccion: 'Dirección de campaña', coordinador: 'Coordinador/a de zona', lider: 'Líder', digitador: 'Digitador/a' };

@Component({
  selector: 'app-mas',
  standalone: true,
  imports: [RouterLink, IonContent, IonHeader, IonToolbar, IonTitle, IonList, IonItem, IonLabel, IonButton, IonIcon],
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
      @if (u.permisos.catalogos) {
        <button type="button" class="enlace" routerLink="/equipo/catalogos">
          <span class="ico"><ion-icon name="list-outline"></ion-icon></span>
          <span><b>Catálogos</b><small>Barrios y veredas, puestos de votación y profesiones</small></span>
          <ion-icon name="chevron-forward" class="flecha"></ion-icon>
        </button>
      }
      @if (u.panelWeb) {
        <ion-button expand="block" fill="outline" (click)="web(u.panelWeb)"><ion-icon slot="start" name="globe-outline"></ion-icon>Abrir el panel web completo</ion-button>
        <p class="small muted" style="text-align:center">Mensajes masivos, WhatsApp, reportes y configuración se manejan en el panel web.</p>
      }
    }
    <ion-button expand="block" color="medium" fill="clear" (click)="salir()"><ion-icon slot="start" name="log-out-outline"></ion-icon>Cerrar sesión</ion-button>
  </ion-content>`,
  styles: [`
    .enlace { display: flex; align-items: center; gap: 12px; width: 100%; background: #fff; border: 0; border-radius: 18px; padding: 14px; margin: 0 0 14px;
              font: inherit; text-align: left; color: #101226; box-shadow: 0 1px 2px rgba(16,18,38,.05), 0 10px 28px rgba(16,18,38,.07); }
    .enlace .ico { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: #FDEBF3; color: #E0186C; font-size: 21px; flex: none; }
    .enlace span:nth-child(2) { flex: 1; display: flex; flex-direction: column; }
    .enlace small { color: #6B7084; font-size: 12.5px; margin-top: 2px; }
    .enlace .flecha { color: #C3C5D3; }
  `],
})
export class MasPage {
  auth = inject(AuthService);
  private alertas = inject(AlertController);
  constructor() { addIcons({ logOutOutline, globeOutline, listOutline, chevronForward }); }
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
