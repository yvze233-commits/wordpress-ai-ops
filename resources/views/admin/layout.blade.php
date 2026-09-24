<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '运营工作台' }} · GEOFlow</title>
    <style>
        :root {
            --ink: #17212b;
            --muted: #71808e;
            --line: #e5eaee;
            --surface: #ffffff;
            --canvas: #f4f6f7;
            --nav: #14252b;
            --nav-soft: #203b42;
            --teal: #1ca79a;
            --teal-dark: #11786f;
            --amber: #c98b22;
            --red: #c65650;
            --blue: #4c76a8;
            --shadow: 0 8px 26px rgba(25, 44, 55, .06);
        }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--ink); background: var(--canvas); font: 14px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif; }
        a { color: inherit; text-decoration: none; }
        button, input, select { font: inherit; }
        button, .button { border: 0; border-radius: 5px; cursor: pointer; }
        .app-shell { min-height: 100vh; display: flex; }
        .sidebar { width: 224px; flex: 0 0 224px; padding: 22px 14px; color: #d9e6e8; background: var(--nav); }
        .brand { display: flex; align-items: center; gap: 11px; padding: 3px 11px 28px; }
        .brand-mark { width: 31px; height: 31px; display: grid; place-items: center; color: #0f2f31; background: #a5e5d8; border-radius: 8px; font-weight: 800; letter-spacing: -1px; }
        .brand strong { display: block; color: #f4fbfb; font-size: 15px; letter-spacing: .2px; }
        .brand small { display: block; margin-top: 1px; color: #84a2a8; font-size: 11px; }
        .nav-label { padding: 0 11px; color: #739399; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; }
        .nav { margin: 8px 0 23px; display: grid; gap: 3px; }
        .nav a { display: flex; align-items: center; gap: 10px; min-height: 38px; padding: 0 11px; color: #a9c1c4; border-radius: 6px; transition: background .15s ease, color .15s ease; }
        .nav a:hover, .nav a.active { color: #f3ffff; background: var(--nav-soft); }
        .nav-icon { width: 18px; color: #72b9b3; font-size: 15px; text-align: center; }
        .sidebar-note { margin: 22px 8px 0; padding: 13px 12px; border: 1px solid #2a4a51; border-radius: 7px; color: #a8c4c6; background: rgba(255,255,255,.035); font-size: 12px; }
        .sidebar-note strong { display: block; margin-bottom: 4px; color: #e1f5f1; font-size: 12px; }
        .main { min-width: 0; flex: 1; }
        .topbar { height: 66px; display: flex; align-items: center; justify-content: space-between; padding: 0 34px; background: var(--surface); border-bottom: 1px solid var(--line); }
        .crumb { color: var(--muted); font-size: 13px; }
        .crumb strong { color: var(--ink); font-weight: 650; }
        .topbar-right { display: flex; align-items: center; gap: 14px; color: var(--muted); font-size: 12px; }
        .connection-dot { width: 7px; height: 7px; display: inline-block; margin-right: 5px; border-radius: 50%; background: var(--teal); box-shadow: 0 0 0 3px #def4ee; }
        .avatar { width: 30px; height: 30px; display: grid; place-items: center; color: #22544e; background: #d9f0e9; border-radius: 50%; font-size: 12px; font-weight: 700; }
        .content { max-width: 1380px; margin: 0 auto; padding: 30px 34px 54px; }
        .page-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; margin-bottom: 24px; }
        .eyebrow { margin-bottom: 5px; color: var(--teal-dark); font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 4px; font-size: 27px; line-height: 1.2; letter-spacing: -.02em; }
        h2 { margin-bottom: 4px; font-size: 17px; line-height: 1.3; }
        h3 { margin-bottom: 3px; font-size: 14px; }
        .subtitle { margin: 0; color: var(--muted); }
        .button { display: inline-flex; align-items: center; gap: 7px; min-height: 35px; padding: 0 13px; color: #fff; background: var(--teal-dark); font-weight: 650; }
        .button:hover { background: #0d675f; }
        .button.ghost { color: var(--teal-dark); background: #eef8f6; }
        .button.ghost:hover { background: #dff1ed; }
        .button.warn { color: #81580f; background: #fff4d9; }
        .button.warn:hover { background: #ffedbf; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 8px; box-shadow: var(--shadow); }
        .panel-header { display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 18px 20px; border-bottom: 1px solid var(--line); }
        .panel-header p { margin: 0; color: var(--muted); font-size: 12px; }
        .panel-body { padding: 20px; }
        .stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 13px; margin-bottom: 19px; }
        .stat { padding: 17px 18px; background: var(--surface); border: 1px solid var(--line); border-radius: 8px; box-shadow: var(--shadow); }
        .stat-top { display: flex; align-items: center; justify-content: space-between; color: var(--muted); font-size: 12px; }
        .stat-icon { width: 26px; height: 26px; display: grid; place-items: center; border-radius: 6px; color: var(--teal-dark); background: #e3f5f0; font-size: 14px; }
        .stat strong { display: block; margin: 9px 0 2px; font-size: 25px; line-height: 1; letter-spacing: -.04em; }
        .stat small { color: var(--muted); font-size: 11px; }
        .progress { height: 5px; margin: 14px 0 7px; overflow: hidden; border-radius: 4px; background: #e7efed; }
        .progress span { display: block; height: 100%; border-radius: inherit; background: var(--teal); }
        .progress-meta { display: flex; justify-content: space-between; color: var(--muted); font-size: 11px; }
        .workspace-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(290px, .8fr); gap: 19px; }
        .stack { display: grid; gap: 19px; align-content: start; }
        .pipeline { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 9px; }
        .pipeline-step { position: relative; min-height: 86px; padding: 13px 12px; border: 1px solid var(--line); border-radius: 7px; background: #fbfcfc; }
        .pipeline-step:not(:last-child)::after { content: '›'; position: absolute; right: -9px; top: 27px; z-index: 1; color: #a7b7bb; font-size: 20px; background: var(--surface); }
        .step-index { color: var(--teal-dark); font-size: 11px; font-weight: 700; }
        .pipeline-step strong { display: block; margin: 7px 0 3px; font-size: 13px; }
        .pipeline-step small { color: var(--muted); font-size: 11px; }
        .review-list { display: grid; }
        .review-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 15px; padding: 14px 0; border-bottom: 1px solid var(--line); }
        .review-row:last-child { border-bottom: 0; padding-bottom: 0; }
        .review-row:first-child { padding-top: 0; }
        .review-title { display: block; overflow: hidden; color: var(--ink); font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
        .review-meta { display: block; margin-top: 3px; color: var(--muted); font-size: 11px; }
        .badge { display: inline-flex; align-items: center; min-height: 23px; padding: 0 8px; border-radius: 20px; font-size: 11px; font-weight: 650; white-space: nowrap; }
        .badge.teal { color: #11786f; background: #e3f5f0; }
        .badge.amber { color: #8a6217; background: #fff3d3; }
        .badge.red { color: #a64641; background: #fbe8e7; }
        .badge.blue { color: #426791; background: #eaf0f8; }
        .badge.gray { color: #66747b; background: #edf0f1; }
        .asset-list { display: grid; gap: 13px; }
        .asset { display: flex; align-items: center; gap: 11px; }
        .asset-mark { width: 31px; height: 31px; display: grid; place-items: center; border-radius: 7px; color: #146c66; background: #e3f5f0; font-size: 14px; }
        .asset-copy { min-width: 0; flex: 1; }
        .asset-copy strong { display: block; font-size: 13px; }
        .asset-copy small { display: block; color: var(--muted); font-size: 11px; }
        .asset-count { color: var(--ink); font-size: 16px; font-weight: 700; }
        .empty { padding: 24px 0 6px; color: var(--muted); text-align: center; }
        .empty strong { display: block; margin-bottom: 3px; color: var(--ink); }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 13px 15px; border-bottom: 1px solid var(--line); text-align: left; white-space: nowrap; }
        th { color: var(--muted); background: #fafbfb; font-size: 11px; font-weight: 650; letter-spacing: .04em; }
        td { font-size: 13px; }
        tr:last-child td { border-bottom: 0; }
        .article { max-width: 840px; padding: 27px 30px; }
        .article h1 { margin-bottom: 17px; font-size: 30px; }
        .article p, .article li { color: #3d4b54; font-size: 15px; line-height: 1.9; }
        .notice { margin-bottom: 17px; padding: 12px 14px; border-left: 3px solid var(--amber); color: #775815; background: #fff8e8; font-size: 13px; }
        .settings-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 19px; }
        .settings-grid-compact { margin-top: 19px; }
        .settings-section { min-width: 0; }
        .ai-workspace { margin-bottom: 19px; }
        .ai-role-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 19px; }
        .ai-role-card { min-width: 0; }
        .role-card-header { min-height: 116px; align-items: flex-start; }
        .role-card-header h2 { font-size: 20px; }
        .role-form { display: grid; gap: 15px; }
        .model-picker { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: end; gap: 9px; }
        .fetch-models { min-height: 38px; white-space: nowrap; }
        .role-actions { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding-top: 2px; }
        .model-status { color: var(--muted); font-size: 11px; }
        .ai-workspace-heading { align-items: flex-start; }
        .ai-workspace-heading h2 { margin-bottom: 4px; font-size: 20px; }
        .routing-panel { margin-top: 19px; }
        .connection-table { display: grid; margin-bottom: 22px; border: 1px solid var(--line); border-radius: 7px; overflow: hidden; }
        .connection-row { display: grid; grid-template-columns: 1.2fr 1.5fr .65fr .55fr; align-items: center; gap: 14px; padding: 12px 13px; border-top: 1px solid var(--line); }
        .connection-row:first-child { border-top: 0; }
        .connection-head { color: var(--muted); background: #fafbfb; font-size: 11px; font-weight: 650; }
        .connection-row strong, .connection-row small { display: block; }
        .connection-row small { margin-top: 2px; color: var(--muted); font-size: 11px; }
        .connection-url { overflow: hidden; color: var(--muted); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
        .subsection-heading { display: flex; align-items: baseline; gap: 10px; margin-bottom: 10px; }
        .subsection-heading strong { font-size: 13px; }
        .subsection-heading span { color: var(--muted); font-size: 11px; }
        .connection-form { display: grid; grid-template-columns: 1fr 1.15fr 1.35fr 1.25fr auto; align-items: end; gap: 10px; }
        .connection-form label { gap: 5px; }
        .connection-form label span { color: var(--muted); font-size: 11px; font-weight: 500; }
        .ai-connection-grid { display: grid; grid-template-columns: 1fr 1.3fr 1.2fr; gap: 16px; padding-bottom: 22px; border-bottom: 1px solid var(--line); }
        label em { color: var(--muted); font-size: 11px; font-style: normal; font-weight: 400; }
        .task-routing-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px 0 11px; }
        .task-routing-heading strong { display: block; font-size: 13px; }
        .task-routing-heading span:not(.badge) { display: block; margin-top: 2px; color: var(--muted); font-size: 11px; }
        .task-routing { display: grid; gap: 9px; }
        .task-row { display: grid; grid-template-columns: 36px minmax(180px, 1fr) minmax(190px, .9fr) minmax(160px, .7fr); align-items: center; gap: 13px; padding: 12px 13px; border: 1px solid var(--line); border-radius: 7px; background: #fbfcfc; }
        .task-mark { width: 26px; height: 26px; display: grid; place-items: center; color: var(--teal-dark); background: #e3f5f0; border-radius: 5px; font-size: 10px; font-weight: 700; }
        .task-copy strong { display: block; font-size: 13px; }
        .task-copy small { display: block; margin-top: 2px; color: var(--muted); font-size: 11px; }
        .task-row label { gap: 5px; }
        .task-row label span { color: var(--muted); font-size: 11px; font-weight: 500; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 17px; }
        label { display: grid; gap: 7px; color: var(--ink); font-size: 13px; font-weight: 650; }
        label small { color: var(--muted); font-size: 11px; font-weight: 400; }
        input, select, textarea { width: 100%; min-height: 38px; padding: 0 11px; color: var(--ink); background: #f8faf9; border: 1px solid #d9e3e1; border-radius: 5px; outline: none; }
        textarea { padding: 9px 11px; resize: vertical; font: inherit; line-height: 1.6; }
        input:focus, select:focus, textarea:focus { border-color: var(--teal); box-shadow: 0 0 0 3px #dff3ee; }
        .check-field { display: flex; align-items: flex-start; gap: 9px; padding-top: 28px; }
        .check-field input { width: 17px; min-height: 17px; margin-top: 2px; accent-color: var(--teal); }
        .check-field span { display: grid; gap: 3px; }
        .settings-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 19px; }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .pagination { margin-top: 18px; }
        @media (max-width: 1050px) { .sidebar { width: 190px; flex-basis: 190px; } .content { padding: 25px 22px 45px; } .topbar { padding: 0 22px; } .workspace-grid, .settings-grid, .ai-role-grid, .ai-connection-grid, .connection-form { grid-template-columns: 1fr; } }
        @media (max-width: 720px) { .app-shell { display: block; } .sidebar { width: auto; padding: 12px 13px; } .brand { padding: 3px 8px 12px; } .nav-label, .sidebar-note { display: none; } .nav { grid-template-columns: repeat(5, minmax(0, 1fr)); margin: 0; gap: 3px; } .nav a { justify-content: center; min-height: 34px; padding: 0 5px; font-size: 0; } .nav-icon { font-size: 15px; } .topbar { height: 55px; padding: 0 16px; } .topbar-right span { display: none; } .content { padding: 22px 15px 35px; } .page-heading { display: block; } .page-heading .button { margin-top: 15px; } h1 { font-size: 23px; } .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .pipeline { grid-template-columns: repeat(2, minmax(0, 1fr)); } .pipeline-step:not(:last-child)::after { display: none; } .panel-body, .panel-header { padding: 16px; } .article { padding: 20px; } .form-grid, .task-row, .connection-row { grid-template-columns: 1fr; } .connection-head { display: none; } .task-mark { display: none; } .check-field { padding-top: 0; } }
    </style>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('admin.dashboard') }}">
            <span class="brand-mark">GF</span>
            <span><strong>GEOFlow Ops</strong><small>WordPress 内容运营</small></span>
        </a>
        <div class="nav-label">工作台</div>
        <nav class="nav">
            <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><span class="nav-icon">⌂</span><span>运营概览</span></a>
            <a class="{{ request()->routeIs('admin.batches.*') ? 'active' : '' }}" href="{{ route('admin.batches.index') }}"><span class="nav-icon">▦</span><span>内容批次</span></a>
            <a class="{{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}" href="{{ route('admin.reviews.index') }}"><span class="nav-icon">✓</span><span>文章审核</span></a>
            <a class="{{ request()->routeIs('admin.wordpress.*') ? 'active' : '' }}" href="{{ route('admin.wordpress.index') }}"><span class="nav-icon">↗</span><span>发布连接</span></a>
            <a class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}"><span class="nav-icon">⚙</span><span>系统配置</span></a>
        </nav>
        <div class="nav-label">资产配置</div>
        <nav class="nav">
            <a class="{{ request()->routeIs('admin.sources.*') ? 'active' : '' }}" href="{{ route('admin.sources.index') }}"><span class="nav-icon">◌</span><span>热点来源</span></a>
            <a class="{{ request()->routeIs('admin.titles.*') ? 'active' : '' }}" href="{{ route('admin.titles.index') }}"><span class="nav-icon">≡</span><span>标题库</span></a>
            <a class="{{ request()->routeIs('admin.knowledge.*') ? 'active' : '' }}" href="{{ route('admin.knowledge.index') }}"><span class="nav-icon">▤</span><span>知识库</span></a>
            <a class="{{ request()->routeIs('admin.images.*') ? 'active' : '' }}" href="{{ route('admin.images.index') }}"><span class="nav-icon">▧</span><span>图片库</span></a>
            <a class="{{ request()->routeIs('admin.skills.*') ? 'active' : '' }}" href="{{ route('admin.skills.index') }}"><span class="nav-icon">✦</span><span>Skills</span></a>
        </nav>
        <div class="sidebar-note"><strong>自动发布已就绪</strong>每日目标 {{ $data['daily_target'] ?? 4 }} 篇，审核通过后写入 WordPress 草稿。</div>
    </aside>
    <div class="main">
        <header class="topbar">
            <div class="crumb">运营工作台 <span aria-hidden="true">/</span> <strong>{{ $title ?? '概览' }}</strong></div>
            <div class="topbar-right"><span><i class="connection-dot"></i>本地服务正常</span><span>{{ now()->format('Y年m月d日') }}</span><span class="avatar">OP</span></div>
        </header>
        <main class="content">@yield('content')</main>
    </div>
</div>
</body>
</html>
