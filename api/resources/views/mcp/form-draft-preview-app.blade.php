<x-mcp::app title="OpnForm draft preview">
    <x-slot:head>
        <style>
            :root { color-scheme: light dark; }
            body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; color: var(--color-text-primary, #111827); }
            main { padding: 0; }
            .preview-surface { border-radius: 12px; padding: 11px; background: var(--color-background-secondary, #f5f5f5); }
            .header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
            .heading { min-width: 0; }
            .draft-summary { display: flex; align-items: baseline; gap: 7px; margin-top: 3px; }
            .header-actions { display: flex; flex: none; align-items: center; gap: 7px; }
            .eyebrow { display: flex; align-items: center; gap: 6px; margin: 0; color: var(--color-text-secondary, #4b5563); font-size: 11px; font-weight: 650; line-height: 1.2; }
            .eyebrow::before { width: 6px; height: 6px; border-radius: 999px; background: #3b82f6; content: ''; }
            h1 { overflow: hidden; margin: 0; font-size: 16px; line-height: 1.25; text-overflow: ellipsis; white-space: nowrap; }
            .meta { flex: none; margin: 0; color: var(--color-text-secondary, #6b7280); font-size: 11px; line-height: 1.25; }
            .zoom-controls { display: flex; align-items: center; overflow: hidden; border: 1px solid var(--color-border-primary, #d1d5db); border-radius: 7px; background: var(--color-background-primary, #fff); }
            .zoom-button { display: grid; width: 28px; height: 28px; place-items: center; border: 0; border-radius: 0; padding: 0; background: transparent; color: var(--color-text-primary, #111827); font-size: 15px; font-weight: 500; cursor: pointer; }
            .zoom-button:hover:not(:disabled), .zoom-value:hover { background: var(--color-background-secondary, #f3f4f6); }
            .zoom-button:disabled { cursor: default; opacity: .35; }
            .zoom-value { min-width: 43px; height: 28px; border: 0; border-right: 1px solid var(--color-border-primary, #d1d5db); border-left: 1px solid var(--color-border-primary, #d1d5db); border-radius: 0; padding: 0 5px; background: transparent; color: var(--color-text-secondary, #4b5563); font-size: 10px; font-variant-numeric: tabular-nums; cursor: pointer; }
            .preview-viewport { width: 100%; margin-top: 10px; overflow: hidden; }
            .preview-canvas { position: relative; width: 100%; margin: 0; }
            iframe { display: block; width: 100%; height: 480px; border: 0; background: transparent; transform-origin: top left; }
            ol { padding-left: 22px; margin: 18px 0; }
            li { margin: 10px 0; }
            .open-button { display: inline-flex; flex: none; height: 28px; align-items: center; border: 0; border-radius: 7px; padding: 0 9px; background: #2563eb; color: white; font-size: 11px; font-weight: 650; cursor: pointer; }
            .open-button:disabled { cursor: wait; opacity: .6; }
            .action-status { margin: 7px 0 0; color: #b42318; font-size: 11px; line-height: 1.35; }
            .empty { color: var(--color-text-secondary, #6b7280); font-style: italic; }
            @media (max-width: 520px) {
                .preview-surface { padding: 10px; }
                .header { align-items: stretch; flex-direction: column; }
                .header-actions { justify-content: space-between; }
                h1 { font-size: 15px; }
            }
        </style>
        <script type="module">
            createMcpApp(async (app) => {
                const title = document.getElementById('title');
                const meta = document.getElementById('meta');
                const fields = document.getElementById('fields');
                const preview = document.getElementById('preview');
                const previewViewport = document.getElementById('preview-viewport');
                const previewCanvas = document.getElementById('preview-canvas');
                const openButton = document.getElementById('open-editor');
                const zoomOutButton = document.getElementById('zoom-out');
                const zoomResetButton = document.getElementById('zoom-reset');
                const zoomInButton = document.getElementById('zoom-in');
                const focusedHeight = 720;
                const zoomLevels = [0.75, 0.85, 1];
                const defaultZoomIndex = 1;
                let zoomIndex = defaultZoomIndex;
                let naturalHeight = 480;
                let presentationStyle = 'classic';
                let resizeScriptPromise;
                let draftHandle = null;
                let rendered = false;
                const initialOpenButtonText = openButton.textContent;
                const loadingFallback = window.setTimeout(() => {
                    if (rendered) return;

                    title.textContent = 'Preview unavailable';
                    meta.textContent = 'Ask ChatGPT to refresh this preview.';
                    fields.hidden = true;
                }, 5000);

                const applyPreviewLayout = () => {
                    const availableWidth = previewViewport.clientWidth;
                    if (availableWidth <= 0) return;

                    const zoom = zoomLevels[zoomIndex];
                    const height = presentationStyle === 'focused' ? focusedHeight : naturalHeight;
                    preview.style.width = `${availableWidth / zoom}px`;
                    preview.style.height = `${height}px`;
                    preview.style.transform = `scale(${zoom})`;
                    previewCanvas.style.width = `${availableWidth}px`;
                    previewCanvas.style.height = `${height * zoom}px`;
                    zoomResetButton.textContent = `${Math.round(zoom * 100)}%`;
                    zoomOutButton.disabled = zoomIndex === 0;
                    zoomInButton.disabled = zoomIndex === zoomLevels.length - 1;
                };

                zoomOutButton.onclick = () => {
                    zoomIndex = Math.max(0, zoomIndex - 1);
                    applyPreviewLayout();
                };
                zoomResetButton.onclick = () => {
                    zoomIndex = defaultZoomIndex;
                    applyPreviewLayout();
                };
                zoomInButton.onclick = () => {
                    zoomIndex = Math.min(zoomLevels.length - 1, zoomIndex + 1);
                    applyPreviewLayout();
                };

                new ResizeObserver(applyPreviewLayout).observe(previewViewport);

                const loadIframeResizer = (origin) => {
                    if (window.iFrameResize) {
                        return Promise.resolve();
                    }

                    if (!resizeScriptPromise) {
                        resizeScriptPromise = new Promise((resolve, reject) => {
                            const script = document.createElement('script');
                            script.src = `${origin}/widgets/iframeResize.min.js`;
                            script.onload = resolve;
                            script.onerror = reject;
                            document.head.appendChild(script);
                        });
                    }

                    return resizeScriptPromise;
                };

                const toolPayload = (result) => result?.structuredContent
                    ?? result?.structured_content
                    ?? result
                    ?? {};

                const applyToolInput = (input) => {
                    const args = input?.arguments ?? input ?? {};
                    draftHandle = args.draft_handle ?? draftHandle;
                    openButton.disabled = !draftHandle;
                };

                const renderToolResult = (result) => {
                    const payload = toolPayload(result);
                    const draft = payload.draft ?? {};
                    const definition = draft.definition ?? {};

                    if (!payload.preview_url && !draft.version && !definition.title) {
                        return false;
                    }

                    rendered = true;
                    window.clearTimeout(loadingFallback);
                    draftHandle = payload.draft_handle ?? draftHandle;
                    presentationStyle = definition.presentation_style ?? 'classic';
                    title.textContent = definition.title ?? 'Untitled form';
                    meta.textContent = `Draft v${draft.version ?? '?'} · ${presentationStyle} layout`;
                    applyPreviewLayout();
                    if (payload.preview_url) {
                        const previewUrl = new URL(payload.preview_url);
                        previewUrl.searchParams.set('embedded', '1');
                        preview.src = previewUrl.toString();
                        preview.hidden = false;
                        previewViewport.hidden = false;
                        applyPreviewLayout();
                        fields.hidden = true;
                        loadIframeResizer(previewUrl.origin).then(() => {
                            if (!preview.iFrameResizer) {
                                window.iFrameResize({
                                    checkOrigin: [previewUrl.origin],
                                    log: false,
                                    scrolling: false,
                                    sizeHeight: presentationStyle !== 'focused',
                                    onResized: ({ height }) => {
                                        if (presentationStyle === 'classic') {
                                            naturalHeight = height;
                                            applyPreviewLayout();
                                        }
                                    },
                                }, preview);
                            }
                        }).catch(() => {
                            preview.style.overflow = 'auto';
                        });
                    }
                    fields.replaceChildren();

                    const visibleFields = (definition.properties ?? []).filter((field) => !field.hidden);
                    if (visibleFields.length === 0) {
                        fields.innerHTML = '<li class="empty">No visible blocks yet.</li>';
                    } else {
                        for (const field of visibleFields) {
                            const item = document.createElement('li');
                            item.textContent = field.name ?? field.type ?? 'Untitled block';
                            fields.appendChild(item);
                        }
                    }

                    openButton.disabled = !draftHandle;

                    return true;
                };

                app.onToolInput(applyToolInput);
                app.onToolResult(renderToolResult);

                applyToolInput(window.openai?.toolInput);
                renderToolResult(window.openai?.toolOutput);

                window.addEventListener('openai:set_globals', (event) => {
                    const globals = event.detail?.globals ?? {};
                    applyToolInput(globals.toolInput ?? window.openai?.toolInput);
                    renderToolResult(globals.toolOutput ?? window.openai?.toolOutput);
                });

                openButton.onclick = async () => {
                    if (!draftHandle) return;

                    const actionStatus = document.getElementById('action-status');
                    openButton.disabled = true;
                    openButton.textContent = 'Opening…';
                    actionStatus.hidden = true;

                    try {
                        const result = await app.callServerTool('open_form_draft_in_editor', {
                            draft_handle: draftHandle,
                        });
                        const payload = toolPayload(result);

                        if (result?.isError || !payload.editor_url) {
                            throw new Error('Editor link unavailable');
                        }

                        await app.openLink({ url: payload.editor_url });
                    } catch (error) {
                        actionStatus.textContent = 'Could not open OpnForm. Please try again.';
                        actionStatus.hidden = false;
                    } finally {
                        openButton.textContent = initialOpenButtonText;
                        openButton.disabled = !draftHandle;
                    }
                };

                app.autoResize();
            });
        </script>
    </x-slot:head>

    <main>
        <section class="preview-surface">
            <header class="header">
                <div class="heading">
                    <p class="eyebrow">Private preview</p>
                    <div class="draft-summary">
                        <h1 id="title">Loading preview…</h1>
                        <p id="meta" class="meta"></p>
                    </div>
                </div>
                <div class="header-actions">
                    <div class="zoom-controls" role="group" aria-label="Preview zoom">
                        <button id="zoom-out" class="zoom-button" type="button" title="Zoom out" aria-label="Zoom out">−</button>
                        <button id="zoom-reset" class="zoom-value" type="button" title="Reset zoom">85%</button>
                        <button id="zoom-in" class="zoom-button" type="button" title="Zoom in" aria-label="Zoom in">+</button>
                    </div>
                    <button id="open-editor" class="open-button" type="button" disabled>Open in OpnForm</button>
                </div>
            </header>
            <p id="action-status" class="action-status" role="status" aria-live="polite" hidden></p>
            <div id="preview-viewport" class="preview-viewport" hidden>
                <div id="preview-canvas" class="preview-canvas">
                    <iframe
                        id="preview"
                        title="OpnForm draft preview"
                        sandbox="allow-forms allow-modals allow-popups allow-scripts allow-same-origin"
                        referrerpolicy="no-referrer"
                        hidden
                    ></iframe>
                </div>
            </div>
            <ol id="fields"></ol>
        </section>
    </main>
</x-mcp::app>
