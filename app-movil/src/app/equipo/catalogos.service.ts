import { Injectable, inject } from '@angular/core';
import { ApiService } from '../core/api.service';
import { Catalogos } from '../core/modelos';

/** Listas de los formularios (zonas, profesiones, puestos…), guardadas mientras la app esté abierta. */
@Injectable({ providedIn: 'root' })
export class CatalogosService {
  private api = inject(ApiService);
  private cache: Promise<Catalogos> | null = null;

  obtener(refrescar = false): Promise<Catalogos> {
    if (!this.cache || refrescar) {
      this.cache = this.api.get<Catalogos>('equipo/catalogos');
      this.cache.catch(() => { this.cache = null; });
    }
    return this.cache;
  }
}
