document.addEventListener('DOMContentLoaded', () => {
    const portal = document.querySelector('[data-docs-portal]');

    if (!portal) {
        return;
    }

    const body = document.body;
    const searchInput = portal.querySelector('[data-docs-search]');
    const navRoot = portal.querySelector('[data-docs-nav-root] > .docs-pro-nav-list');
    const searchEmpty = portal.querySelector('[data-docs-search-empty]');
    const sidebarToggle = portal.querySelector('[data-docs-sidebar-toggle]');
    const tocToggle = portal.querySelector('[data-docs-toc-toggle]');
    const dismiss = portal.querySelector('[data-docs-dismiss]');
    const pageDataNode = document.getElementById('docs-pro-page-data');
    const pageData = pageDataNode ? JSON.parse(pageDataNode.textContent) : {};
    const tocList = portal.querySelector('[data-docs-toc-list]');
    const tocEmpty = portal.querySelector('[data-docs-toc-empty]');
    const content = portal.querySelector('[data-docs-content]');

    const closePanels = () => {
        body.classList.remove('docs-pro-sidebar-open', 'docs-pro-toc-open');
    };

    sidebarToggle?.addEventListener('click', () => {
        body.classList.toggle('docs-pro-sidebar-open');
        body.classList.remove('docs-pro-toc-open');
    });

    tocToggle?.addEventListener('click', () => {
        body.classList.toggle('docs-pro-toc-open');
        body.classList.remove('docs-pro-sidebar-open');
    });

    dismiss?.addEventListener('click', closePanels);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePanels();
        }

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k' && searchInput) {
            event.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
    });

    const navItemsWithChildren = navRoot
        ? Array.from(navRoot.querySelectorAll('[data-docs-nav-item].has-children'))
        : [];

    const getNavChildrenWrap = (item) => item?.querySelector(':scope > [data-docs-nav-children]');
    const getNavToggle = (item) => item?.querySelector(':scope > .docs-pro-nav-row [data-docs-nav-toggle]');

    const setNavItemOpen = (item, isOpen, persist = true) => {
        const children = getNavChildrenWrap(item);

        if (!item || !children) {
            return;
        }

        children.classList.toggle('is-open', isOpen);
        item.classList.toggle('is-open', isOpen);

        if (persist) {
            item.dataset.initialOpen = isOpen ? '1' : '0';
        }

        getNavToggle(item)?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    const closeNavBranch = (item, persist = true) => {
        if (!item) {
            return;
        }

        setNavItemOpen(item, false, persist);

        item.querySelectorAll('[data-docs-nav-item].has-children').forEach((nestedItem) => {
            setNavItemOpen(nestedItem, false, persist);
        });
    };

    const getNavPath = (item) => {
        const path = new Set();
        let current = item;

        while (current && current instanceof HTMLElement && current.matches('[data-docs-nav-item]')) {
            path.add(current);
            current = current.parentElement?.closest('[data-docs-nav-item]') || null;
        }

        return path;
    };

    navItemsWithChildren.forEach((item) => {
        item.dataset.initialOpen = getNavChildrenWrap(item)?.classList.contains('is-open') ? '1' : '0';
    });

    portal.querySelectorAll('[data-docs-nav-toggle]').forEach((button) => {
        const item = button.closest('[data-docs-nav-item]');
        const children = getNavChildrenWrap(item);

        if (!item || !children) {
            return;
        }

        button.addEventListener('click', () => {
            const isOpen = !children.classList.contains('is-open');

            if (!isOpen) {
                closeNavBranch(item);

                return;
            }

            const keepOpen = getNavPath(item);

            navItemsWithChildren.forEach((navItem) => {
                if (!keepOpen.has(navItem)) {
                    closeNavBranch(navItem);
                }
            });

            Array.from(keepOpen).forEach((navItem) => {
                setNavItemOpen(navItem, true);
            });
        });
    });

    portal.querySelectorAll('.docs-pro-sidebar a, .docs-pro-toc a').forEach((link) => {
        link.addEventListener('click', closePanels);
    });

    const filterList = (list, query) => {
        let hasVisibleItems = false;

        Array.from(list.children).forEach((child) => {
            if (!(child instanceof HTMLElement) || !child.matches('[data-docs-nav-item]')) {
                return;
            }

            const type = child.dataset.docsType || '';
            const text = (child.dataset.docsSearch || '').toLowerCase();
            const nestedList = child.querySelector(':scope > [data-docs-nav-children] > .docs-pro-nav-list');
            const hasVisibleChildren = nestedList ? filterList(nestedList, query) : false;
            const selfMatch = query === '' ? type === 'separator' || text !== '' : text.includes(query);
            const visible = query === ''
                ? type === 'separator' || text !== '' || hasVisibleChildren
                : type !== 'separator' && (selfMatch || hasVisibleChildren);

            child.hidden = !visible;

            const childrenWrap = child.querySelector(':scope > [data-docs-nav-children]');
            const toggle = child.querySelector(':scope > .docs-pro-nav-row [data-docs-nav-toggle]');

            if (childrenWrap) {
                const isOpen = query !== '' ? hasVisibleChildren : child.dataset.initialOpen === '1';
                childrenWrap.classList.toggle('is-open', isOpen);
                child.classList.toggle('is-open', isOpen);

                if (toggle) {
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                }
            }

            hasVisibleItems ||= visible;
        });

        return hasVisibleItems;
    };

    const applySearch = () => {
        if (!navRoot || !searchInput) {
            return;
        }

        const query = searchInput.value.trim().toLowerCase();
        const hasResults = filterList(navRoot, query);

        if (searchEmpty) {
            searchEmpty.hidden = hasResults;
        }
    };

    searchInput?.addEventListener('input', applySearch);
    applySearch();

    portal.querySelectorAll('[data-docs-copy]').forEach((button) => {
        const labelNode = button.querySelector('[data-docs-copy-label]');
        const defaultLabel = labelNode?.textContent || '';

        button.addEventListener('click', async () => {
            const mode = button.getAttribute('data-docs-copy');
            const value = mode === 'markdown' ? pageData.markdown : pageData.url;

            if (!value) {
                return;
            }

            try {
                await navigator.clipboard.writeText(value);

                if (labelNode) {
                    labelNode.textContent = button.getAttribute('data-copied-label') || defaultLabel;

                    window.setTimeout(() => {
                        labelNode.textContent = defaultLabel;
                    }, 1600);
                }
            } catch (error) {
                console.error('Unable to copy docs content.', error);
            }
        });
    });

    if (content && tocList && tocEmpty) {
        const headings = Array.from(content.querySelectorAll('h2, h3, h4')).filter((heading) => heading.textContent.trim() !== '');
        const slugCounts = new Map();

        const slugify = (value) =>
            value
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '') || 'section';

        const items = headings.map((heading) => {
            const baseSlug = heading.id || slugify(heading.textContent.trim());
            const count = (slugCounts.get(baseSlug) || 0) + 1;
            slugCounts.set(baseSlug, count);

            if (!heading.id) {
                heading.id = count === 1 ? baseSlug : `${baseSlug}-${count}`;
            }

            return {
                id: heading.id,
                level: Number(heading.tagName.slice(1)),
                text: heading.textContent.trim(),
            };
        });

        if (items.length === 0) {
            tocList.hidden = true;
        } else {
            tocEmpty.hidden = true;

            items.forEach((item) => {
                const listItem = document.createElement('li');
                const link = document.createElement('a');

                link.href = `#${item.id}`;
                link.className = 'docs-pro-toc-link';
                link.dataset.level = String(item.level);
                link.dataset.targetId = item.id;
                link.textContent = item.text;

                listItem.append(link);
                tocList.append(listItem);
            });

            const tocLinks = Array.from(tocList.querySelectorAll('.docs-pro-toc-link'));
            let ticking = false;

            const getSections = () => {
                const contentBottom = content.getBoundingClientRect().bottom + window.scrollY;

                return headings.map((heading, index) => {
                    const top = heading.getBoundingClientRect().top + window.scrollY;
                    const nextHeading = headings[index + 1];
                    const nextTop = nextHeading
                        ? nextHeading.getBoundingClientRect().top + window.scrollY
                        : contentBottom;

                    return {
                        id: heading.id,
                        start: top,
                        end: Math.max(top + 1, nextTop),
                    };
                });
            };

            const updateActiveHeading = () => {
                const viewportTop = window.scrollY + 112;
                const viewportBottom = window.scrollY + window.innerHeight - 108;
                const viewportHeight = Math.max(1, viewportBottom - viewportTop);
                const sections = getSections();
                const visibleSections = [];
                let fallbackId = items[0]?.id || null;
                let activeId = fallbackId;
                let strongestScore = -1;

                sections.forEach((section) => {
                    if (viewportTop >= section.start) {
                        fallbackId = section.id;
                    }

                    const overlap = Math.max(0, Math.min(section.end, viewportBottom) - Math.max(section.start, viewportTop));

                    if (overlap <= 0) {
                        return;
                    }

                    const sectionLength = Math.max(72, section.end - section.start);
                    const score = Math.min(1, overlap / Math.min(sectionLength, viewportHeight));
                    const intensity = Math.max(0.26, score);

                    visibleSections.push({
                        id: section.id,
                        intensity,
                        score,
                    });

                    if (score >= strongestScore) {
                        strongestScore = score;
                        activeId = section.id;
                    }
                });

                if (visibleSections.length === 0) {
                    activeId = fallbackId;
                }

                const visibleMap = new Map(visibleSections.map((section) => [section.id, section]));

                tocLinks.forEach((link) => {
                    const visibleSection = visibleMap.get(link.dataset.targetId);
                    const isVisible = Boolean(visibleSection);
                    const isActive = link.dataset.targetId === activeId;

                    link.classList.toggle('is-visible', isVisible);
                    link.classList.toggle('is-active', isActive);
                    link.classList.remove('is-visible-start', 'is-visible-end');

                    if (isVisible && visibleSection) {
                        link.style.setProperty('--docs-pro-toc-strength', visibleSection.intensity.toFixed(3));
                    } else {
                        link.style.removeProperty('--docs-pro-toc-strength');
                    }
                });

                const visibleLinks = tocLinks.filter((link) => link.classList.contains('is-visible'));

                if (visibleLinks.length > 0) {
                    visibleLinks[0].classList.add('is-visible-start');
                    visibleLinks[visibleLinks.length - 1].classList.add('is-visible-end');
                }

                ticking = false;
            };

            const requestHeadingUpdate = () => {
                if (!ticking) {
                    window.requestAnimationFrame(updateActiveHeading);
                    ticking = true;
                }
            };

            window.addEventListener('scroll', requestHeadingUpdate, { passive: true });
            window.addEventListener('resize', requestHeadingUpdate);
            requestHeadingUpdate();
        }
    }
});
