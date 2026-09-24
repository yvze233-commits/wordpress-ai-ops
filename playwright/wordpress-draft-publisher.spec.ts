import { test, expect } from '@playwright/test';

const enabled = Boolean(process.env.WP_TEST_URL && process.env.WP_TEST_USERNAME && process.env.WP_TEST_PASSWORD);

test.describe('WordPress draft browser fallback', () => {
  test.skip(!enabled, 'Set WP_TEST_URL, WP_TEST_USERNAME and WP_TEST_PASSWORD for the opt-in smoke test.');

  test('logs in, creates a draft, and removes the test draft', async ({ page }) => {
    const marker = `AI Ops browser smoke ${Date.now()}`;
    await page.goto('/wp-login.php');
    await page.getByLabel(/用户名|username/i).fill(process.env.WP_TEST_USERNAME!);
    await page.getByLabel(/密码|password/i).fill(process.env.WP_TEST_PASSWORD!);
    await page.getByRole('button', { name: /登录|log in/i }).click();
    await expect(page).toHaveURL(/wp-admin/);

    await page.goto('/wp-admin/post-new.php');
    await page.getByLabel(/添加标题|add title|标题/i).fill(marker);
    const editor = page.locator('[contenteditable="true"]').first();
    await editor.fill('<p>Browser fallback smoke draft.</p>');
    await page.getByRole('button', { name: /保存草稿|save draft/i }).click();
    await expect(page.getByText(/已保存|saved/i)).toBeVisible();

    const postId = new URL(page.url()).searchParams.get('post');
    expect(postId).toBeTruthy();
    await page.goto(`/wp-admin/post.php?post=${postId}&action=trash`);
    await expect(page).toHaveURL(/wp-admin/);
  });
});
