$(function () {
    const $editor = $('[data-docs-editor]')
    const stateElement = document.getElementById('docs-pro-editor-state')

    if (!$editor.length || !stateElement) {
        return
    }

    const initialState = JSON.parse(stateElement.textContent || '{}')
    const state = {
        nodes: [],
        selectedId: null,
        dirty: false,
        activeTab: 'content',
        tempCounter: 1,
        statusOptions: initialState.status_options || {},
        labels: initialState.labels || {},
    }

    const debounce = (callback, wait = 300) => {
        let timeout = null

        return (...args) => {
            clearTimeout(timeout)
            timeout = setTimeout(() => callback(...args), wait)
        }
    }

    const normalizeId = (value) => value === null || value === undefined || value === '' ? null : String(value)
    const escapeHtml = (value) => $('<div>').text(value ?? '').html()
    const getTreeContainer = () => $('#docs-pro-editor-tree-container')
    const getPanelContainer = () => $('#docs-pro-editor-panel-container')
    const getCurrentNode = () => state.nodes.find((node) => node.id === state.selectedId) || null
    const getChildren = (parentId = null) => state.nodes
        .filter((node) => normalizeId(node.parent_id) === normalizeId(parentId))
        .sort((first, second) => {
            if (first.sort_order !== second.sort_order) {
                return first.sort_order - second.sort_order
            }

            return first.name.localeCompare(second.name)
        })

    const normalizeNode = (node, index = 0) => ({
        id: normalizeId(node.id),
        parent_id: normalizeId(node.parent_id),
        node_type: ['doc', 'title', 'separator'].includes(node.node_type) ? node.node_type : 'doc',
        name: String(node.name || ''),
        slug_path: node.slug_path || '',
        excerpt: node.excerpt || '',
        markdown_content: node.markdown_content || '',
        content: node.content || '',
        preview_html: node.content || '',
        status: node.status || 'draft',
        is_default: Boolean(node.is_default),
        is_section: Boolean(node.is_section),
        sort_order: Number.isFinite(Number(node.sort_order)) ? Number(node.sort_order) : index,
    })

    const normalizeNodeHierarchy = (nodes) => {
        const grouped = new Map()
        const counters = new Map()
        const normalized = []
        const sortNodes = (items) => [...items].sort((first, second) => {
            if (first.sort_order !== second.sort_order) {
                return first.sort_order - second.sort_order
            }

            return first.name.localeCompare(second.name)
        })
        const nextSortOrder = (parentId) => {
            const key = normalizeId(parentId) ?? '__root__'
            const current = counters.get(key) ?? 0
            counters.set(key, current + 1)

            return current
        }

        nodes.forEach((node) => {
            const parentKey = normalizeId(node.parent_id) ?? '__root__'
            const siblings = grouped.get(parentKey) || []
            siblings.push({ ...node })
            grouped.set(parentKey, siblings)
        })

        const visit = (sourceParentId = null, targetParentId = null) => {
            const sourceKey = normalizeId(sourceParentId) ?? '__root__'

            sortNodes(grouped.get(sourceKey) || []).forEach((node) => {
                const normalizedNode = {
                    ...node,
                    parent_id: normalizeId(targetParentId),
                    sort_order: nextSortOrder(targetParentId),
                }

                normalized.push(normalizedNode)

                if (node.node_type === 'separator') {
                    return
                }

                visit(node.id, ['doc', 'title'].includes(node.node_type) ? node.id : targetParentId)
            })
        }

        visit()

        return normalized
    }

    const setNodes = (nodes) => {
        state.nodes = normalizeNodeHierarchy((nodes || []).map((node, index) => normalizeNode(node, index)))
        state.tempCounter = state.nodes.reduce((max, node) => {
            if (!node.id || !node.id.startsWith('tmp-')) {
                return max
            }

            const suffix = Number(node.id.replace('tmp-', ''))

            return Number.isFinite(suffix) ? Math.max(max, suffix + 1) : max
        }, 1)
    }

    const getSortedNodes = () => {
        const nodes = []

        const addChildren = (parentId = null) => {
            getChildren(parentId).forEach((node) => {
                nodes.push(node)
                addChildren(node.id)
            })
        }

        addChildren(null)

        return nodes
    }

    const ensureLocalDefaultDoc = () => {
        const docs = state.nodes.filter((node) => node.node_type === 'doc')

        if (!docs.length) {
            return
        }

        const defaultDoc = docs.find((node) => node.is_default)

        if (defaultDoc) {
            docs.forEach((node) => {
                node.is_default = node.id === defaultDoc.id
            })

            return
        }

        docs[0].is_default = true
    }

    const findPreferredNodeId = () => {
        const nodes = getSortedNodes().filter((node) => node.node_type !== 'separator')

        return nodes.find((node) => node.node_type === 'doc' && node.is_default)?.id
            || nodes.find((node) => node.node_type === 'doc')?.id
            || nodes.find((node) => node.node_type === 'title')?.id
            || null
    }

    const syncCurrentNodeMeta = () => {
        $editor.data('current-node', state.selectedId || '')
        $editor.attr('data-current-node', state.selectedId || '')
        $editor.data('current-node-type', getCurrentNode()?.node_type || '')
        $editor.attr('data-current-node-type', getCurrentNode()?.node_type || '')
    }

    const updateSaveButtons = () => {
        $('[data-docs-save-all], [data-docs-save-button]').prop('disabled', !state.dirty)
    }

    const setDirty = (value = true) => {
        state.dirty = value
        $editor.toggleClass('has-unsaved-changes', value)
        updateSaveButtons()
    }

    const hydrateState = (payload) => {
        setNodes(payload.nodes || [])
        state.selectedId = normalizeId(payload.selected_node_id)

        ensureLocalDefaultDoc()

        if (!getCurrentNode() || getCurrentNode().node_type === 'separator') {
            state.selectedId = findPreferredNodeId()
        }

        syncCurrentNodeMeta()
        setDirty(false)
        renderAll()
    }

    const renderTreeNodes = (parentId = null) => getChildren(parentId).map((node) => {
        const typeLabel = state.labels[`node_type_${node.node_type}`] || node.node_type
        const nested = renderTreeNodes(node.id)

        if (node.node_type === 'separator') {
            return `
                <li class="dd-item docs-pro-editor-tree-item" data-id="${escapeHtml(node.id)}">
                    <div class="docs-pro-editor-tree-row">
                        <div class="dd-handle docs-pro-editor-tree-handle" title="Drag">
                            <span class="docs-pro-editor-tree-grip" aria-hidden="true"></span>
                        </div>
                        <div class="docs-pro-editor-tree-static">
                            <span class="docs-pro-editor-tree-copy">
                                <span class="docs-pro-editor-tree-separator" aria-hidden="true"></span>
                            </span>
                            <span class="docs-pro-editor-tree-badges">
                                <span class="badge bg-secondary-lt">${escapeHtml(typeLabel)}</span>
                            </span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger docs-pro-editor-tree-action" data-docs-delete-node data-node-id="${escapeHtml(node.id)}" title="${escapeHtml(state.labels.delete || 'Delete')}">&times;</button>
                    </div>
                    ${nested ? `<ol class="dd-list">${nested}</ol>` : ''}
                </li>
            `
        }

        const sectionBadge = node.node_type === 'doc' && node.is_section
            ? `<span class="badge bg-secondary-lt">${escapeHtml(state.labels.editor_section_badge || 'Section')}</span>`
            : ''
        const defaultBadge = node.is_default
            ? `<span class="badge bg-primary-lt">${escapeHtml(state.labels.editor_default_badge || 'Default')}</span>`
            : ''

        return `
            <li class="dd-item docs-pro-editor-tree-item ${node.id === state.selectedId ? 'is-selected' : ''}" data-id="${escapeHtml(node.id)}">
                <div class="docs-pro-editor-tree-row">
                    <div class="dd-handle docs-pro-editor-tree-handle" title="Drag">
                        <span class="docs-pro-editor-tree-grip" aria-hidden="true"></span>
                    </div>
                    <button type="button" class="docs-pro-editor-tree-button" data-docs-node-id="${escapeHtml(node.id)}">
                        <span class="docs-pro-editor-tree-copy">
                            <span class="docs-pro-editor-tree-title">${escapeHtml(node.name)}</span>
                        </span>
                        <span class="docs-pro-editor-tree-badges">
                            <span class="badge bg-secondary-lt">${escapeHtml(typeLabel)}</span>
                            ${sectionBadge}
                            ${defaultBadge}
                        </span>
                    </button>
                </div>
                ${nested ? `<ol class="dd-list">${nested}</ol>` : ''}
            </li>
        `
    }).join('')

    const renderTree = () => {
        if (!state.nodes.length) {
            getTreeContainer().html(`
                <div class="docs-pro-editor-empty">
                    <h4 class="mb-2">${escapeHtml(state.labels.editor_no_docs_title || '')}</h4>
                    <p class="text-muted mb-0">${escapeHtml(state.labels.editor_no_docs_description || '')}</p>
                </div>
            `)

            return
        }

        getTreeContainer().html(`
            <div class="dd docs-pro-editor-tree" id="docs-pro-editor-tree">
                <ol class="dd-list">${renderTreeNodes()}</ol>
            </div>
        `)
    }

    const renderStatusOptions = (selectedValue) => Object.entries(state.statusOptions).map(([value, label]) => `
        <option value="${escapeHtml(value)}" ${selectedValue === value ? 'selected' : ''}>${escapeHtml(label)}</option>
    `).join('')

    const renderPanel = () => {
        const node = getCurrentNode()

        if (!node) {
            getPanelContainer().html(`
                <div class="card docs-pro-editor-card">
                    <div class="card-body">
                        <div class="docs-pro-editor-empty">
                            <h3 class="mb-2">${escapeHtml(state.labels.editor_select_title || '')}</h3>
                            <p class="text-muted mb-0">${escapeHtml(state.labels.editor_select_description || '')}</p>
                        </div>
                    </div>
                </div>
            `)

            return
        }

        if (node.node_type === 'title') {
            getPanelContainer().html(`
                <div class="card docs-pro-editor-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title mb-1">${escapeHtml(state.labels.editor_panel_title || '')}</h3>
                            <p class="text-muted mb-0">${escapeHtml(state.labels.editor_preview_doc_only || '')}</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="docs-pro-editor-form">
                            <div class="mb-4">
                                <label for="docs-pro-title-name" class="form-label">${escapeHtml(state.labels.form_name || '')}</label>
                                <input id="docs-pro-title-name" type="text" class="form-control" name="name" value="${escapeHtml(node.name)}" maxlength="255" required>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary" data-docs-save-button ${state.dirty ? '' : 'disabled'}>${escapeHtml(state.labels.save || 'Save')}</button>
                                <button type="button" class="btn btn-outline-danger" data-docs-delete-node data-node-id="${escapeHtml(node.id)}">${escapeHtml(state.labels.delete || 'Delete')}</button>
                            </div>
                        </form>
                    </div>
                </div>
            `)

            return
        }

        const previewHtml = node.preview_html || node.content || `<p class="text-muted mb-0">${escapeHtml(state.labels.editor_preview_empty || '')}</p>`
        const legacyAlert = !node.markdown_content && node.content
            ? `<div class="alert alert-warning mt-4 mb-0" data-docs-legacy-alert>${escapeHtml(state.labels.editor_legacy_content_notice || '')}</div>`
            : ''

        getPanelContainer().html(`
            <div class="card docs-pro-editor-card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-1">${escapeHtml(state.labels.editor_panel_title || '')}</h3>
                        <p class="text-muted mb-0">${escapeHtml(state.labels.editor_panel_description || '')}</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="docs-pro-editor-tabs">
                        <div class="docs-pro-editor-tablist" role="tablist">
                            <button type="button" class="docs-pro-editor-tab ${state.activeTab === 'content' ? 'is-active' : ''}" data-docs-panel-tab="content">
                                <span>${escapeHtml(state.labels.editor_tab_content || 'Content')}</span>
                                <small>${escapeHtml(state.labels.editor_use_markdown || '')}</small>
                            </button>
                            <button type="button" class="docs-pro-editor-tab ${state.activeTab === 'preview' ? 'is-active' : ''}" data-docs-panel-tab="preview">
                                <span>${escapeHtml(state.labels.editor_tab_preview || 'Preview')}</span>
                            </button>
                        </div>
                        <div class="docs-pro-editor-pane ${state.activeTab === 'content' ? '' : 'is-hidden'}" data-docs-panel-pane="content">
                            <form id="docs-pro-editor-form">
                                <div class="row g-3">
                                    <div class="col-lg-8">
                                        <label for="docs-pro-doc-name" class="form-label">${escapeHtml(state.labels.form_name || '')}</label>
                                        <input id="docs-pro-doc-name" type="text" class="form-control" name="name" value="${escapeHtml(node.name)}" maxlength="255" required>
                                    </div>
                                    <div class="col-lg-4">
                                        <label for="docs-pro-doc-status" class="form-label">${escapeHtml(state.labels.form_status || '')}</label>
                                        <select id="docs-pro-doc-status" class="form-select" name="status">${renderStatusOptions(node.status)}</select>
                                    </div>
                                    <div class="col-12">
                                        <label for="docs-pro-doc-excerpt" class="form-label">${escapeHtml(state.labels.form_excerpt || '')}</label>
                                        <textarea id="docs-pro-doc-excerpt" class="form-control" name="excerpt" rows="3">${escapeHtml(node.excerpt)}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_default" value="1" ${node.is_default ? 'checked' : ''}>
                                            <span class="form-check-label">${escapeHtml(state.labels.form_doc_is_default || '')}</span>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_section" value="1" ${node.is_section ? 'checked' : ''}>
                                            <span class="form-check-label">${escapeHtml(state.labels.form_is_section || '')}</span>
                                        </label>
                                    </div>
                                    <div class="col-12">
                                        <div class="docs-pro-editor-field-head">
                                            <label for="docs-pro-doc-markdown" class="form-label">${escapeHtml(state.labels.form_content || 'Content')}</label>
                                            <span>${escapeHtml(state.labels.editor_use_markdown || '')}</span>
                                        </div>
                                        <textarea id="docs-pro-doc-markdown" class="form-control docs-pro-editor-textarea" name="markdown_content" rows="24" style="min-height: 42rem; height: 70vh;" data-docs-markdown-input>${escapeHtml(node.markdown_content)}</textarea>
                                    </div>
                                </div>
                                ${legacyAlert}
                                <div class="d-flex flex-wrap gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary" data-docs-save-button ${state.dirty ? '' : 'disabled'}>${escapeHtml(state.labels.save || 'Save')}</button>
                                    <button type="button" class="btn btn-outline-danger" data-docs-delete-node data-node-id="${escapeHtml(node.id)}">${escapeHtml(state.labels.delete || 'Delete')}</button>
                                </div>
                            </form>
                        </div>
                        <div class="docs-pro-editor-pane ${state.activeTab === 'preview' ? '' : 'is-hidden'}" data-docs-panel-pane="preview">
                            <div class="docs-pro-editor-preview-wrap">
                                <div class="docs-pro-editor-preview" data-docs-preview>${previewHtml}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `)
    }

    const initTree = () => {
        const $tree = $('#docs-pro-editor-tree')

        if (!$tree.length) {
            return
        }

        $tree.nestable({
            group: 1,
            maxDepth: 12,
            expandBtnHTML: '',
            collapseBtnHTML: '',
        })
    }

    const renderAll = () => {
        renderTree()
        renderPanel()
        initTree()
        syncCurrentNodeMeta()
        updateSaveButtons()
        updatePreview()
    }

    const syncSelectedTreeNode = () => {
        $('.docs-pro-editor-tree-item').removeClass('is-selected')

        if (state.selectedId) {
            $(`.docs-pro-editor-tree-item[data-id="${state.selectedId}"]`).addClass('is-selected')
        }
    }

    const syncTreeFromDom = (markDirty = true, rerender = false) => {
        const $tree = $('#docs-pro-editor-tree')

        if (!$tree.length) {
            state.nodes.forEach((node, index) => {
                node.parent_id = null
                node.sort_order = index
            })

            if (markDirty) {
                setDirty(true)
            }

            return
        }

        const walk = (items, parentId = null) => {
            items.forEach((item, index) => {
                const node = state.nodes.find((candidate) => candidate.id === normalizeId(item.id))

                if (!node) {
                    return
                }

                node.parent_id = normalizeId(parentId)
                node.sort_order = index
                walk(item.children || [], node.id)
            })
        }

        walk($tree.nestable('serialize'))
        state.nodes = normalizeNodeHierarchy(state.nodes)

        if (markDirty) {
            setDirty(true)
        }

        if (rerender) {
            renderAll()
        }
    }

    const syncCurrentNodeFromForm = () => {
        const node = getCurrentNode()
        const $form = $('#docs-pro-editor-form')

        if (!node || !$form.length) {
            return
        }

        node.name = String($form.find('[name="name"]').val() || '').trim()

        if (node.node_type === 'doc') {
            node.excerpt = String($form.find('[name="excerpt"]').val() || '')
            node.markdown_content = String($form.find('[name="markdown_content"]').val() || '')
            node.status = String($form.find('[name="status"]').val() || 'draft')
            node.is_default = $form.find('[name="is_default"]').is(':checked')
            node.is_section = $form.find('[name="is_section"]').is(':checked')

            if (node.is_default) {
                state.nodes.forEach((candidate) => {
                    if (candidate.node_type === 'doc') {
                        candidate.is_default = candidate.id === node.id
                    }
                })
            } else {
                ensureLocalDefaultDoc()
            }
        }
    }

    const updatePreviewFallback = () => {
        const node = getCurrentNode()
        const $preview = $('[data-docs-preview]')

        if (!node || node.node_type !== 'doc' || !$preview.length) {
            return
        }

        $preview.html(node.preview_html || node.content || `<p class="text-muted mb-0">${escapeHtml(state.labels.editor_preview_empty || '')}</p>`)
    }

    const updatePreview = debounce(() => {
        const node = getCurrentNode()

        if (!node || node.node_type !== 'doc') {
            return
        }

        if (!node.markdown_content) {
            updatePreviewFallback()

            return
        }

        $httpClient
            .make()
            .post($editor.data('preview-url'), { markdown: node.markdown_content })
            .then(({ data }) => {
                node.preview_html = data.data.html || ''

                if (node.id === state.selectedId) {
                    $('[data-docs-preview]').html(node.preview_html || `<p class="text-muted mb-0">${escapeHtml(state.labels.editor_preview_empty || '')}</p>`)
                }
            })
            .catch((error) => Botble.handleError(error))
    }, 300)

    const nextSiblingSortOrder = (parentId = null) => {
        const siblings = getChildren(parentId)

        if (!siblings.length) {
            return 0
        }

        return Math.max(...siblings.map((node) => node.sort_order)) + 1
    }

    const collectDescendantIds = (nodeId) => {
        const ids = [normalizeId(nodeId)]

        getChildren(nodeId).forEach((child) => {
            ids.push(...collectDescendantIds(child.id))
        })

        return ids
    }

    const createNode = (nodeType) => {
        syncCurrentNodeFromForm()
        syncTreeFromDom(false)

        const parentNode = getCurrentNode()
        const parentId = null

        state.nodes.push({
            id: `tmp-${state.tempCounter++}`,
            parent_id: parentId,
            node_type: nodeType,
            name: nodeType === 'title' ? 'New title' : nodeType === 'separator' ? 'New separator' : 'New doc',
            slug_path: '',
            excerpt: '',
            markdown_content: '',
            content: '',
            preview_html: '',
            status: nodeType === 'doc' ? 'draft' : 'published',
            is_default: false,
            is_section: false,
            sort_order: nextSiblingSortOrder(parentId),
        })

        const createdNode = state.nodes[state.nodes.length - 1]

        if (createdNode.node_type !== 'separator') {
            state.selectedId = createdNode.id
        }

        ensureLocalDefaultDoc()
        setDirty(true)
        renderAll()
    }

    const deleteNode = (nodeId) => {
        const normalizedId = normalizeId(nodeId)

        if (!normalizedId || !window.confirm($editor.data('delete-confirm'))) {
            return
        }

        const removedIds = collectDescendantIds(normalizedId)
        state.nodes = state.nodes.filter((node) => !removedIds.includes(node.id))

        ensureLocalDefaultDoc()

        if (removedIds.includes(state.selectedId)) {
            state.selectedId = findPreferredNodeId()
        }

        setDirty(true)
        renderAll()
    }

    const saveAll = (button) => {
        syncCurrentNodeFromForm()
        syncTreeFromDom(false)

        const $button = button ? $(button) : $('[data-docs-save-all]').first()

        $httpClient
            .make()
            .withButtonLoading($button)
            .post($editor.data('save-all-url'), {
                tree: $('#docs-pro-editor-tree').length ? $('#docs-pro-editor-tree').nestable('serialize') : [],
                nodes: state.nodes.map((node) => ({
                    id: node.id,
                    parent_id: node.parent_id,
                    node_type: node.node_type,
                    name: node.name,
                    excerpt: node.excerpt,
                    markdown_content: node.markdown_content,
                    status: node.status,
                    is_default: node.is_default,
                    is_section: node.is_section,
                    sort_order: node.sort_order,
                })),
                selected_node_id: state.selectedId,
            })
            .then(({ data }) => {
                if (data.message) {
                    Botble.showSuccess(data.message)
                }

                hydrateState(data.data.editor_state || {})
            })
            .catch((error) => Botble.handleError(error))
    }

    $(window).on('beforeunload', function (event) {
        if (!state.dirty) {
            return undefined
        }

        event.preventDefault()

        if (event.originalEvent) {
            event.originalEvent.returnValue = ''
        }

        return ''
    })

    $(document)
        .on('change', '#docs-pro-editor-tree', function () {
            syncTreeFromDom(true, true)
        })
        .on('click', '[data-docs-create-node]', function (event) {
            event.preventDefault()
            createNode($(event.currentTarget).data('docs-create-node'))
        })
        .on('click', '[data-docs-node-id]', function (event) {
            event.preventDefault()

            const nodeId = normalizeId($(event.currentTarget).data('docs-node-id'))

            if (!nodeId || nodeId === state.selectedId) {
                return
            }

            syncCurrentNodeFromForm()
            state.selectedId = nodeId
            syncCurrentNodeMeta()
            renderPanel()
            syncSelectedTreeNode()
            updateSaveButtons()
            updatePreview()
        })
        .on('click', '[data-docs-panel-tab]', function (event) {
            event.preventDefault()
            state.activeTab = $(event.currentTarget).data('docs-panel-tab') === 'preview' ? 'preview' : 'content'
            renderPanel()
        })
        .on('input change', '#docs-pro-editor-form input, #docs-pro-editor-form textarea, #docs-pro-editor-form select', function (event) {
            const fieldName = $(event.currentTarget).attr('name')

            syncCurrentNodeFromForm()
            setDirty(true)

            if (fieldName === 'name') {
                $(`.docs-pro-editor-tree-item[data-id="${state.selectedId}"] .docs-pro-editor-tree-title`).text(getCurrentNode()?.name || '')
            }

            if (fieldName === 'is_default' || fieldName === 'is_section') {
                renderAll()
            }

            if (fieldName === 'markdown_content') {
                updatePreview()
            }
        })
        .on('click', '[data-docs-delete-node]', function (event) {
            event.preventDefault()
            deleteNode($(event.currentTarget).data('node-id'))
        })
        .on('click', '[data-docs-save-all]', function (event) {
            event.preventDefault()
            saveAll(event.currentTarget)
        })
        .on('submit', '#docs-pro-editor-form', function (event) {
            event.preventDefault()
            saveAll($(event.currentTarget).find('[data-docs-save-button]')[0])
        })

    hydrateState(initialState)
})
