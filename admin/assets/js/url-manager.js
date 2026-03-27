/**
 * AdminURL — Clean URL manager for zerdaTime Admin
 * Centralizes all URL generation and parsing for the admin panel.
 *
 * Usage:
 *   AdminURL.page('desirs')              → /zerdatime/admin/desirs
 *   AdminURL.page('user-edit', uuid)     → /zerdatime/admin/user-edit/<uuid>
 *   AdminURL.page('sondages', null, {selected: id}) → /zerdatime/admin/sondages?selected=<id>
 *   AdminURL.currentPage()               → 'desirs'
 *   AdminURL.currentId()                 → '<uuid>' (from path or ?id=)
 *   AdminURL.go('pv')                    → navigates to /zerdatime/admin/pv
 */
(function () {
    'use strict';

    const BASE = '/zerdatime/admin';

    const AdminURL = {

        /**
         * Build a clean admin URL
         * @param {string} page - Page name (e.g. 'desirs', 'user-edit')
         * @param {string|null} [id] - Optional ID (UUID or slug)
         * @param {Object|null} [params] - Optional query parameters
         * @returns {string}
         */
        page: function (page, id, params) {
            var url;
            if (!page || page === 'dashboard') {
                url = BASE + '/';
            } else {
                url = BASE + '/' + encodeURIComponent(page);
                if (id) url += '/' + encodeURIComponent(id);
            }
            if (params && typeof params === 'object') {
                var qs = new URLSearchParams(params).toString();
                if (qs) url += '?' + qs;
            }
            return url;
        },

        /**
         * Get the current page name from the URL path
         * @returns {string}
         */
        currentPage: function () {
            var path = window.location.pathname.replace(/\/+$/, '');
            var base = BASE.replace(/\/+$/, '');
            var relative = path.substring(base.length);
            var parts = relative.split('/').filter(Boolean);
            return parts[0] || 'dashboard';
        },

        /**
         * Get the ID from the URL path (second segment after page name).
         * Falls back to ?id= query parameter for backwards compatibility.
         * @returns {string}
         */
        currentId: function () {
            var path = window.location.pathname.replace(/\/+$/, '');
            var base = BASE.replace(/\/+$/, '');
            var relative = path.substring(base.length);
            var parts = relative.split('/').filter(Boolean);
            return parts[1] || new URLSearchParams(window.location.search).get('id') || '';
        },

        /**
         * Get a query parameter value
         * @param {string} name
         * @returns {string}
         */
        param: function (name) {
            return new URLSearchParams(window.location.search).get(name) || '';
        },

        /**
         * Navigate to an admin page
         * @param {string} page
         * @param {string|null} [id]
         * @param {Object|null} [params]
         */
        go: function (page, id, params) {
            window.location.href = this.page(page, id, params);
        },

        /**
         * Logout URL
         * @returns {string}
         */
        logout: function () {
            return BASE + '/logout';
        }
    };

    // Expose globally
    window.AdminURL = AdminURL;
})();
