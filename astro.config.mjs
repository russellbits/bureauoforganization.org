// @ts-check
import { defineConfig } from 'astro/config';

// https://astro.build/config
export default defineConfig({
  site: 'https://www.bureauoforganization.org',
  output: 'static',
  outDir: './build',
  server: {
    port: 22118,
  }
});
