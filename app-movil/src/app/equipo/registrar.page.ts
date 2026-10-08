import { Component, inject, signal, computed } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  IonContent, IonHeader, IonToolbar, IonTitle, IonList, IonItem, IonInput, IonSelect, IonSelectOption, IonCheckbox, IonButton,
  IonModal, IonSearchbar, IonItemGroup, IonItemDivider, IonLabel, IonButtons, IonSpinner,
} from '@ionic/angular/standalone';
import { ApiService, ApiError } from '../core/api.service';
import { AvisosService } from '../core/avisos.service';
import { AuthService } from '../core/auth.service';
import { Catalogos } from '../core/modelos';
import { CatalogosService } from './catalogos.service';
import { celular, soloDigitos, soloLetras } from '../core/util';

type Zona = Catalogos['zonas'][number];

const VACIO = () => ({
  nombre: '', documento: '', telefono: '', fecha_nacimiento: '', genero: '', zona_id: 0, profesion_id: null as number | null,
  puesto_id: null as number | null, mesa: '', nivel: 'simpatizante', lider_id: null as number | null, consentimiento: false,
});

@Component({
  selector: 'app-registrar',
  standalone: true,
  imports: [FormsModule, IonContent, IonHeader, IonToolbar, IonTitle, IonList, IonItem, IonInput, IonSelect, IonSelectOption, IonCheckbox,
    IonButton, IonModal, IonSearchbar, IonItemGroup, IonItemDivider, IonLabel, IonButtons, IonSpinner],
  templateUrl: './registrar.page.html',
})
export class RegistrarPage {
  private api = inject(ApiService);
  private avisos = inject(AvisosService);
  private catalogos = inject(CatalogosService);
  auth = inject(AuthService);

  cat = signal<Catalogos | null>(null);
  f = VACIO();
  errores = signal<Record<string, string>>({});
  zonaAbierta = signal(false);
  buscarZona = signal('');
  hoy = new Date().toISOString().slice(0, 10);

  zonaNombre = computed(() => this.cat()?.zonas.find(z => z.id === this.zonaSel())?.nombre ?? '');
  private zonaSel = signal(0);

  /** Zonas filtradas y agrupadas (barrios, veredas, corregimientos…) para el buscador. */
  grupos = computed(() => {
    const q = this.normal(this.buscarZona());
    const r: { grupo: string; zonas: Zona[] }[] = [];
    for (const z of this.cat()?.zonas ?? []) {
      if (q && !this.normal(z.nombre).includes(q)) continue;
      let g = r.find(x => x.grupo === z.grupo);
      if (!g) r.push(g = { grupo: z.grupo, zonas: [] });
      g.zonas.push(z);
    }
    return r;
  });

  async ionViewWillEnter() {
    try { this.cat.set(await this.catalogos.obtener()); }
    catch (e) { await this.avisos.error((e as ApiError).message); }
  }

  private normal(v: string) { return v.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim(); }

  /** Filtra lo que se escribe (letras, números) y lo devuelve al campo para que no se vea lo que no vale. */
  limpiar(campo: 'nombre' | 'documento' | 'telefono' | 'mesa', ev: Event) {
    const el = ev.target as HTMLIonInputElement;
    const t = String(el.value ?? '');
    if (campo === 'nombre') this.f.nombre = soloLetras(t);
    if (campo === 'documento') this.f.documento = soloDigitos(t, 10);
    if (campo === 'telefono') this.f.telefono = celular(t);
    if (campo === 'mesa') this.f.mesa = soloDigitos(t, 4);
    el.value = this.f[campo];
    this.quitarError(campo);
  }

  quitarError(campo: string) {
    if (!this.errores()[campo]) return;
    const e = { ...this.errores() }; delete e[campo]; this.errores.set(e);
  }

  elegirZona(z: Zona) {
    this.f.zona_id = z.id; this.zonaSel.set(z.id);
    this.quitarError('zona_id');
    this.zonaAbierta.set(false);
    this.buscarZona.set('');
  }

  /** Revisión rápida en el teléfono; el servidor vuelve a validar todo. */
  private revisar(): Record<string, string> {
    const e: Record<string, string> = {};
    const c = this.cat();
    if (this.f.nombre.trim().split(/\s+/).length < 2) e['nombre'] = 'Escribe nombre y apellido.';
    if (this.f.documento.length < 5) e['documento'] = 'Escribe el número de documento (solo números).';
    if (!/^3\d{9}$/.test(this.f.telefono)) e['telefono'] = 'El celular debe tener 10 dígitos y empezar por 3.';
    if (!this.f.fecha_nacimiento) e['fecha_nacimiento'] = 'Escribe la fecha de nacimiento.';
    if (c?.generos.length && !this.f.genero) e['genero'] = 'Selecciona el género.';
    if (!this.f.zona_id) e['zona_id'] = 'Selecciona el barrio o vereda.';
    if (!this.f.profesion_id) e['profesion_id'] = 'Selecciona la profesión.';
    if (!this.auth.usuario()?.permisos.soloSuRed && !this.f.lider_id) e['lider_id'] = 'Selecciona el líder que vincula.';
    if (!this.f.consentimiento) e['consentimiento'] = 'Sin la autorización de datos no se puede guardar el registro.';
    return e;
  }

  async guardar() {
    const e = this.revisar();
    this.errores.set(e);
    if (Object.keys(e).length) { this.irAlError(); return; }
    try {
      const r = await this.avisos.conCarga(() => this.api.post<{ id: number; msg: string }>('equipo/simpatizantes', this.f));
      await this.avisos.ok(r.msg);
      this.f = VACIO(); this.zonaSel.set(0);
    } catch (err) {
      const x = err as ApiError;
      if (x.errores) { this.errores.set(x.errores); this.irAlError(); }
      await this.avisos.error(x.message);
    }
  }

  private irAlError() {
    setTimeout(() => document.querySelector('app-registrar .con-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 50);
  }
}
