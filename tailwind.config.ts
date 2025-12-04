import type { Config } from 'tailwindcss';

const config: Config = {
  content: [
    './src/**/*.{js,jsx}',
    './includes/**/*.php',
    './surefeedback.php',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
};

export default config;
