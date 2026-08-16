<x-mcp::app title="OpnForm draft preview">
    <x-slot:head>
        <style>
            :root { color-scheme: light dark; }
            body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; color: var(--color-text-primary, #111827); }
            main { padding: 0; }
            .preview-surface { border-radius: 14px; padding: 18px; background: var(--color-background-secondary, #f5f5f5); }
            .header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
            .heading { min-width: 0; }
            .header-actions { display: flex; align-items: center; gap: 8px; }
            .eyebrow { display: flex; align-items: center; gap: 8px; margin: 0 0 10px; color: var(--color-text-secondary, #4b5563); font-size: 13px; font-weight: 650; }
            .eyebrow::before { width: 8px; height: 8px; border-radius: 999px; background: #3b82f6; content: ''; }
            h1 { margin: 0 0 8px; font-size: 20px; }
            .meta { margin: 0; color: var(--color-text-secondary, #6b7280); font-size: 13px; }
            .zoom-controls { display: flex; align-items: center; overflow: hidden; border: 1px solid var(--color-border-primary, #d1d5db); border-radius: 8px; background: var(--color-background-primary, #fff); }
            .zoom-button { display: grid; width: 32px; height: 32px; place-items: center; border: 0; border-radius: 0; padding: 0; background: transparent; color: var(--color-text-primary, #111827); font-size: 17px; font-weight: 500; cursor: pointer; }
            .zoom-button:hover:not(:disabled), .zoom-value:hover { background: var(--color-background-secondary, #f3f4f6); }
            .zoom-button:disabled { cursor: default; opacity: .35; }
            .zoom-value { min-width: 46px; height: 32px; border: 0; border-right: 1px solid var(--color-border-primary, #d1d5db); border-left: 1px solid var(--color-border-primary, #d1d5db); border-radius: 0; padding: 0 6px; background: transparent; color: var(--color-text-secondary, #4b5563); font-size: 11px; font-variant-numeric: tabular-nums; cursor: pointer; }
            .preview-viewport { width: 100%; margin-top: 18px; overflow: hidden; }
            .preview-canvas { position: relative; margin: 0 auto; }
            iframe { display: block; width: 1280px; height: 480px; border: 0; background: transparent; transform-origin: top left; }
            ol { padding-left: 22px; margin: 18px 0; }
            li { margin: 10px 0; }
            .open-button { display: inline-flex; flex: none; height: 32px; align-items: center; border: 0; border-radius: 8px; padding: 0 10px; background: #2563eb; color: white; font-size: 12px; font-weight: 650; cursor: pointer; }
            .open-button:disabled { cursor: wait; opacity: .6; }
            .empty { color: var(--color-text-secondary, #6b7280); font-style: italic; }
            @media (max-width: 520px) {
                .preview-surface { padding: 14px; }
                .header { align-items: stretch; flex-direction: column; }
                .header-actions { justify-content: space-between; }
                .eyebrow { margin-bottom: 8px; }
                h1 { font-size: 17px; }
                .open-button { padding: 6px 8px; }
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
                const canvasWidth = 1280;
                const focusedHeight = 720;
                const defaultZoom = 0.8;
                const minimumZoom = 0.5;
                const zoomStep = 0.1;
                let requestedZoom = defaultZoom;
                let naturalHeight = 480;
                let presentationStyle = 'classic';
                let resizeScriptPromise;

                const applyPreviewLayout = () => {
                    const availableWidth = previewViewport.clientWidth;
                    const fitZoom = availableWidth > 0 ? Math.min(1, availableWidth / canvasWidth) : 1;
                    const effectiveMinimumZoom = Math.min(minimumZoom, fitZoom);
                    const zoom = Math.max(effectiveMinimumZoom, Math.min(requestedZoom, fitZoom));
                    const height = presentationStyle === 'focused' ? focusedHeight : naturalHeight;
                    preview.style.width = `${canvasWidth}px`;
                    preview.style.height = `${height}px`;
                    preview.style.transform = `scale(${zoom})`;
                    previewCanvas.style.width = `${canvasWidth * zoom}px`;
                    previewCanvas.style.height = `${height * zoom}px`;
                    zoomResetButton.textContent = `${Math.round(zoom * 100)}%`;
                    zoomOutButton.disabled = zoom <= effectiveMinimumZoom;
                    zoomInButton.disabled = zoom >= fitZoom;
                };

                zoomOutButton.onclick = () => {
                    const currentZoom = Number.parseInt(zoomResetButton.textContent, 10) / 100;
                    requestedZoom = Math.max(0.1, currentZoom - zoomStep);
                    applyPreviewLayout();
                };
                zoomResetButton.onclick = () => {
                    requestedZoom = defaultZoom;
                    applyPreviewLayout();
                };
                zoomInButton.onclick = () => {
                    const currentZoom = Number.parseInt(zoomResetButton.textContent, 10) / 100;
                    requestedZoom = Math.min(1, currentZoom + zoomStep);
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

                app.onToolResult((result) => {
                    const payload = result?.structuredContent ?? result?.structured_content ?? {};
                    const draft = payload.draft ?? {};
                    const definition = draft.definition ?? {};
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

                    openButton.disabled = !payload.editor_url;
                    openButton.onclick = () => {
                        if (window.openai?.openExternal) {
                            return window.openai.openExternal({ href: payload.editor_url, redirectUrl: false });
                        }

                        return app.openLink({ url: payload.editor_url });
                    };
                });

                app.autoResize();
            });
        </script>
    </x-slot:head>

    <main>
        <section class="preview-surface">
            <header class="header">
                <div class="heading">
                    <p class="eyebrow">Private preview</p>
                    <h1 id="title">Loading preview…</h1>
                    <p id="meta" class="meta"></p>
                </div>
                <div class="header-actions">
                    <div class="zoom-controls" role="group" aria-label="Preview zoom">
                        <button id="zoom-out" class="zoom-button" type="button" title="Zoom out" aria-label="Zoom out">−</button>
                        <button id="zoom-reset" class="zoom-value" type="button" title="Reset zoom">80%</button>
                        <button id="zoom-in" class="zoom-button" type="button" title="Zoom in" aria-label="Zoom in">+</button>
                    </div>
                    <button id="open-editor" class="open-button" type="button" disabled>Open in OpnForm</button>
                </div>
            </header>
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
