import { Component } from '@angular/core';
import { IonTabs, IonTabBar, IonTabButton, IonIcon, IonLabel } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { homeOutline, peopleOutline, checkboxOutline, personCircleOutline } from 'ionicons/icons';

@Component({
  selector: 'app-mi-tabs',
  standalone: true,
  imports: [IonTabs, IonTabBar, IonTabButton, IonIcon, IonLabel],
  template: `
  <ion-tabs>
    <ion-tab-bar slot="bottom">
      <ion-tab-button tab="inicio"><ion-icon name="home-outline"></ion-icon><ion-label>Inicio</ion-label></ion-tab-button>
      <ion-tab-button tab="red"><ion-icon name="people-outline"></ion-icon><ion-label>Mi red</ion-label></ion-tab-button>
      <ion-tab-button tab="tareas"><ion-icon name="checkbox-outline"></ion-icon><ion-label>Tareas</ion-label></ion-tab-button>
      <ion-tab-button tab="perfil"><ion-icon name="person-circle-outline"></ion-icon><ion-label>Perfil</ion-label></ion-tab-button>
    </ion-tab-bar>
  </ion-tabs>`,
})
export class SimpatizanteTabsPage {
  constructor() { addIcons({ homeOutline, peopleOutline, checkboxOutline, personCircleOutline }); }
}
