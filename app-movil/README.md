# App móvil · Red de Diana Lucía Montes

App en **Ionic 8 + Angular 20 + Capacitor 7**. Habla con la API del panel (`admin/api.php`).

| Quién | Cómo entra | Qué ve |
|---|---|---|
| Simpatizante | Su celular + código de 6 números que llega por **WhatsApp** | Su nivel y puntos, enlace para invitar, su red, sus tareas |
| Equipo (dirección, coordinación, líderes, digitación) | **Correo y contraseña** del panel | Tablero, simpatizantes (completar/verificar), registrar, tareas y validación |

La sesión queda guardada en el teléfono: simpatizantes 180 días y equipo 30 días. El plazo se renueva cada vez que abren la app y dura hasta que cierren sesión. Los permisos son los mismos de la web: el líder solo ve su red y la digitación ve los datos protegidos.

## Requisitos

- Node.js 20 o superior.
- Android Studio para generar el APK y/o Xcode (en Mac) para iPhone.

## Probar en el computador

```bash
cd app-movil
npm install
npm start          # abre http://localhost:8100
```

En desarrollo la app usa `src/environments/environment.ts` (API en `http://localhost:8080`).
Si WhatsApp no está configurado y `APP_ENV` no es `prod`, la pantalla de ingreso muestra el código de prueba.

## Generar el APK (Android)

```bash
cd app-movil
npm install
npm run build:prod                     # usa src/environments/environment.prod.ts
npx cap add android                    # solo la primera vez
npx @capacitor/assets generate --android   # íconos y splash desde resources/ (opcional)
npx cap sync android
npx cap open android                   # Android Studio → Build › Generate Signed App Bundle / APK
```

Para iPhone, el proceso es el mismo con `ios` en lugar de `android`; se necesita un Mac con Xcode.

## Configuración

- **URL de la API**: `src/environments/environment.prod.ts` → `apiUrl`.
- **Identificador de la app**: `capacitor.config.ts` → `appId` (`com.dianamontes.app`).
- **Servidor** (`admin/config/config.php`):
  - `WA_PLANTILLA_OTP` (por defecto `codigo_acceso`) y `WA_PLANTILLA_OTP_IDIOMA` (`es`).
  - `API_ORIGENES`: orígenes extra permitidos. Los de la app (`capacitor://localhost` y `https://localhost`) ya están permitidos.
- La plantilla del código se crea desde **Admin › WhatsApp › Plantillas › Código de acceso**. Meta la clasifica en la categoría **Autenticación**.
