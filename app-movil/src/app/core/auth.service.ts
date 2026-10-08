import { Injectable, inject, signal, computed } from '@angular/core';
import { Router } from '@angular/router';
import { Preferences } from '@capacitor/preferences';
import { Capacitor } from '@capacitor/core';
import { ApiService } from './api.service';

export type TipoCuenta = 'simpatizante' | 'usuario';

export interface PerfilUsuario {
  id: number; nombre: string; email: string; rol: string;
  permisos: { verDatosCompletos: boolean; soloSuRed: boolean; tareas: boolean };
  panelWeb: string | null;
}
export interface PerfilSimpatizante {
  id: number; nombre: string; primerNombre: string; documento: string; telefono: string; puntos: number; nivel: string;
}
export interface Sesion {
  token: string;
  tipo: TipoCuenta;
  perfil: PerfilUsuario | PerfilSimpatizante;
}

interface RespuestaLogin { ok: true; token: string; tipo: TipoCuenta; perfil: Sesion['perfil']; }

const CLAVE = 'dm_sesion';

/**
 * Sesión de la app. Se guarda en el almacenamiento del teléfono (Preferences):
 * al abrir la app de nuevo la persona sigue adentro hasta que cierre sesión.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private api = inject(ApiService);
  private router = inject(Router);

  readonly sesion = signal<Sesion | null>(null);
  readonly tipo = computed(() => this.sesion()?.tipo ?? null);
  readonly usuario = computed(() => this.sesion()?.tipo === 'usuario' ? this.sesion()!.perfil as PerfilUsuario : null);
  readonly simpatizante = computed(() => this.sesion()?.tipo === 'simpatizante' ? this.sesion()!.perfil as PerfilSimpatizante : null);

  private cargada: Promise<void> | null = null;

  /** Lee la sesión guardada (una sola vez al abrir la app). */
  cargar(): Promise<void> {
    if (!this.cargada) {
      this.cargada = Preferences.get({ key: CLAVE }).then(({ value }) => {
        if (!value) return;
        try { this.sesion.set(JSON.parse(value)); } catch { /* sesión dañada: se ignora */ }
      });
    }
    return this.cargada;
  }

  get token(): string | null { return this.sesion()?.token ?? null; }

  /** Ruta de inicio según el tipo de cuenta. */
  inicio(): string {
    return this.tipo() === 'usuario' ? '/equipo/tablero' : this.tipo() === 'simpatizante' ? '/mi/inicio' : '/login';
  }

  private dispositivo(): string {
    const p = Capacitor.getPlatform();
    return p === 'web' ? 'Navegador' : p === 'ios' ? 'iPhone' : 'Android';
  }

  solicitarCodigo(telefono: string) {
    return this.api.post<{ ok: true; msg: string; espera: number; vigencia: number; codigo_dev?: string }>('auth/otp/solicitar', { telefono });
  }

  async verificarCodigo(telefono: string, codigo: string): Promise<void> {
    const r = await this.api.post<RespuestaLogin>('auth/otp/verificar', { telefono, codigo, dispositivo: this.dispositivo() });
    await this.guardar(r);
  }

  async ingresarEquipo(email: string, password: string): Promise<void> {
    const r = await this.api.post<RespuestaLogin>('auth/equipo', { email, password, dispositivo: this.dispositivo() });
    await this.guardar(r);
  }

  private async guardar(r: RespuestaLogin): Promise<void> {
    const s: Sesion = { token: r.token, tipo: r.tipo, perfil: r.perfil };
    this.sesion.set(s);
    await Preferences.set({ key: CLAVE, value: JSON.stringify(s) });
  }

  /** Refresca el perfil (puntos, nivel…) sin pedir de nuevo el acceso. */
  async refrescarPerfil(): Promise<void> {
    const s = this.sesion();
    if (!s) return;
    const r = await this.api.get<{ ok: true; tipo: TipoCuenta; perfil: Sesion['perfil'] }>('yo');
    const nueva = { ...s, perfil: r.perfil };
    this.sesion.set(nueva);
    await Preferences.set({ key: CLAVE, value: JSON.stringify(nueva) });
  }

  /** Cierra la sesión en el servidor y en el teléfono. */
  async salir(): Promise<void> {
    try { if (this.token) await this.api.post('auth/salir'); } catch { /* sin conexión: igual se cierra aquí */ }
    await this.olvidar();
  }

  /** Borra la sesión del teléfono (p. ej. si el servidor la dio por vencida). */
  async olvidar(): Promise<void> {
    this.sesion.set(null);
    await Preferences.remove({ key: CLAVE });
    await this.router.navigateByUrl('/login', { replaceUrl: true });
  }
}
