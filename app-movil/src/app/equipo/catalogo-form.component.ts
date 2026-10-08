import { Component, Input, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { IonHeader, IonToolbar, IonTitle, IonButtons, IonButton, IonContent, IonIcon, IonSpinner, ModalController, AlertController } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { closeOutline, locationOutline, chevronForward, trashOutline, mapOutline, flagOutline, briefcaseOutline } from 'ionicons/icons';
import { ApiService, ApiError } from '../core/api.service';
import { AvisosService } from '../core/avisos.service';
import { CatalogosAdmin, ProfesionAdmin, PuestoAdmin, ZonaAdmin } from '../core/modelos';
import { abrirSelector } from '../shared/selector.component';
import { soloDigitos } from '../core/util';

export type TipoCatalogo = 'zona' | 'puesto' | 'profesion';
type Item = ZonaAdmin | PuestoAdmin | ProfesionAdmin;

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const TITULOS: Record<TipoCatalogo, [string, string]> = { zona: ['Nuevo barrio o vereda', 'Editar zona'], puesto: ['Nuevo puesto', 'Editar puesto'], profesion: ['Nueva profesión', 'Editar profesión'] };

/** Crear o editar un barrio/vereda, un puesto de votación o una profesión (se abre como hoja). */
@Component({
  selector: 'app-catalogo-form',
  standalone: true,
  imports: [FormsModule, IonHeader, IonToolbar, IonTitle, IonButtons, IonButton, IonContent, IonIcon, IonSpinner],
  template: `
  <ion-header class="ion-no-border">
    <ion-toolbar>
      <ion-title>{{ titulo }}</ion-title>
      <ion-buttons slot="end"><ion-button (click)="cerrar()"><ion-icon slot="icon-only" name="close-outline"></ion-icon></ion-button></ion-buttons>
    </ion-toolbar>
  </ion-header>
  <ion-content class="ion-padding">
    <label class="campo" [class.error]="error()['nombre']">
      <span class="etq">Nombre</span>
      <span class="caja"><input [(ngModel)]="f.nombre" (ngModelChange)="limpiarError('nombre')" [maxlength]="tipo === 'puesto' ? 120 : 80"
        [placeholder]="tipo === 'zona' ? 'Ej: Villa del Prado' : tipo === 'puesto' ? 'Ej: IE Normal Superior' : 'Ej: Enfermero/a'"></span>
      @if (error()['nombre']) { <em>{{ error()['nombre'] }}</em> }
    </label>

    @if (tipo === 'zona') {
      <div class="campo">
        <span class="etq">Clasificación</span>
        <div class="chips">
          @for (c of datos.clases; track c.clave) {
            <button type="button" class="chip" [class.on]="f.clase === c.clave" (click)="f.clase = c.clave">{{ c.clave }}</button>
          }
        </div>
      </div>
    }

    @if (tipo === 'puesto') {
      <label class="campo" [class.error]="error()['direccion']">
        <span class="etq">Dirección <i>opcional</i></span>
        <span class="caja"><input [(ngModel)]="f.direccion" (ngModelChange)="limpiarError('direccion')" maxlength="160" placeholder="Ej: Calle 5 # 3-20"></span>
        @if (error()['direccion']) { <em>{{ error()['direccion'] }}</em> }
      </label>
      <div class="campo">
        <span class="etq">Barrio o vereda <i>opcional</i></span>
        <button type="button" class="elegir" (click)="elegirZona()">
          <ion-icon name="location-outline" class="ico"></ion-icon>
          <span class="txt">@if (zonaNombre()) { <b>{{ zonaNombre() }}</b> } @else { <i>Elegir zona</i> }</span>
          <ion-icon name="chevron-forward" class="flecha"></ion-icon>
        </button>
      </div>
      <div class="doble">
        <label class="campo" [class.error]="error()['mesas']">
          <span class="etq">Mesas</span>
          <span class="caja"><input [value]="f.mesas" (input)="numero('mesas', $event, 3)" inputmode="numeric" placeholder="Ej: 12"></span>
          @if (error()['mesas']) { <em>{{ error()['mesas'] }}</em> }
        </label>
        <label class="campo" [class.error]="error()['potencial']">
          <span class="etq">Potencial <i>votantes</i></span>
          <span class="caja"><input [value]="f.potencial" (input)="numero('potencial', $event, 6)" inputmode="numeric" placeholder="Ej: 3400"></span>
          @if (error()['potencial']) { <em>{{ error()['potencial'] }}</em> }
        </label>
      </div>
      <small class="ayuda">Las mesas y el potencial salen de la Registraduría. Con las mesas se valida la mesa de cada persona.</small>
    }

    @if (tipo === 'profesion') {
      <div class="campo" [class.error]="error()['dia']">
        <span class="etq">Día de la profesión <i>para el saludo por WhatsApp</i></span>
        <div class="doble">
          <span class="caja"><select [(ngModel)]="f.mes"><option value="">Mes</option>@for (m of meses; track $index) { <option [value]="pad($index + 1)">{{ m }}</option> }</select></span>
          <span class="caja"><select [(ngModel)]="f.dia"><option value="">Día</option>@for (d of dias; track d) { <option [value]="pad(d)">{{ d }}</option> }</select></span>
        </div>
        @if (error()['dia']) { <em>{{ error()['dia'] }}</em> }
      </div>
    }

    <button type="button" class="btn primario" (click)="guardar()" [disabled]="guardando()">
      @if (guardando()) { <ion-spinner name="crescent"></ion-spinner> } @else { {{ item ? 'Guardar cambios' : 'Crear' }} }
    </button>
    @if (item) {
      @if (item.uso === 0) {
        <button type="button" class="btn borrar" (click)="eliminar()"><ion-icon name="trash-outline"></ion-icon> Eliminar</button>
      } @else {
        <p class="nota">No se puede eliminar porque lo usan <b>{{ item.uso }}</b> {{ item.uso === 1 ? 'persona' : 'personas' }}. Puedes corregir el nombre.</p>
      }
    }
  </ion-content>`,
  styles: [`
    ion-toolbar { --background: #fff; }
    ion-content { --background: #fff; }
    .campo { display: block; margin-bottom: 16px; }
    .etq { display: block; font-size: 13px; font-weight: 700; margin: 0 0 7px 2px; color: #101226; }
    .etq i { font-style: normal; font-weight: 600; color: #6B7084; font-size: 11.5px; margin-left: 4px; }
    .caja { display: flex; align-items: center; min-width: 0; background: #F4F3F9; border: 1.5px solid transparent; border-radius: 14px; padding: 0 14px; height: 52px; }
    .caja:focus-within { background: #fff; border-color: #E0186C; box-shadow: 0 0 0 4px #FDEBF3; }
    .caja input, .caja select { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font: inherit; font-size: 16px; color: #101226; height: 100%; }
    .campo.error .caja, .campo.error .elegir { border-color: #D92D50; background: #FFF5F7; }
    em { display: block; font-style: normal; color: #D92D50; font-size: 12.5px; font-weight: 600; margin: 6px 0 0 4px; }
    .chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .chip { border: 1.5px solid #E7E6EF; background: #fff; border-radius: 99px; padding: 9px 14px; font: inherit; font-size: 14px; font-weight: 600; color: #101226; }
    .chip.on { background: #101226; border-color: #101226; color: #fff; }
    .elegir { display: flex; align-items: center; gap: 10px; width: 100%; background: #F4F3F9; border: 1.5px solid transparent; border-radius: 14px; padding: 0 14px; height: 52px; font: inherit; color: #101226; text-align: left; }
    .elegir .ico { color: #E0186C; font-size: 20px; } .elegir .txt { flex: 1; } .elegir i { font-style: normal; color: #A9ACBD; } .elegir .flecha { color: #B8BACA; }
    .doble { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 10px; }
    .doble .campo { margin-bottom: 0; }
    .ayuda { display: block; color: #6B7084; font-size: 12px; margin: 8px 2px 18px; }
    .btn { width: 100%; border: 0; border-radius: 16px; height: 52px; font: inherit; font-size: 16px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 6px; }
    .btn.primario { background: linear-gradient(120deg, #E0186C 0%, #B4239A 60%, #7C3AED 130%); color: #fff; }
    .btn.primario ion-spinner { color: #fff; }
    .btn.borrar { background: #FFF1F3; color: #C81E45; margin-top: 10px; }
    .nota { color: #6B7084; font-size: 13px; text-align: center; margin-top: 14px; }
  `],
})
export class CatalogoFormComponent implements OnInit {
  private api = inject(ApiService);
  private avisos = inject(AvisosService);
  private hoja = inject(ModalController);
  private alertas = inject(AlertController);
  @Input() tipo: TipoCatalogo = 'zona';
  @Input() item: Item | null = null;
  @Input() datos!: CatalogosAdmin;

  titulo = '';
  meses = MESES;
  dias = Array.from({ length: 31 }, (_, i) => i + 1);
  f = { nombre: '', clase: 'Barrio', direccion: '', zona_id: null as number | null, mesas: '', potencial: '', mes: '', dia: '' };
  error = signal<Record<string, string>>({});
  guardando = signal(false);
  zonaNombre = signal('');

  constructor() { addIcons({ closeOutline, locationOutline, chevronForward, trashOutline, mapOutline, flagOutline, briefcaseOutline }); }

  ngOnInit() {
    this.titulo = TITULOS[this.tipo][this.item ? 1 : 0];
    const it = this.item as (ZonaAdmin & PuestoAdmin & ProfesionAdmin) | null;
    if (!it) return;
    this.f.nombre = it.nombre;
    if (this.tipo === 'zona') this.f.clase = it.clase ?? 'Barrio';
    if (this.tipo === 'puesto') {
      this.f.direccion = it.direccion ?? ''; this.f.zona_id = it.zonaId; this.zonaNombre.set(it.zona ?? '');
      this.f.mesas = it.mesas?.toString() ?? ''; this.f.potencial = it.potencial?.toString() ?? '';
    }
    if (this.tipo === 'profesion' && it.dia) [this.f.mes, this.f.dia] = it.dia.split('-');
  }

  pad(n: number) { return n.toString().padStart(2, '0'); }
  limpiarError(k: string) { if (this.error()[k]) { const e = { ...this.error() }; delete e[k]; this.error.set(e); } }

  numero(k: 'mesas' | 'potencial', ev: Event, max: number) {
    const el = ev.target as HTMLInputElement;
    this.f[k] = soloDigitos(el.value, max); el.value = this.f[k]; this.limpiarError(k);
  }

  async elegirZona() {
    const o = await abrirSelector(this.hoja, {
      titulo: 'Barrio o vereda', buscar: 'Buscar zona…', seleccion: this.f.zona_id, opcional: true, textoNinguno: 'Sin zona',
      opciones: this.datos.zonas.map(z => ({ id: z.id, nombre: z.nombre, grupo: z.grupo })),
    });
    if (o !== undefined) { this.f.zona_id = o?.id ?? null; this.zonaNombre.set(o?.nombre ?? ''); }
  }

  async guardar() {
    if (this.f.nombre.trim().length < 3) { this.error.set({ nombre: 'Escribe el nombre (mínimo 3 letras).' }); return; }
    if (this.tipo === 'profesion' && (!this.f.mes) !== (!this.f.dia)) { this.error.set({ dia: 'Elige el mes y el día, o deja ambos vacíos.' }); return; }
    const id = this.item?.id ?? 0;
    const cuerpo = this.tipo === 'zona' ? { id, nombre: this.f.nombre, clase: this.f.clase }
      : this.tipo === 'puesto' ? { id, nombre: this.f.nombre, direccion: this.f.direccion, zona_id: this.f.zona_id, mesas: this.f.mesas, potencial: this.f.potencial }
      : { id, nombre: this.f.nombre, dia: this.f.mes && this.f.dia ? `${this.f.mes}-${this.f.dia}` : '' };
    this.guardando.set(true);
    try {
      const r = await this.api.post<{ msg: string }>('equipo/catalogos/' + this.tipo, cuerpo);
      await this.avisos.ok(r.msg);
      await this.hoja.dismiss(null, 'guardado');
    } catch (e) {
      const x = e as ApiError;
      if (x.campo) this.error.set({ [x.campo === 'zona_id' ? 'nombre' : x.campo]: x.message }); else await this.avisos.error(x.message);
    } finally { this.guardando.set(false); }
  }

  async eliminar() {
    const a = await this.alertas.create({
      header: '¿Eliminar?', message: `Se quitará «${this.item?.nombre}» de los formularios.`,
      buttons: [{ text: 'Cancelar', role: 'cancel' }, { text: 'Eliminar', role: 'destructive', handler: () => { void this.hacerEliminar(); } }],
    });
    await a.present();
  }

  private async hacerEliminar() {
    try {
      const r = await this.api.post<{ msg: string }>('equipo/catalogos/eliminar', { tipo: this.tipo, id: this.item?.id });
      await this.avisos.ok(r.msg);
      await this.hoja.dismiss(null, 'guardado');
    } catch (e) { await this.avisos.error((e as ApiError).message); }
  }

  cerrar() { void this.hoja.dismiss(null, 'cancel'); }
}
