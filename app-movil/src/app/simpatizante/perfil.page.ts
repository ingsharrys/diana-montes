import { Component, inject } from '@angular/core';
import { IonContent, IonHeader, IonToolbar, IonTitle, IonList, IonItem, IonLabel, IonButton, IonIcon, AlertController } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { logOutOutline, globeOutline } from 'ionicons/icons';
import { AuthService } from '../core/auth.service';
import { abrir, formatoCelular } from '../core/util';
import { environment } from '../../environments/environment';

@Component({
  selector: 'app-mi-perfil',
  standalone: true,
  imports: [IonContent, IonHeader, IonToolbar, IonTitle, IonList, IonItem, IonLabel, IonButton, IonIcon],
  template: `
  <ion-header><ion-toolbar class="dm"><ion-title>Mi perfil</ion-title></ion-toolbar></ion-header>
  <ion-content class="ion-padding">
    @if (auth.simpatizante(); as p) {
      <section class="tarjeta">
        <h2>👤 {{ p.nombre }}</h2>
        <ion-list lines="full">
          <ion-item><ion-label><p>Celular (tu acceso)</p><h3>{{ cel(p.telefono) }}</h3></ion-label></ion-item>
          <ion-item><ion-label><p>Documento</p><h3>{{ p.documento }}</h3></ion-label></ion-item>
          <ion-item><ion-label><p>Nivel</p><h3>{{ p.nivel }} · {{ p.puntos }} puntos</h3></ion-label></ion-item>
        </ion-list>
        <p class="small muted">¿Algún dato está mal? Pídele a tu líder que lo corrija.</p>
      </section>
    }
    <ion-button expand="block" fill="outline" (click)="web()"><ion-icon slot="start" name="globe-outline"></ion-icon>Abrir mi panel en la web</ion-button>
    <ion-button expand="block" color="medium" fill="clear" (click)="salir()"><ion-icon slot="start" name="log-out-outline"></ion-icon>Cerrar sesión</ion-button>
    <p class="small muted" style="text-align:center">Para dejar de recibir mensajes de WhatsApp responde <b>SALIR</b> a cualquier mensaje de la campaña.</p>
  </ion-content>`,
})
export class MiPerfilPage {
  auth = inject(AuthService);
  private alertas = inject(AlertController);
  cel = formatoCelular;
  constructor() { addIcons({ logOutOutline, globeOutline }); }
  ionViewWillEnter() { void this.auth.refrescarPerfil().catch(() => undefined); }
  web() { void abrir(environment.sitioWeb + '/mi/'); }
  async salir() {
    const a = await this.alertas.create({
      header: '¿Cerrar sesión?', message: 'Para volver a entrar te enviaremos un código a tu WhatsApp.',
      buttons: [{ text: 'Cancelar', role: 'cancel' }, { text: 'Cerrar sesión', handler: () => { void this.auth.salir(); } }],
    });
    await a.present();
  }
}
