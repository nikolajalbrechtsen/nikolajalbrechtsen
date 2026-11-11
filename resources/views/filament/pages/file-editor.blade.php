<x-filament-panels::page>
    <div
        x-data="fileEditor()"
        x-init="init()"
        @keydown.window.ctrl.s.prevent="save()"
        @keydown.window.meta.s.prevent="save()"
        class="fe-root"
    >
        {{-- Sidebar: file tree --}}
        <aside class="fe-sidebar">
            <div class="fe-sidebar-head">
                <span class="fe-title">Explorer</span>
                <div class="fe-tools">
                    <button type="button" title="New file" @click="create('file')">
                        <x-filament::icon icon="heroicon-o-document-plus" class="h-4 w-4" />
                    </button>
                    <button type="button" title="New folder" @click="create('dir')">
                        <x-filament::icon icon="heroicon-o-folder-plus" class="h-4 w-4" />
                    </button>
                    <button type="button" title="Rename selected" @click="rename()" :disabled="!selected">
                        <x-filament::icon icon="heroicon-o-pencil" class="h-4 w-4" />
                    </button>
                    <button type="button" title="Delete selected" @click="remove()" :disabled="!selected">
                        <x-filament::icon icon="heroicon-o-trash" class="h-4 w-4" />
                    </button>
                    <button type="button" title="Refresh" @click="refresh()">
                        <x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4" />
                    </button>
                </div>
            </div>
            <input type="search" x-model="filter" placeholder="Filter open folders…" class="fe-filter">
            <div class="fe-tree">
                <template x-for="node in visibleNodes()" :key="node.path">
                    <div
                        class="fe-node"
                        :class="{ 'is-selected': selected === node.path, 'is-active': active === node.path }"
                        :style="`padding-left: ${node.depth * 12 + 8}px`"
                        @click="clickNode(node)"
                        :title="node.path"
                    >
                        <span class="fe-chevron" x-text="node.type === 'dir' ? (expanded[node.path] ? '▾' : '▸') : ''"></span>
                        <span class="fe-icon" x-text="icon(node)"></span>
                        <span class="fe-name" x-text="node.name"></span>
                    </div>
                </template>
            </div>
        </aside>

        {{-- Main: tabs + editor --}}
        <section class="fe-main">
            <div class="fe-tabs">
                <template x-for="tab in tabs" :key="tab.path">
                    <div class="fe-tab" :class="{ 'is-active': active === tab.path }" @click="activate(tab.path)" @auxclick.prevent="closeTab(tab.path)">
                        <span x-text="tab.path.split('/').pop()"></span>
                        <span class="fe-dirty" x-show="tab.dirty">●</span>
                        <button type="button" class="fe-close" @click.stop="closeTab(tab.path)">×</button>
                    </div>
                </template>
            </div>
            <div class="fe-statusbar">
                <span x-text="active || 'No file open'"></span>
                <span class="fe-spacer"></span>
                <span x-show="status" x-text="status"></span>
                <button type="button" class="fe-save" @click="save()" :disabled="!active">Save (Ctrl+S)</button>
            </div>
            <div wire:ignore x-ref="editor" class="fe-editor">
                <div class="fe-empty" x-show="!active">
                    <p>Select a file on the left to start editing.</p>
                    <p class="fe-hint">Tip: CSS/JS changes need “Build CSS/JS”. Config changes need “Clear caches”.</p>
                </div>
            </div>
        </section>
    </div>

    <style>
        .fe-root { display: grid; grid-template-columns: 280px 1fr; height: calc(100vh - 12rem); min-height: 480px; border-radius: .75rem; overflow: hidden; border: 1px solid rgb(0 0 0 / .1); background: #fff; }
        .dark .fe-root { border-color: rgb(255 255 255 / .1); background: #18181b; }
        .fe-sidebar { display: flex; flex-direction: column; border-right: 1px solid rgb(0 0 0 / .08); min-height: 0; background: #f8f8f9; }
        .dark .fe-sidebar { background: #1f1f23; border-color: rgb(255 255 255 / .08); }
        .fe-sidebar-head { display: flex; align-items: center; justify-content: space-between; padding: .5rem .75rem; }
        .fe-title { font-size: .7rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; opacity: .7; }
        .fe-tools { display: flex; gap: .15rem; }
        .fe-tools button { padding: .3rem; border-radius: .35rem; opacity: .75; }
        .fe-tools button:hover:not(:disabled) { opacity: 1; background: rgb(127 127 127 / .15); }
        .fe-tools button:disabled { opacity: .3; cursor: not-allowed; }
        .fe-filter { margin: 0 .5rem .5rem; padding: .3rem .5rem; font-size: .8rem; border-radius: .35rem; border: 1px solid rgb(127 127 127 / .25); background: transparent; }
        .fe-tree { overflow: auto; flex: 1; padding-bottom: 1rem; font-size: .82rem; }
        .fe-node { display: flex; align-items: center; gap: .3rem; padding: .18rem .5rem; cursor: pointer; white-space: nowrap; user-select: none; }
        .fe-node:hover { background: rgb(127 127 127 / .12); }
        .fe-node.is-selected { background: rgb(99 102 241 / .18); }
        .fe-node.is-active .fe-name { font-weight: 600; }
        .fe-chevron { width: .8rem; display: inline-block; opacity: .6; font-size: .7rem; }
        .fe-icon { width: 1.1rem; text-align: center; font-size: .8rem; }
        .fe-name { overflow: hidden; text-overflow: ellipsis; }
        .fe-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; }
        .fe-tabs { display: flex; overflow-x: auto; border-bottom: 1px solid rgb(127 127 127 / .15); background: #f3f3f4; min-height: 2.25rem; }
        .dark .fe-tabs { background: #1b1b1f; }
        .fe-tab { display: flex; align-items: center; gap: .4rem; padding: .45rem .75rem; font-size: .8rem; cursor: pointer; border-right: 1px solid rgb(127 127 127 / .15); white-space: nowrap; opacity: .7; }
        .fe-tab.is-active { opacity: 1; background: #fff; box-shadow: inset 0 -2px 0 rgb(99 102 241); }
        .dark .fe-tab.is-active { background: #1e1e1e; }
        .fe-dirty { color: rgb(99 102 241); font-size: .7rem; }
        .fe-close { opacity: .5; font-size: 1rem; line-height: 1; }
        .fe-close:hover { opacity: 1; }
        .fe-statusbar { display: flex; align-items: center; gap: .75rem; padding: .3rem .75rem; font-size: .75rem; opacity: .85; border-bottom: 1px solid rgb(127 127 127 / .15); font-family: ui-monospace, monospace; }
        .fe-spacer { flex: 1; }
        .fe-save { padding: .2rem .6rem; border-radius: .35rem; background: rgb(99 102 241); color: #fff; font-family: inherit; }
        .fe-save:disabled { opacity: .4; }
        .fe-editor { flex: 1; min-height: 0; position: relative; }
        .fe-empty { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem; opacity: .6; font-size: .9rem; }
        .fe-hint { font-size: .75rem; }
        @media (max-width: 768px) { .fe-root { grid-template-columns: 1fr; grid-template-rows: 220px 1fr; height: auto; } .fe-main { min-height: 60vh; } }
    </style>

    <script>
        window.fileEditor = () => ({
            nodes: {},          // path -> children array
            expanded: { '': true },
            filter: '',
            selected: null,
            active: null,
            tabs: [],
            models: {},
            editor: null,
            monaco: null,
            status: '',

            async init() {
                this.nodes[''] = await this.$wire.listDir('');
                await this.loadMonaco();
            },

            loadMonaco() {
                return new Promise((resolve) => {
                    const boot = () => {
                        window.require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs' } });
                        window.require(['vs/editor/editor.main'], () => {
                            this.monaco = window.monaco;
                            const dark = document.documentElement.classList.contains('dark');
                            this.editor = monaco.editor.create(this.$refs.editor, {
                                theme: dark ? 'vs-dark' : 'vs',
                                automaticLayout: true,
                                fontSize: 14,
                                minimap: { enabled: true },
                                tabSize: 4,
                                wordWrap: 'off',
                                model: null,
                            });
                            this.editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, () => this.save());
                            new MutationObserver(() => {
                                monaco.editor.setTheme(document.documentElement.classList.contains('dark') ? 'vs-dark' : 'vs');
                            }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                            resolve();
                        });
                    };
                    if (window.monaco) { this.monaco = window.monaco; return boot(); }
                    if (window.require && window.require.config) return boot();
                    const s = document.createElement('script');
                    s.src = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/loader.js';
                    s.onload = boot;
                    document.head.appendChild(s);
                });
            },

            visibleNodes() {
                const out = [];
                const f = this.filter.toLowerCase();
                const walk = (path, depth) => {
                    for (const n of (this.nodes[path] || [])) {
                        const match = !f || n.name.toLowerCase().includes(f) || n.type === 'dir';
                        if (match) out.push({ ...n, depth });
                        if (n.type === 'dir' && this.expanded[n.path]) walk(n.path, depth + 1);
                    }
                };
                walk('', 0);
                return out;
            },

            icon(n) {
                if (n.type === 'dir') return this.expanded[n.path] ? '📂' : '📁';
                const ext = n.name.split('.').pop();
                return ({ php: '🐘', js: '🟨', ts: '🔷', css: '🎨', json: '🧾', md: '📝', svg: '🖼', png: '🖼', jpg: '🖼', env: '🔒', sh: '⚙', yml: '⚙', yaml: '⚙' })[ext] || '📄';
            },

            async clickNode(n) {
                this.selected = n.path;
                if (n.type === 'dir') {
                    this.expanded[n.path] = !this.expanded[n.path];
                    if (this.expanded[n.path] && !this.nodes[n.path]) {
                        this.nodes[n.path] = await this.$wire.listDir(n.path);
                    }
                } else {
                    this.openFile(n.path);
                }
            },

            language(path) {
                const name = path.split('/').pop();
                if (name.startsWith('.env')) return 'ini';
                const ext = name.split('.').pop();
                return ({
                    php: 'php', js: 'javascript', mjs: 'javascript', ts: 'typescript', json: 'json', css: 'css', scss: 'scss',
                    html: 'html', md: 'markdown', xml: 'xml', svg: 'xml', yml: 'yaml', yaml: 'yaml', sh: 'shell', sql: 'sql', ini: 'ini',
                })[ext] || 'plaintext';
            },

            async openFile(path) {
                if (this.models[path]) return this.activate(path);
                this.status = 'Opening…';
                const res = await this.$wire.openFile(path);
                this.status = '';
                if (!res.ok) return;
                const model = this.monaco.editor.createModel(res.content, this.language(path), this.monaco.Uri.parse('file:///' + path));
                this.models[path] = model;
                const tab = { path, dirty: false, saved: model.getAlternativeVersionId() };
                this.tabs.push(tab);
                model.onDidChangeContent(() => {
                    const t = this.tabs.find(t => t.path === path);
                    if (t) t.dirty = model.getAlternativeVersionId() !== t.saved;
                });
                this.activate(path);
            },

            activate(path) {
                this.active = path;
                this.selected = path;
                this.editor.setModel(this.models[path]);
                this.editor.focus();
            },

            closeTab(path) {
                const tab = this.tabs.find(t => t.path === path);
                if (tab?.dirty && !confirm('Discard unsaved changes in ' + path + '?')) return;
                this.models[path]?.dispose();
                delete this.models[path];
                this.tabs = this.tabs.filter(t => t.path !== path);
                if (this.active === path) {
                    const next = this.tabs[this.tabs.length - 1];
                    next ? this.activate(next.path) : (this.active = null, this.editor.setModel(null));
                }
            },

            async save() {
                if (!this.active) return;
                const path = this.active;
                const model = this.models[path];
                this.status = 'Saving…';
                const res = await this.$wire.saveFile(path, model.getValue());
                if (res.ok) {
                    const t = this.tabs.find(t => t.path === path);
                    t.saved = model.getAlternativeVersionId();
                    t.dirty = false;
                    this.status = 'Saved ' + new Date().toLocaleTimeString();
                } else {
                    this.status = 'Save failed';
                }
            },

            parentOfSelection() {
                if (!this.selected) return '';
                const all = Object.values(this.nodes).flat();
                const n = all.find(x => x.path === this.selected);
                if (n && n.type === 'dir') return n.path;
                return this.selected.includes('/') ? this.selected.substring(0, this.selected.lastIndexOf('/')) : '';
            },

            async reload(dir) {
                this.nodes[dir] = await this.$wire.listDir(dir);
            },

            async create(type) {
                const dir = this.parentOfSelection();
                const name = prompt(`New ${type === 'dir' ? 'folder' : 'file'} in /${dir}`);
                if (!name) return;
                const res = await this.$wire.createItem(dir ? dir + '/' + name : name, type);
                if (!res.ok) return;
                this.expanded[dir] = true;
                await this.reload(dir);
                if (type === 'file') this.openFile(res.path);
            },

            async rename() {
                if (!this.selected) return;
                const from = this.selected;
                const to = prompt('Rename / move to (path from project root):', from);
                if (!to || to === from) return;
                const res = await this.$wire.renameItem(from, to);
                if (!res.ok) return;
                if (this.models[from]) this.closeTab(from);
                await this.refresh();
                this.selected = res.path;
            },

            async remove() {
                if (!this.selected) return;
                if (!confirm('Delete ' + this.selected + '? This cannot be undone.')) return;
                const path = this.selected;
                const res = await this.$wire.deleteItem(path);
                if (!res.ok) return;
                if (this.models[path]) { this.tabs.find(t => t.path === path).dirty = false; this.closeTab(path); }
                this.selected = null;
                await this.refresh();
            },

            async refresh() {
                for (const dir of Object.keys(this.nodes)) {
                    if (dir === '' || this.expanded[dir]) await this.reload(dir);
                    else delete this.nodes[dir];
                }
            },
        });
    </script>
</x-filament-panels::page>
