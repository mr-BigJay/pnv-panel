const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

// Exercise the actual page handlers with DOM and transport stubs.
async function run(page) {
    const source = fs.readFileSync(`${__dirname}/../${page}`, 'utf8');
    const render = source.slice(source.indexOf('function renderPay(item){'), source.indexOf('function startPayWatchers(id){'));
    let response;
    const context = {
        currentPay: { id: 'order-1', status: 'waiting' },
        payCreating: null, payTickTimer: 1, payPollTimer: 2,
        instantPay: {}, instantAmount: {}, instantTimer: {}, instantPayHead: {},
        instantStatus: {}, instantApproved: {},
        formatRemain: () => '', clearInterval: () => {},
        stopPayWatchers() { this.stopped = true; },
        fillResult(item) { context.result = item; },
        showStep(step) { context.step = step; },
        window: { scrollTo() {} }, document: { getElementById() { return null; } },
        fetch(url, opts) { assert.equal(opts.cache, 'no-store'); return Promise.resolve({ json: () => Promise.resolve(response) }); },
    };
    // Use closures rather than `this` because browser functions are unbound.
    context.stopPayWatchers = () => { context.stopped = true; };
    vm.createContext(context);
    vm.runInContext(render, context);
    for (const status of page === 'renew.php' ? ['failed', 'payment_received_provision_failed'] : ['failed']) {
        context.renderPay({ id: 'order-1', status });
        assert.equal(context.stopped, undefined, `${page}: failure must keep polling`);
        assert.equal(context.step, undefined);
    }
    response = { ok: true, item: { id: 'order-1', status: 'paid', link: 'https://example.test/sub/1' } };
    context.pollPayStatus('order-1');
    await new Promise(setImmediate);
    assert.equal(context.step, 3, `${page}: manual approval enters step 3`);
    assert.equal(context.result.link, response.item.link);
    response = { ok: true, item: { id: 'order-1', status: 'waiting' } };
    context.pollPayStatus('order-1');
    await new Promise(setImmediate);
    assert.equal(context.currentPay.status, 'paid', 'late response cannot undo success');
    context.currentPay = { id: 'order-2', status: 'waiting' };
    response.item.status = 'paid';
    context.pollPayStatus('order-1');
    await new Promise(setImmediate);
    assert.equal(context.currentPay.id, 'order-2', 'old order cannot replace current order');
    console.log(`PASS ${page}: failure recovery, automatic step 3, stale response guards`);
}
(async () => { await run('buy.php'); await run('renew.php'); })().catch(err => { console.error(err); process.exitCode = 1; });
