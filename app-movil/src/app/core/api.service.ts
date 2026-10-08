import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpErrorResponse, HttpParams } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../environments/environment';

/** Error de la API con el mensaje listo para mostrar. */
export class ApiError extends Error {
  constructor(
    public override message: string,
    public status: number,
    public campo?: string,
    public errores?: Record<string, string>,
    public espera?: number,
  ) { super(message); }
}

/** Cliente de la API del sitio (admin/api.php?r=ruta). El token lo agrega el interceptor. */
@Injectable({ providedIn: 'root' })
export class ApiService {
  private http = inject(HttpClient);

  url(ruta: string): string {
    return `${environment.apiUrl}?r=${ruta}`;
  }

  async get<T>(ruta: string, params: Record<string, string | number | undefined> = {}): Promise<T> {
    let p = new HttpParams();
    for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== '') p = p.set(k, String(v));
    try {
      return await firstValueFrom(this.http.get<T>(this.url(ruta), { params: p }));
    } catch (e) { throw this.error(e); }
  }

  async post<T>(ruta: string, cuerpo: unknown = {}): Promise<T> {
    try {
      return await firstValueFrom(this.http.post<T>(this.url(ruta), cuerpo));
    } catch (e) { throw this.error(e); }
  }

  private error(e: unknown): ApiError {
    if (e instanceof HttpErrorResponse) {
      if (e.status === 0) return new ApiError('Sin conexión. Revisa tu internet e intenta de nuevo.', 0);
      const b = (e.error && typeof e.error === 'object') ? e.error : {};
      return new ApiError(b.msg || 'Tuvimos un problema. Intenta de nuevo.', e.status, b.campo, b.errores, b.espera);
    }
    return new ApiError('Tuvimos un problema. Intenta de nuevo.', 0);
  }
}
