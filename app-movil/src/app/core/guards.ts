import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService, TipoCuenta } from './auth.service';

/** Solo entra quien tiene una sesión del tipo indicado; si no, va a su inicio o al ingreso. */
export const soloTipo = (tipo: TipoCuenta): CanActivateFn => async () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  await auth.cargar();
  if (auth.tipo() === tipo) return true;
  return router.parseUrl(auth.inicio());
};

/** El ingreso solo se muestra sin sesión. */
export const sinSesion: CanActivateFn = async () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  await auth.cargar();
  return auth.tipo() ? router.parseUrl(auth.inicio()) : true;
};

/** Raíz: a donde corresponda. */
export const redirigirInicio: CanActivateFn = async () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  await auth.cargar();
  return router.parseUrl(auth.inicio());
};
