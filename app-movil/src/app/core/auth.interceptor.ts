import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { AuthService } from './auth.service';
import { environment } from '../../environments/environment';

/** Agrega el token a las llamadas a la API y, si el servidor dice que venció, vuelve al ingreso. */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const esApi = req.url.startsWith(environment.apiUrl);
  const token = auth.token;
  const peticion = esApi && token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req;
  return next(peticion).pipe(
    catchError((e: unknown) => {
      if (esApi && token && e instanceof HttpErrorResponse && e.status === 401 && !req.url.includes('r=auth/')) {
        void auth.olvidar();
      }
      return throwError(() => e);
    }),
  );
};
