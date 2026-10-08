import { Browser } from '@capacitor/browser';

/** Solo letras (con tildes y ñ) y espacios, como en la web. */
export const soloLetras = (v: string) => v.replace(/[^\p{L}\p{M} ]/gu, '').replace(/ {2,}/g, ' ').replace(/^ /, '');
export const soloDigitos = (v: string, max = 20) => v.replace(/\D/g, '').slice(0, max);
/** Celular colombiano: 10 dígitos; quita el +57 si lo pegan. */
export const celular = (v: string) => {
  let d = v.replace(/\D/g, '');
  if (d.length > 10 && d.startsWith('57')) d = d.slice(2);
  return d.slice(0, 10);
};
export const celularValido = (v: string) => /^3\d{9}$/.test(v);
export const formatoCelular = (v: string) => v.replace(/^(\d{3})(\d{3})(\d{0,4}).*/, '$1 $2 $3').trim();

/** Abre un enlace fuera de la app (navegador o WhatsApp). */
export async function abrir(url: string): Promise<void> {
  if (url.startsWith('https://wa.me') || url.startsWith('tel:')) { window.open(url, '_system'); return; }
  await Browser.open({ url });
}

export function iniciales(nombre: string): string {
  const p = nombre.trim().split(/\s+/);
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase();
}
