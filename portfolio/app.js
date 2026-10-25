// Owner: virtual public routes request WP metadata and load WebP bytes from file-service URLs.
(() => {
    'use strict';
    class PublicPortfolio {
        // Method: only a bounded page offset is retained from the public URL.
        constructor(settings) { this.settings = settings; this.offset = Math.max(settings.zero, Number(new URL(location.href).searchParams.get('offset')) || settings.zero); }
        // Method: route aliases and hash access remain separate from numeric database relationships.
        request() {
            const parts = location.pathname.split('/').filter(Boolean);
            // Branch: canonical user-container routes and previously shared root aliases resolve the same public profile.
            if (parts[this.settings.zero] === this.settings.usersLeaf) parts.shift();
            const url = new URL(this.settings.metadataRoute, this.settings.metadataDomain);
            if (parts[this.settings.zero] === 'prototype') { url.searchParams.set('action', 'prototype'); url.searchParams.set('hash', parts[this.settings.one] || ''); }
            else { url.searchParams.set('username', parts[this.settings.zero] || ''); url.searchParams.set('action', parts[this.settings.one] === 'portfolio' ? 'portfolio' : 'profile'); url.searchParams.set('offset', String(this.offset)); }
            return url;
        }
        // Method: remote user text is rendered with textContent; response URLs must use HTTPS.
        link(url, text) { const link = document.createElement('a'); link.href = this.safeUrl(url); link.textContent = text; link.rel = 'noopener noreferrer'; return link; }
        // Method: HTTP metadata may not inject executable or insecure outbound URLs.
        safeUrl(value) { const url = new URL(value); if (url.protocol !== 'https:') throw new Error(this.settings.unavailable); return url.href; }
        // Method: explicit opening counts a view through native WP navigation, including a known logged-in viewer.
        image(item) {
            const figure = document.createElement('figure'); const link = this.link(item.open_url, ''); const image = document.createElement('img');
            image.src = this.safeUrl(item.url); image.alt = item.title; image.loading = 'lazy'; image.referrerPolicy = 'no-referrer'; link.append(image);
            const caption = document.createElement('figcaption'); caption.textContent = item.title;
            const views = document.createElement('p'); views.textContent = this.settings.views + item.views; caption.append(views); figure.append(link, caption); return figure;
        }
        // Method: only numeric offsets paginate; neither client user IDs nor storage paths reach the API.
        page(offset, title) { const url = new URL(location.href); url.searchParams.set('offset', String(offset)); return this.link(url.href, title); }
        async mount() {
            const status = document.querySelector('[data-status]');
            try {
                const request = this.request(); const response = await fetch(request, {credentials: 'omit', referrerPolicy: 'no-referrer'});
                const data = await response.json(); if (!response.ok || !data.ok) throw new Error(this.settings.unavailable);
                const prototype = request.searchParams.get('action') === 'prototype'; const profile = data.profile || {};
                document.querySelector('[data-title]').textContent = prototype ? this.settings.prototype : profile.name || this.settings.portfolio;
                document.querySelector('[data-bio]').textContent = profile.bio || '';
                const profileLink = document.querySelector('[data-profile]'); const target = profile.profile_url || profile.portfolio_url;
                if (target) { profileLink.href = this.safeUrl(target); profileLink.textContent = profile.profile_url ? this.settings.profileLink : this.settings.portfolioLink; profileLink.hidden = false; }
                const images = data.images || []; document.querySelector('[data-images]').replaceChildren(...images.map(item => this.image(item)));
                status.textContent = images.length === this.settings.zero && !profile.bio && !profile.portfolio_url ? this.settings.empty : '';
                const pages = document.querySelector('[data-pages]');
                if (!prototype && request.searchParams.get('action') === 'portfolio') {
                    if (this.offset > this.settings.zero) pages.append(this.page(Math.max(this.settings.zero, this.offset - this.settings.pageSize), this.settings.previous));
                    if (images.length === this.settings.pageSize) pages.append(this.page(this.offset + this.settings.pageSize, this.settings.next));
                }
            } catch (error) { status.textContent = this.settings.unavailable; }
        }
    }
    new PublicPortfolio(window.BZNPortfolioPublicConfig).mount();
})();
