import type { Config } from 'tailwindcss';

const config: Config = {
  content: [
    './src/**/*.{js,jsx,ts,tsx}',
    './includes/**/*.php',
    './surefeedback-cloud.php',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
};

export default config;
