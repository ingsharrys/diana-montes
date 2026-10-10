import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.dianamontes.app',
  appName: 'Diana Montes',
  webDir: 'www',
  // Android 15+ dibuja la app bajo las barras del sistema: Capacitor ajusta los márgenes
  android: { adjustMarginsForEdgeToEdge: 'auto' },
  plugins: {
    SplashScreen: { launchShowDuration: 900, backgroundColor: '#C0105A', showSpinner: false },
  },
};

export default config;
