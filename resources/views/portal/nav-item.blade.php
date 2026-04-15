@if ($nodes !== [])
    <ul class="docs-pro-nav-list">
        @foreach ($nodes as $node)
            @php
                /** @var \Botble\DocsPro\Models\Doc $document */
                $document = $node['document'];
                $isInTrail = in_array((int) $document->getKey(), $activeTrail, true);
                $hasChildren = $node['children'] !== [];
                $shouldStartOpen = $hasChildren && $isInTrail;
            @endphp

            <li
                class="docs-pro-nav-item {{ $node['is_active'] ? 'is-active' : '' }} {{ $shouldStartOpen ? 'is-open' : '' }} {{ $hasChildren ? 'has-children' : '' }} {{ $document->isSeparator() ? 'is-separator' : '' }}"
                data-docs-nav-item
                data-docs-type="{{ $document->node_type }}"
                data-docs-search="{{ $document->isSeparator() ? '' : \Illuminate\Support\Str::lower($document->menuTitle()) }}"
            >
                @if ($document->isSeparator())
                    <div class="docs-pro-nav-separator" aria-hidden="true"></div>
                @else
                    <div class="docs-pro-nav-row {{ $document->isTitle() ? 'is-title' : '' }}">
                        @if ($document->isDoc() && $node['url'])
                            <a href="{{ $node['url'] }}" class="docs-pro-nav-link" data-docs-nav-label>
                                <span class="docs-pro-nav-link-text">{{ $document->menuTitle() }}</span>

                                @if ($document->is_section)
                                    <span class="docs-pro-nav-badge">{{ trans('plugins/docs-pro::docs-pro.table_section') }}</span>
                                @endif
                            </a>
                        @else
                            <span class="docs-pro-nav-link is-static" data-docs-nav-label>
                                <span class="docs-pro-nav-link-text">{{ $document->menuTitle() }}</span>
                            </span>
                        @endif

                        @if ($hasChildren)
                            <button
                                type="button"
                                class="docs-pro-nav-toggle"
                                data-docs-nav-toggle
                                aria-label="{{ $document->menuTitle() }}"
                                aria-expanded="{{ $shouldStartOpen ? 'true' : 'false' }}"
                            >
                                <svg viewBox="0 0 20 20" aria-hidden="true">
                                    <path d="m7 6l5 4l-5 4" />
                                </svg>
                            </button>
                        @endif
                    </div>
                @endif

                @if ($hasChildren)
                    <div class="docs-pro-nav-children {{ $shouldStartOpen ? 'is-open' : '' }}" data-docs-nav-children>
                        @include('plugins/docs-pro::portal.nav-item', [
                            'nodes' => $node['children'],
                            'activeTrail' => $activeTrail,
                        ])
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
@endif
