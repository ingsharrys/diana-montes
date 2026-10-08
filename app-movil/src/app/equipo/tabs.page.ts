import { Component, inject } from '@angular/core';
import { IonTabs, IonTabBar, IonTabButton, IonIcon, IonLabel } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { speedometerOutline, peopleOutline, personAddOutline, clipboardOutline, ellipsisHorizontalCircleOutline } from 'ionicons/icons';
import { AuthService } from '../core/auth.service';

@Component({
  selector: 'app-equipo-tabs',
  standalone: true,
  imports: [IonTabs, IonTabBar, IonTabButton, IonIcon, IonLabel],
  template: `
  <ion-tabs>
    <ion-tab-bar slot="bottom">
      <ion-tab-button tab="tablero"><ion-icon name="speedometer-outline"></ion-icon><ion-label>Tablero</ion-label></ion-tab-button>
      <ion-tab-button tab="simpatizantes"><ion-icon name="people-outline"></ion-icon><ion-label>Simpatizantes</ion-label></ion-tab-button>
      <ion-tab-button tab="registrar"><ion-icon name="person-add-outline"></ion-icon><ion-label>Registrar</ion-label></ion-tab-button>
      @if (auth.usuario()?.permisos?.tareas) {
        <ion-tab-button tab="tareas"><ion-icon name="clipboard-outline"></ion-icon><ion-label>Tareas</ion-label></ion-tab-button>
      }
      <ion-tab-button tab="mas"><ion-icon name="ellipsis-horizontal-circle-outline"></ion-icon><ion-label>Más</ion-label></ion-tab-button>
    </ion-tab-bar>
  </ion-tabs>`,
})
export class EquipoTabsPage {
  auth = inject(AuthService);
  constructor() { addIcons({ speedometerOutline, peopleOutline, personAddOutline, clipboardOutline, ellipsisHorizontalCircleOutline }); }
}
