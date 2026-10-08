import { Routes } from '@angular/router';
import { redirigirInicio, sinSesion, soloTipo } from './core/guards';

export const routes: Routes = [
  { path: '', pathMatch: 'full', canActivate: [redirigirInicio], children: [] },
  { path: 'login', canActivate: [sinSesion], loadComponent: () => import('./login/login.page').then(m => m.LoginPage) },
  {
    path: 'mi', canActivate: [soloTipo('simpatizante')],
    loadComponent: () => import('./simpatizante/tabs.page').then(m => m.SimpatizanteTabsPage),
    children: [
      { path: 'inicio', loadComponent: () => import('./simpatizante/inicio.page').then(m => m.MiInicioPage) },
      { path: 'red', loadComponent: () => import('./simpatizante/red.page').then(m => m.MiRedPage) },
      { path: 'tareas', loadComponent: () => import('./simpatizante/tareas.page').then(m => m.MisTareasPage) },
      { path: 'perfil', loadComponent: () => import('./simpatizante/perfil.page').then(m => m.MiPerfilPage) },
      { path: '', pathMatch: 'full', redirectTo: 'inicio' },
    ],
  },
  {
    path: 'equipo', canActivate: [soloTipo('usuario')],
    loadComponent: () => import('./equipo/tabs.page').then(m => m.EquipoTabsPage),
    children: [
      { path: 'tablero', loadComponent: () => import('./equipo/tablero.page').then(m => m.TableroPage) },
      { path: 'simpatizantes', loadComponent: () => import('./equipo/simpatizantes.page').then(m => m.SimpatizantesPage) },
      { path: 'simpatizantes/:id', loadComponent: () => import('./equipo/simpatizante.page').then(m => m.SimpatizantePage) },
      { path: 'registrar', loadComponent: () => import('./equipo/registrar.page').then(m => m.RegistrarPage) },
      { path: 'tareas', loadComponent: () => import('./equipo/tareas.page').then(m => m.TareasPage) },
      { path: 'tareas/:id', loadComponent: () => import('./equipo/tarea.page').then(m => m.TareaPage) },
      { path: 'mas', loadComponent: () => import('./equipo/mas.page').then(m => m.MasPage) },
      { path: '', pathMatch: 'full', redirectTo: 'tablero' },
    ],
  },
  { path: '**', redirectTo: '' },
];
