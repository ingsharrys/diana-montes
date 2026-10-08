import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.dianamontes.app',
  appName: 'Diana Montes',
  webDir: 'www',
  plugins: {
    SplashScreen: { launchShowDuration: 900, backgroundColor: '#C0105A', showSpinner: false },
  },
};

export default config;
