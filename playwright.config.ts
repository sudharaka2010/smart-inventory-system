import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 30000,
  fullyParallel: true,
  reporter: [
    ['list'],
    ['junit', { outputFile: 'artifacts/junit-results.xml' }],
    ['html', { outputFolder: 'artifacts/html-report', open: 'never' }],
  ],
  use: {
    baseURL: 'http://localhost/rbstorsg', // IMPORTANT: matches your XAMPP path
    headless: true,
    video: 'on',
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
    timezoneId: 'Asia/Colombo',
  },
  projects: [{ name: 'Chromium', use: { ...devices['Desktop Chrome'] } }],
});
