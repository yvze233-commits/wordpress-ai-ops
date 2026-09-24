import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './playwright',
  timeout: 45_000,
  use: {
    baseURL: process.env.WP_TEST_URL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
});
