/**
 * zerdaSelect — Custom dropdown component for SpocSpace admin
 *
 * Usage:
 *   <div class="zs-select" data-name="fieldName" data-placeholder="Choisir...">
 *     <!-- options injected by JS -->
 *   </div>
 *
 * JS:
 *   zerdaSelect.init(el, options, { onSelect, value, dots })
 *     el       — the .zs-select element (or CSS selector string)
 *     options  — [{ value, label, dot?, icon? }, ...]
 *     opts.onSelect(value, label)  — callback on selection
 *     opts.value     — initial selected value (default '')
 *     opts.dots      — show colored dots (default false)
 *     opts.search    — show search input (default false if ≤8 options)
 *     opts.caret     — show caret arrow (default true)
 *     opts.align     — 'left' | 'right' (default 'left')
 *     opts.width     — min-width for dropdown list (default 'auto')
 *
 *   zerdaSelect.getValue(el) — get current value
 *   zerdaSelect.setValue(el, value) — set value programmatically
 *   zerdaSelect.destroy(el) — cleanup
 */
(function () {
    'use strict';

    const _instances = new WeakMap();

    function init(el, options, opts = {}) {
        if (typeof el === 'string') el = document.querySelector(el);
        if (!el) return;

        // Cleanup if re-init
        if (_instances.has(el)) destroy(el);

        const placeholder = el.dataset.placeholder || opts.placeholder || '— Choisir —';
        const showDots = opts.dots || false;
        const showCaret = opts.caret !== false;
        const showSearch = opts.search ?? options.length > 8;
        const align = opts.align || 'left';
        const minWidth = opts.width || '180px';

        el.classList.add('zs-select');
        el.innerHTML = '';

        // Toggle button
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'zs-toggle';
        toggle.innerHTML = `<span class="zs-label">${esc(placeholder)}</span>${showCaret ? '<i class="bi bi-chevron-down zs-arrow"></i>' : ''}`;
        el.appendChild(toggle);

        // Dropdown list container
        const list = document.createElement('div');
        list.className = 'zs-list';
        list.style.minWidth = minWidth;
        if (align === 'right') list.classList.add('zs-align-right');
        el.appendChild(list);

        // Search input (optional)
        let searchInput = null;
        if (showSearch) {
            const searchWrap = document.createElement('div');
            searchWrap.className = 'zs-search-wrap';
            searchWrap.innerHTML = '<i class="bi bi-search zs-search-icon"></i>';
            searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.className = 'zs-search';
            searchInput.placeholder = 'Rechercher...';
            searchWrap.appendChild(searchInput);
            list.appendChild(searchWrap);
        }

        // Inner scrollable area
        const inner = document.createElement('div');
        inner.className = 'zs-list-inner';
        list.appendChild(inner);

        // State
        const state = { value: '', label: placeholder, onSelect: opts.onSelect || null };

        function renderOptions(filter = '') {
            const q = filter.toLowerCase().trim();
            inner.innerHTML = '';
            const filtered = q ? options.filter(o => o.label.toLowerCase().includes(q)) : options;

            if (!filtered.length) {
                inner.innerHTML = '<div class="zs-empty">Aucun résultat</div>';
                return;
            }

            filtered.forEach(o => {
                const opt = document.createElement('div');
                opt.className = 'zs-option' + (o.value === state.value ? ' active' : '');
                let html = '';
                if (showDots && o.dot) {
                    html += `<span class="zs-dot" style="background:${o.dot}"></span>`;
                }
                if (o.icon) {
                    html += `<i class="bi ${o.icon} zs-opt-icon"></i>`;
                }
                html += `<span>${esc(o.label)}</span>`;
                opt.innerHTML = html;
                opt.addEventListener('click', (e) => {
                    e.stopPropagation();
                    selectValue(o.value, o.label);
                    close();
                });
                inner.appendChild(opt);
            });
        }

        function selectValue(val, label) {
            state.value = val;
            state.label = label;
            const labelEl = toggle.querySelector('.zs-label');
            // Show dot in toggle if dots mode
            const optData = options.find(op => op.value === val);
            if (showDots && optData?.dot) {
                labelEl.innerHTML = `<span class="zs-dot" style="background:${optData.dot}"></span>${esc(label)}`;
            } else {
                toggle.querySelector('.zs-dot')?.remove();
                labelEl.textContent = label;
            }
            toggle.classList.toggle('zs-has-value', val !== '');
            inner.querySelectorAll('.zs-option').forEach(o => o.classList.remove('active'));
            // Mark active
            const activeOpt = [...inner.querySelectorAll('.zs-option')].find(o => {
                const optData = options.find(op => op.label === label);
                return optData && optData.value === val;
            });
            if (activeOpt) activeOpt.classList.add('active');
            if (state.onSelect) state.onSelect(val, label);
        }

        function positionList() {
            const toggleRect = toggle.getBoundingClientRect();
            const listH = list.offsetHeight;
            const spaceBelow = window.innerHeight - toggleRect.bottom - 10;
            const spaceAbove = toggleRect.top - 10;
            const dropUp = listH > spaceBelow && spaceAbove > spaceBelow;

            el.classList.toggle('zs-drop-up', dropUp);

            const listWidth = Math.max(toggleRect.width, parseInt(minWidth) || 180);
            if (align === 'right') {
                list.style.left = '';
                list.style.right = (window.innerWidth - toggleRect.right) + 'px';
            } else {
                list.style.right = '';
                list.style.left = toggleRect.left + 'px';
            }
            list.style.width = listWidth + 'px';

            if (dropUp) {
                list.style.top = '';
                list.style.bottom = (window.innerHeight - toggleRect.top + 8) + 'px';
            } else {
                list.style.bottom = '';
                list.style.top = (toggleRect.bottom + 8) + 'px';
            }
        }

        let _scrollRaf = null;
        function onScrollReposition() {
            if (!el.classList.contains('open')) return;
            if (_scrollRaf) cancelAnimationFrame(_scrollRaf);
            _scrollRaf = requestAnimationFrame(positionList);
        }

        function open() {
            // Close all other instances
            document.querySelectorAll('.zs-select.open').forEach(s => {
                if (s !== el) s.classList.remove('open');
            });
            el.classList.remove('zs-drop-up');
            el.classList.add('open');
            if (searchInput) { searchInput.value = ''; searchInput.focus(); }
            renderOptions();
            requestAnimationFrame(positionList);
            window.addEventListener('scroll', onScrollReposition, true);
            window.addEventListener('resize', onScrollReposition);
        }

        function close() {
            el.classList.remove('open');
            window.removeEventListener('scroll', onScrollReposition, true);
            window.removeEventListener('resize', onScrollReposition);
        }

        function toggleOpen(e) {
            e.stopPropagation();
            if (el.classList.contains('open')) close();
            else open();
        }

        // Events
        toggle.addEventListener('click', toggleOpen);

        if (searchInput) {
            searchInput.addEventListener('input', () => renderOptions(searchInput.value));
            searchInput.addEventListener('click', (e) => e.stopPropagation());
        }

        const outsideHandler = () => close();
        document.addEventListener('click', outsideHandler);

        // Set initial value
        if (opts.value !== undefined && opts.value !== '') {
            const found = options.find(o => String(o.value) === String(opts.value));
            if (found) selectValue(found.value, found.label);
        }

        renderOptions();

        // Store instance
        _instances.set(el, { state, outsideHandler, onScrollReposition, selectValue, renderOptions, options });
    }

    function getValue(el) {
        if (typeof el === 'string') el = document.querySelector(el);
        const inst = _instances.get(el);
        return inst ? inst.state.value : '';
    }

    function setValue(el, value) {
        if (typeof el === 'string') el = document.querySelector(el);
        const inst = _instances.get(el);
        if (!inst) return;
        if (value === '' || value === null || value === undefined) {
            const placeholder = el.dataset.placeholder || '— Choisir —';
            inst.selectValue('', placeholder);
        } else {
            const found = inst.options.find(o => String(o.value) === String(value));
            if (found) inst.selectValue(found.value, found.label);
        }
    }

    function destroy(el) {
        if (typeof el === 'string') el = document.querySelector(el);
        const inst = _instances.get(el);
        if (!inst) return;
        document.removeEventListener('click', inst.outsideHandler);
        window.removeEventListener('scroll', inst.onScrollReposition, true);
        window.removeEventListener('resize', inst.onScrollReposition);
        _instances.delete(el);
    }

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    window.zerdaSelect = { init, getValue, setValue, destroy };
})();
