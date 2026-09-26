# WordPress 浏览器操作说明

本项目优先使用 WordPress REST API。浏览器自动化只是受控环境下的兜底方式，只有显式设置 `PLAYWRIGHT_ENABLED=true` 后才允许启用。

## REST 配置

1. 为专用 WordPress 操作账号创建应用密码。
2. 在 `/admin/wordpress` 或 `/admin/settings` 保存站点地址、用户名和应用密码。
3. 执行连接健康检查，确认分类、标签、媒体和文章接口可读写。
4. 首次测试只写入草稿，不要直接指向生产发布。

应用密码会加密保存，不会写入日志、审计数据或页面回显。生产环境必须使用 HTTPS；HTTP 只允许在本地或测试环境使用。

## 浏览器冒烟测试

仅使用一次性测试账号运行：

```powershell
$env:WP_TEST_URL = 'https://test.example.com'
$env:WP_TEST_USERNAME = 'operator'
$env:WP_TEST_PASSWORD = 'application-password'
npx playwright test playwright/wordpress-draft-publisher.spec.ts
```

测试会通过页面标签和角色定位元素，创建一篇测试草稿，读取编辑器地址中的文章 ID，最后只删除这篇测试草稿。不要把测试指向生产账号。

## 失败处理

- `401`、`403`：立即停止，检查用户名、应用密码和 WordPress 权限。
- `429`、`5xx`：按退避策略重试，并检查任务运行记录。
- 请求超时：先使用幂等标记查询是否已经写入，再决定是否重试，避免重复文章。
- WordPress 页面标签变化：只更新 Playwright 选择器，REST API 仍保持默认写入路径。

## 图片检查

发布前在文章审核页确认：

- 正文包含匹配到的图片。
- 每张图片都有中文 `alt`。
- 图片地址来自受控媒体预览或 WordPress 媒体接口。
- 图片说明与正文段落语义一致。
- 图片版权状态不是未知或未授权。
