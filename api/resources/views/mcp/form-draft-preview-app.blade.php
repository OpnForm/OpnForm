<x-mcp::app title="OpnForm draft preview">
    <x-slot:head>
        <style>
            :root { color-scheme: light dark; }
            body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; color: var(--color-text-primary, #111827); }
            main { padding: 18px; }
            .card { border: 1px solid var(--color-border-primary, #d1d5db); border-radius: 14px; padding: 18px; background: var(--color-background-primary, transparent); }
            .header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
            .heading { min-width: 0; }
            h1 { margin: 0 0 8px; font-size: 20px; }
            .meta { margin: 0; color: var(--color-text-secondary, #6b7280); font-size: 13px; }
            iframe { width: 100%; height: 620px; margin: 16px 0; border: 1px solid var(--color-border-primary, #d1d5db); border-radius: 10px; background: white; }
            ol { padding-left: 22px; margin: 18px 0; }
            li { margin: 10px 0; }
            button { flex: none; border: 0; border-radius: 8px; padding: 7px 10px; background: #2563eb; color: white; font-size: 12px; font-weight: 650; cursor: pointer; }
            button:disabled { cursor: wait; opacity: .6; }
            .empty { color: var(--color-text-secondary, #6b7280); font-style: italic; }
            @media (max-width: 520px) {
                main { padding: 12px; }
                .card { padding: 14px; }
                .header { align-items: center; }
                h1 { font-size: 17px; }
                button { padding: 6px 8px; }
            }
        </style>
        <script type="module">
            createMcpApp(async (app) => {
                const title = document.getElementById('title');
                const meta = document.getElementById('meta');
                const fields = document.getElementById('fields');
                const preview = document.getElementById('preview');
                const openButton = document.getElementById('open-editor');

                app.onToolResult((result) => {
                    const payload = result?.structuredContent ?? result?.structured_content ?? {};
                    const draft = payload.draft ?? {};
                    const definition = draft.definition ?? {};
                    title.textContent = definition.title ?? 'Untitled form';
                    meta.textContent = `Draft v${draft.version ?? '?'} · ${definition.presentation_style ?? 'classic'} layout`;
                    if (payload.preview_url) {
                        preview.src = payload.preview_url;
                        preview.hidden = false;
                        fields.hidden = true;
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
        <section class="card">
            <header class="header">
                <div class="heading">
                    <h1 id="title">Loading preview…</h1>
                    <p id="meta" class="meta"></p>
                </div>
                <button id="open-editor" disabled>Open in OpnForm</button>
            </header>
            <iframe id="preview" title="OpnForm draft preview" hidden></iframe>
            <ol id="fields"></ol>
        </section>
    </main>
</x-mcp::app>
