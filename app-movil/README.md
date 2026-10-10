# App móvil · Red de Diana Lucía Montes

App en **Ionic 8 + Angular 20 + Capacitor 7**. Habla con la API del panel (`admin/api.php`).

| Quién | Cómo entra | Qué ve |
|---|---|---|
| Simpatizante | Su celular + código de 6 números que llega por **WhatsApp** | Su nivel y puntos, enlace para invitar, su red, sus tareas |
| Equipo (dirección, coordinación, líderes, digitación) | **Correo y contraseña** del panel | Tablero, simpatizantes (completar/verificar), registrar, tareas y validación |

La sesión queda guardada en el teléfono: simpatizantes 180 días y equipo 30 días. El plazo se renueva cada vez que abren la app y dura hasta que cierren sesión. Los permisos son los mismos de la web: el líder solo ve su red y la digitación ve los datos protegidos.

## Requisitos

- Node.js 20 o superior.
- Para Android: GitHub Actions (no hace falta instalar nada) o Android Studio. Para iPhone: Xcode en un Mac.

## Probar en el computador

```bash
cd app-movil
npm install
npm start          # abre http://localhost:8100
```

En desarrollo la app usa `src/environments/environment.ts` (API en `http://localhost:8080`).
Si WhatsApp no está configurado y `APP_ENV` no es `prod`, la pantalla de ingreso muestra el código de prueba.

## Android y Google Play

El proyecto de Android ya está en `android/`, con íconos, pantalla de inicio y SDK objetivo 36 (el que exige Google Play).

- Paquete: `com.dianamontes.app`. Una vez publicado en Play no se puede cambiar.
- Versión: `versionName` está en `android/app/build.gradle`. El `versionCode` se pasa al compilar con `-PdmVersionCode=N` y debe subir en cada envío a Play.
- Firma: la llave de subida (`.jks`) **nunca va al repositorio**. Para compilar se lee de `android/keystore.properties` (ignorado por git) o de las variables `DM_KEYSTORE`, `DM_KEYSTORE_PASSWORD`, `DM_KEY_ALIAS` y `DM_KEY_PASSWORD`.

**Compilar en GitHub (recomendado).** Ve a Actions → "App Android (Google Play)" → Run workflow. Al terminar, descarga el artefacto `app-android`. Trae el `.aab` para subir a Play y un `.apk` para instalar a mano. Antes hay que crear los 4 secretos que se describen en `.github/workflows/android-play.yml`.

**Compilar en tu computador** (Android Studio):

```bash
cd app-movil
npm install
npm run build:prod && npx cap sync android
cd android && ./gradlew bundleRelease -PdmVersionCode=2   # → app/build/outputs/bundle/release/app-release.aab
```

Si cambias `resources/icon.png` o `resources/splash.png`, vuelve a generar los íconos con `npx @capacitor/assets generate --android`.

## Compilar para iPhone (iOS)

El proyecto de Xcode ya está en `ios/`. Usa Swift Package Manager, así que no hace falta CocoaPods, y ya trae los íconos y la pantalla de inicio. Solo se puede compilar en un Mac con **Xcode 16 o superior** (Mac App Store).

```bash
cd app-movil
npm install
npm run ios        # compila la web, la copia a ios/ (cap sync) y abre Xcode
```

En Xcode:

1. Selecciona el proyecto **App** › pestaña **Signing & Capabilities** › **Team**: tu cuenta de Apple. El identificador es `com.dianamontes.app`.
2. **Probar en tu iPhone:** conéctalo por cable, elígelo arriba y oprime ▶.
   - Con una cuenta de Apple gratuita, la app funciona 7 días; después hay que volver a instalarla.
   - En el iPhone, la primera vez: Ajustes › General › VPN y gestión de dispositivos › confiar en el desarrollador.
3. **Publicar** (TestFlight o App Store) requiere el Apple Developer Program (99 USD al año):
   - en Xcode, elige **Any iOS Device (arm64)** › **Product › Archive** › **Distribute App** › **App Store Connect**;
   - luego invita a los probadores desde TestFlight.

Después de cambiar el código de la app, repite `npm run ios`, o `npm run build:prod && npx cap sync ios`.

## Configuración

- **URL de la API**: `src/environments/environment.prod.ts` → `apiUrl`.
- **Identificador de la app**: `capacitor.config.ts` → `appId` (`com.dianamontes.app`).
- **Servidor** (`admin/config/config.php`):
  - `WA_PLANTILLA_OTP` (por defecto `codigo_acceso`) y `WA_PLANTILLA_OTP_IDIOMA` (`es`).
  - `API_ORIGENES`: orígenes extra permitidos. Los de la app (`capacitor://localhost` y `https://localhost`) ya están permitidos.
- La plantilla del código se crea desde **Admin › WhatsApp › Plantillas › Código de acceso**. Meta la clasifica en la categoría **Autenticación**.
