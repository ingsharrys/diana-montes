import { Component, OnDestroy, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import {
  IonContent, IonSegment, IonSegmentButton, IonLabel, IonItem, IonInput, IonButton, IonIcon, IonSpinner, IonInputPasswordToggle,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { logoWhatsapp, lockClosedOutline, arrowBackOutline } from 'ionicons/icons';
import { AuthService } from '../core/auth.service';
import { ApiError } from '../core/api.service';
import { AvisosService } from '../core/avisos.service';
import { abrir, celular, celularValido, formatoCelular, soloDigitos } from '../core/util';
import { environment } from '../../environments/environment';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [FormsModule, IonContent, IonSegment, IonSegmentButton, IonLabel, IonItem, IonInput, IonButton, IonIcon, IonSpinner, IonInputPasswordToggle],
  templateUrl: './login.page.html',
  styleUrls: ['./login.page.scss'],
})
export class LoginPage implements OnDestroy {
  private auth = inject(AuthService);
  private router = inject(Router);
  private avisos = inject(AvisosService);

  modo: 'whatsapp' | 'equipo' = 'whatsapp';
  paso = signal<'numero' | 'codigo'>('numero');
  telefono = '';
  codigo = '';
  email = '';
  password = '';
  error = signal('');
  enviando = signal(false);
  espera = signal(0);
  codigoPrueba = signal('');
  private reloj?: ReturnType<typeof setInterval>;

  constructor() { addIcons({ logoWhatsapp, lockClosedOutline, arrowBackOutline }); }
  ngOnDestroy() { if (this.reloj) clearInterval(this.reloj); }

  get telefonoVisible() { return formatoCelular(this.telefono); }

  limpiarTelefono(v: string | null | undefined, input: IonInput) {
    this.telefono = celular(String(v ?? ''));
    input.value = this.telefono;
  }
  limpiarCodigo(v: string | null | undefined, input: IonInput) {
    this.codigo = soloDigitos(String(v ?? ''), 6);
    input.value = this.codigo;
    if (this.codigo.length === 6) void this.verificar();
  }

  private contar(seg: number) {
    this.espera.set(seg);
    if (this.reloj) clearInterval(this.reloj);
    this.reloj = setInterval(() => {
      this.espera.update(s => Math.max(0, s - 1));
      if (this.espera() === 0 && this.reloj) clearInterval(this.reloj);
    }, 1000);
  }

  async pedirCodigo() {
    this.error.set('');
    if (!celularValido(this.telefono)) { this.error.set('Escribe tu celular de 10 dígitos (empieza por 3).'); return; }
    this.enviando.set(true);
    try {
      const r = await this.auth.solicitarCodigo(this.telefono);
      this.paso.set('codigo');
      this.codigo = '';
      this.contar(r.espera);
      this.codigoPrueba.set(r.codigo_dev ?? '');
    } catch (e) {
      const err = e as ApiError;
      this.error.set(err.message);
      if (err.espera) { this.paso.set('codigo'); this.contar(err.espera); }
    } finally { this.enviando.set(false); }
  }

  async verificar() {
    if (this.enviando()) return;
    this.error.set('');
    if (this.codigo.length !== 6) { this.error.set('El código tiene 6 números.'); return; }
    this.enviando.set(true);
    try {
      await this.auth.verificarCodigo(this.telefono, this.codigo);
      await this.router.navigateByUrl(this.auth.inicio(), { replaceUrl: true });
    } catch (e) {
      this.error.set((e as ApiError).message);
      this.codigo = '';
    } finally { this.enviando.set(false); }
  }

  cambiarNumero() { this.paso.set('numero'); this.error.set(''); this.codigo = ''; }

  async ingresarEquipo() {
    this.error.set('');
    if (!/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(this.email.trim())) { this.error.set('Escribe un correo válido.'); return; }
    if (!this.password) { this.error.set('Escribe tu contraseña.'); return; }
    this.enviando.set(true);
    try {
      await this.auth.ingresarEquipo(this.email.trim(), this.password);
      await this.router.navigateByUrl(this.auth.inicio(), { replaceUrl: true });
    } catch (e) {
      this.error.set((e as ApiError).message);
    } finally { this.enviando.set(false); }
  }

  registrarme() { void abrir(environment.sitioWeb + '/#sumate'); }
  cambiarModo() { this.error.set(''); }
}
