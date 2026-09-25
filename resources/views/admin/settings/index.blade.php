@extends('admin.layout', ['title' => '系统配置'])

@php
    $roleLabels = ['writing' => ['title' => '生成 AI', 'eyebrow' => '文章生成', 'hint' => '负责根据选题、知识库和图片备注生成文章。'], 'review' => ['title' => '审核 AI', 'eyebrow' => '文章审核', 'hint' => '负责检查事实、结构、证据和发布风险。']];
@endphp

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow">智能服务配置</div><h1>系统配置</h1><p class="subtitle">分别配置写作和审核使用的 AI。每张卡保存一次，模型只能从列表中选择。</p></div>
        <span class="badge blue">两个独立角色</span>
    </div>
    @if (session('status'))<div class="notice" style="border-left-color:var(--teal);color:var(--teal-dark);background:#eaf8f4">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ $errors->first() }}</div>@endif

    <div class="ai-role-grid">
        @foreach ($roleLabels as $role => $copy)
            @php($roleConfig = $roleConfigs[$role])
            <section class="panel ai-role-card">
                <div class="panel-header role-card-header">
                    <div><div class="eyebrow">{{ $copy['eyebrow'] }}</div><h2>{{ $copy['title'] }}</h2><p>{{ $copy['hint'] }}</p></div>
                    <span class="badge {{ $roleConfig['configured'] ? 'teal' : 'amber' }}">{{ $roleConfig['configured'] ? '已配置' : '待配置' }}</span>
                </div>
                <div class="panel-body">
                    <form method="post" action="{{ route('admin.settings.ai-role.save') }}" class="role-form" data-role-form>
                        @csrf
                        <input type="hidden" name="role" value="{{ $role }}">
                        <label><span>服务商</span><select name="provider" data-provider-select required>@foreach ($providerCatalog as $provider => $providerInfo)<option value="{{ $provider }}" data-base-url="{{ $providerInfo['base_url'] }}" @selected($roleConfig['provider'] === $provider)>{{ $providerInfo['label'] }}</option>@endforeach</select></label>
                        <label><span>接口 URL</span><input name="base_url" type="url" value="{{ $roleConfig['base_url'] }}" data-base-url-input required></label>
                        <label><span>API 密钥</span><input name="api_key" type="password" placeholder="{{ $roleConfig['configured'] ? '已配置，留空保持不变' : '粘贴 API 密钥' }}"><small>加密保存，不会回显。</small></label>
                        <div class="model-picker"><label><span>使用模型</span><select name="model" data-model-select data-selected-model="{{ $roleConfig['model'] }}" required></select></label><button type="button" class="button ghost fetch-models" data-fetch-models>获取模型</button></div>
                        <div class="role-actions"><span class="model-status" data-model-status>模型列表来自服务商预设</span><button class="button" type="submit">保存 {{ $copy['title'] }} <span aria-hidden="true">→</span></button></div>
                    </form>
                </div>
            </section>
        @endforeach
    </div>

    <form method="post" action="{{ route('admin.settings.update') }}">
        @csrf
        <div class="settings-grid settings-grid-compact">
            <section class="panel settings-section"><div class="panel-header"><div><h2>内容策略</h2><p>控制每日生产量、写作规则和二段审核门槛。</p></div></div><div class="panel-body form-grid"><label><span>每日文章目标</span><input type="number" min="1" max="20" name="topic_daily_target" value="{{ old('topic_daily_target', $settings['topic_daily_target']) }}"><small>建议保持在 3-5 篇。</small></label><label><span>审核通过分数</span><input type="number" min="1" max="100" name="review_pass_threshold" value="{{ old('review_pass_threshold', $settings['review_pass_threshold']) }}"><small>低于此分数会进入人工复核。</small></label><label><span>写作规则</span><select name="writing_skill_slug">@foreach ($writingSkills as $skill)<option value="{{ $skill->slug }}" @selected(old('writing_skill_slug', $settings['writing_skill_slug']) === $skill->slug)>{{ $skill->name }}</option>@endforeach</select><small>新任务会保存所选规则版本。</small></label><label><span>审核规则</span><input value="GEOFlow 二段审核：事实与证据 → 结构与发布风险" readonly><small>每篇文章连续调用审核 AI 两次。</small></label></div></section>
            <section class="panel settings-section"><div class="panel-header"><div><h2>WordPress 发布</h2><p>连接站点并设置自动化边界。</p></div><span class="badge {{ $connection ? 'teal' : 'amber' }}">{{ $connection ? '连接已保存' : '待配置' }}</span></div><div class="panel-body form-grid"><label><span>站点名称</span><input name="wp_name" value="{{ old('wp_name', $connection?->name) }}" placeholder="主 WordPress 站点"></label><label><span>站点地址</span><input type="url" name="wp_base_url" value="{{ old('wp_base_url', $connection?->base_url) }}" placeholder="https://example.com"></label><label><span>用户名</span><input name="wp_username" value="{{ old('wp_username', $connection?->username) }}" placeholder="WordPress 用户名"></label><label><span>应用密码</span><input type="password" name="wp_application_password" placeholder="{{ $connection ? '已配置，留空保持不变' : 'WordPress 应用密码' }}"><small>加密保存，不会回显。</small></label><label><span>默认发布状态</span><select name="wp_default_status"><option value="draft" @selected(old('wp_default_status', $settings['wp_default_status']) === 'draft')>草稿</option><option value="pending" @selected(old('wp_default_status', $settings['wp_default_status']) === 'pending')>待审核</option><option value="publish" @selected(old('wp_default_status', $settings['wp_default_status']) === 'publish')>直接发布</option></select></label><label class="check-field"><input type="checkbox" name="playwright_enabled" value="1" @checked(old('playwright_enabled', $settings['playwright_enabled']))><span><strong>允许浏览器兜底</strong><small>仅在 REST API 不可用时使用。</small></span></label></div></section>
        </div>
        <div class="settings-actions"><a class="button ghost" href="{{ route('admin.dashboard') }}">取消</a><button class="button" type="submit">保存内容与发布设置 <span aria-hidden="true">→</span></button></div>
    </form>
    <script>
        const fillModels = async (form, announce = false) => {
            const providerSelect = form.querySelector('[data-provider-select]');
            const modelSelect = form.querySelector('[data-model-select]');
            const status = form.querySelector('[data-model-status]');
            const provider = providerSelect.value;
            const selected = modelSelect.dataset.selectedModel;
            const apiKey = form.querySelector('input[name="api_key"]').value;
            const button = form.querySelector('[data-fetch-models]');
            button.disabled = true;
            status.textContent = '正在验证密钥并读取真实模型…';
            const response = await fetch('{{ route('admin.settings.models.fetch') }}', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value },
                body: JSON.stringify({ provider, base_url: form.querySelector('[data-base-url-input]').value, api_key: apiKey }),
            });
            const payload = await response.json().catch(() => ({}));
            button.disabled = false;
            if (!response.ok) {
                modelSelect.innerHTML = '';
                status.textContent = payload.message || '获取模型失败，请检查接口 URL 和 API 密钥';
                return;
            }
            modelSelect.innerHTML = Object.entries(payload.models || {}).map(([value, label]) => `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`).join('');
            modelSelect.dataset.selectedModel = modelSelect.value;
            status.textContent = announce ? '模型已更新，可从列表选择' : `可用模型 ${Object.keys(payload.models || {}).length} 个`;
        };
        document.querySelectorAll('[data-role-form]').forEach((form) => {
            const providerSelect = form.querySelector('[data-provider-select]');
            const baseUrlInput = form.querySelector('[data-base-url-input]');
            const fetchButton = form.querySelector('[data-fetch-models]');
            const syncUrl = () => { baseUrlInput.value = providerSelect.selectedOptions[0]?.dataset.baseUrl || ''; };
            providerSelect.addEventListener('change', () => { syncUrl(); form.querySelector('[data-model-select]').dataset.selectedModel = ''; fillModels(form); });
            fetchButton.addEventListener('click', () => fillModels(form, true));
            fillModels(form);
        });
    </script>
@endsection
