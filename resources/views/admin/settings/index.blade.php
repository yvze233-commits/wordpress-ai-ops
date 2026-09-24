@extends('admin.layout', ['title' => '系统配置'])

@php
    $writingConnectionId = old('writing_connection_id', $assignments['writing']['connection_id']);
    $reviewConnectionId = old('review_connection_id', $assignments['review']['connection_id']);
    $writingModel = old('writing_model', $assignments['writing']['model']);
    $reviewModel = old('review_model', $assignments['review']['model']);
@endphp

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow">Workspace settings</div><h1>系统配置</h1><p class="subtitle">先保存服务商连接，再把不同 AI 分配给写作和审核任务。</p></div>
        <span class="badge teal">{{ $aiConnections->count() }} 个 AI 连接</span>
    </div>
    @if (session('status'))<div class="notice" style="border-left-color:var(--teal);color:var(--teal-dark);background:#eaf8f4">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ $errors->first() }}</div>@endif

    <section class="panel ai-workspace">
        <div class="panel-header ai-workspace-heading"><div><div class="eyebrow">AI connections</div><h2>AI 服务商连接</h2><p>每个服务商只配置一次密钥。配置完成后，任务分配只需要选择，不需要手填模型。</p></div><span class="badge {{ $aiConnections->isNotEmpty() ? 'teal' : 'amber' }}">{{ $aiConnections->isNotEmpty() ? '可分配任务' : '先添加连接' }}</span></div>
        <div class="panel-body">
            @if ($aiConnections->isNotEmpty())
                <div class="connection-table" role="table" aria-label="已配置的 AI 服务商">
                    <div class="connection-row connection-head" role="row"><span>服务商</span><span>接口地址</span><span>密钥</span><span>状态</span></div>
                    @foreach ($aiConnections as $aiConnection)
                        <div class="connection-row" role="row"><span><strong>{{ $aiConnection->name }}</strong><small>{{ $providerCatalog[$aiConnection->provider]['label'] ?? $aiConnection->provider }}</small></span><span class="connection-url">{{ $aiConnection->base_url }}</span><span><span class="badge teal">已配置</span></span><span><span class="badge {{ $aiConnection->status === 'active' ? 'teal' : 'red' }}">{{ $aiConnection->status === 'active' ? '正常' : '异常' }}</span></span></div>
                    @endforeach
                </div>
            @else
                <div class="empty"><strong>还没有 AI 服务商连接</strong>先在下面添加一个 GPT、DeepSeek 或豆包连接。</div>
            @endif
            <div class="subsection-heading"><strong>添加或更新服务商</strong><span>同一服务商再次保存会更新原连接，不会创建重复配置。</span></div>
            <form class="connection-form" method="post" action="{{ route('admin.settings.connections.store') }}">
                @csrf
                <label><span>服务商</span><select id="ai-provider" name="provider" required>@foreach ($providerCatalog as $value => $providerInfo)<option value="{{ $value }}" data-base-url="{{ $providerInfo['base_url'] }}">{{ $providerInfo['label'] }}</option>@endforeach</select></label>
                <label><span>连接名称</span><input name="name" required placeholder="例如 GPT 写作账号"></label>
                <label><span>API 地址</span><input id="ai-base-url" type="url" name="base_url" placeholder="由服务商自动填入"></label>
                <label><span>API 密钥</span><input type="password" name="api_key" required placeholder="粘贴密钥"><small>加密保存，不会回显。</small></label>
                <button class="button" type="submit">保存连接</button>
            </form>
        </div>
    </section>

    <section class="panel ai-workspace routing-panel">
        <div class="panel-header ai-workspace-heading"><div><div class="eyebrow">Task routing</div><h2>任务分配</h2><p>文章写作和文章审核可以使用不同的服务商与模型。</p></div><span class="badge blue">选择制</span></div>
        <div class="panel-body">
            @if ($aiConnections->isNotEmpty())
                <form method="post" action="{{ route('admin.settings.routing.update') }}">
                    @csrf
                    <div class="task-routing">
                        @foreach (['writing' => ['label' => '文章写作', 'hint' => '根据选题、知识库和图片备注生成正文', 'connection' => $writingConnectionId, 'model' => $writingModel], 'review' => ['label' => '文章审核', 'hint' => '检查事实、证据、结构、风险和图片适配', 'connection' => $reviewConnectionId, 'model' => $reviewModel]] as $task => $taskInfo)
                            <div class="task-row"><div class="task-mark">{{ $task === 'writing' ? '01' : '02' }}</div><div class="task-copy"><strong>{{ $taskInfo['label'] }}</strong><small>{{ $taskInfo['hint'] }}</small></div><label><span>服务商连接</span><select class="task-connection" name="{{ $task }}_connection_id" data-model-target="{{ $task }}-model">@foreach ($aiConnections as $aiConnection)<option value="{{ $aiConnection->id }}" data-provider="{{ $aiConnection->provider }}" @selected((string) $taskInfo['connection'] === (string) $aiConnection->id)>{{ $aiConnection->name }} · {{ $providerCatalog[$aiConnection->provider]['label'] ?? $aiConnection->provider }}</option>@endforeach</select></label><label><span>模型预设</span><select id="{{ $task }}-model" name="{{ $task }}_model" data-selected="{{ $taskInfo['model'] }}"></select></label></div>
                        @endforeach
                    </div>
                    <div class="settings-actions"><button class="button" type="submit">保存任务分配 <span aria-hidden="true">→</span></button></div>
                </form>
            @else
                <div class="empty"><strong>添加服务商后才能分配任务</strong>先保存至少一个 AI 连接，再选择写作和审核模型。</div>
            @endif
        </div>
    </section>

    <form method="post" action="{{ route('admin.settings.update') }}">
        @csrf
        <div class="settings-grid settings-grid-compact">
            <section class="panel settings-section"><div class="panel-header"><div><h2>内容策略</h2><p>控制每日生产量和自动审核门槛。</p></div></div><div class="panel-body form-grid"><label><span>每日文章目标</span><input type="number" min="1" max="20" name="topic_daily_target" value="{{ old('topic_daily_target', $settings['topic_daily_target']) }}"><small>建议保持在 3-5 篇。</small></label><label><span>审核通过分数</span><input type="number" min="1" max="100" name="review_pass_threshold" value="{{ old('review_pass_threshold', $settings['review_pass_threshold']) }}"><small>低于此分数会进入人工复核。</small></label></div></section>
            <section class="panel settings-section"><div class="panel-header"><div><h2>WordPress 发布</h2><p>连接站点并设置自动化边界。</p></div><span class="badge {{ $connection ? 'teal' : 'amber' }}">{{ $connection ? '连接已保存' : '待配置' }}</span></div><div class="panel-body form-grid"><label><span>站点名称</span><input name="wp_name" value="{{ old('wp_name', $connection?->name) }}" placeholder="主 WordPress 站点"></label><label><span>站点地址</span><input type="url" name="wp_base_url" value="{{ old('wp_base_url', $connection?->base_url) }}" placeholder="https://example.com"></label><label><span>用户名</span><input name="wp_username" value="{{ old('wp_username', $connection?->username) }}" placeholder="WordPress 用户名"></label><label><span>应用密码</span><input type="password" name="wp_application_password" placeholder="{{ $connection ? '已配置，留空保持不变' : 'WordPress 应用密码' }}"><small>加密保存，不会回显。</small></label><label><span>默认发布状态</span><select name="wp_default_status"><option value="draft" @selected(old('wp_default_status', $settings['wp_default_status']) === 'draft')>草稿</option><option value="pending" @selected(old('wp_default_status', $settings['wp_default_status']) === 'pending')>待审核</option><option value="publish" @selected(old('wp_default_status', $settings['wp_default_status']) === 'publish')>直接发布</option></select></label><label class="check-field"><input type="checkbox" name="playwright_enabled" value="1" @checked(old('playwright_enabled', $settings['playwright_enabled']))><span><strong>允许浏览器兜底</strong><small>仅在 REST API 不可用时使用。</small></span></label></div></section>
        </div>
        <div class="settings-actions"><a class="button ghost" href="{{ route('admin.dashboard') }}">取消</a><button class="button" type="submit">保存内容与发布设置 <span aria-hidden="true">→</span></button></div>
    </form>
    <script>
        const providerCatalog = @json($providerCatalog);
        const updateTaskModels = (connectionSelect) => {
            const target = document.getElementById(connectionSelect.dataset.modelTarget);
            const provider = connectionSelect.selectedOptions[0]?.dataset.provider;
            const models = providerCatalog[provider]?.models || {};
            const selected = target.dataset.selected;
            target.innerHTML = Object.entries(models).map(([value, label]) => `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`).join('');
            target.dataset.selected = target.value;
        };
        document.querySelectorAll('.task-connection').forEach((select) => { updateTaskModels(select); select.addEventListener('change', () => { select.closest('.task-row').querySelector('select[id$="-model"]').dataset.selected = ''; updateTaskModels(select); }); });
        const providerSelect = document.getElementById('ai-provider');
        const baseUrlInput = document.getElementById('ai-base-url');
        const updateBaseUrl = () => { baseUrlInput.value = providerSelect.selectedOptions[0]?.dataset.baseUrl || ''; };
        updateBaseUrl();
        providerSelect.addEventListener('change', updateBaseUrl);
    </script>
@endsection
