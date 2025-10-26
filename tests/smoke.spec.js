const { test, expect } = require('@playwright/test');

test('home page loads', async ({ page }) => {
  await page.goto('/'); // resolves to http://localhost/rbstorsg/
  // Relax this to match any text you certainly have on the homepage:
  await expect(page).toHaveTitle(/RB|Store|Dashboard/i);
});
