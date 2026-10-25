// Public metadata requests must agree for new contained paths and previously shared aliases.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

class RouteCheck {
    async request(path) {
        let captured;
        const element = { textContent: '', hidden: true, replaceChildren() {}, append() {} };
        const scope = { window: {}, location: new URL(path, 'https://files.bsns.ru'), URL, Math, Number, String,
            document: { querySelector: () => element },
            fetch: async url => { captured = url; return { ok: true, json: async () => ({ ok: true, images: [] }) }; } };
        vm.createContext(scope);
        vm.runInContext(fs.readFileSync(new URL('./config.js', import.meta.url), 'utf8'), scope);
        vm.runInContext(fs.readFileSync(new URL('./app.js', import.meta.url), 'utf8'), scope);
        await Promise.resolve(); return captured;
    }
    async run() {
        for (const path of ['/users/user-test/portfolio?offset=50', '/user-test/portfolio?offset=50']) {
            const request = await this.request(path);
            assert.equal(request.searchParams.get('username'), 'user-test');
            assert.equal(request.searchParams.get('action'), 'portfolio'); assert.equal(request.searchParams.get('offset'), '50');
        }
        for (const path of ['/users/user-test', '/user-test']) {
            const request = await this.request(path); assert.equal(request.searchParams.get('username'), 'user-test');
            assert.equal(request.searchParams.get('action'), 'profile');
        }
        const prototype = await this.request('/prototype/' + 'a'.repeat(64));
        assert.equal(prototype.searchParams.get('action'), 'prototype'); assert.equal(prototype.searchParams.get('hash'), 'a'.repeat(64));
        console.log('Portfolio routes PASS: users container and legacy aliases share profile/portfolio semantics; offset and prototype access preserved.');
    }
}
await new RouteCheck().run();
