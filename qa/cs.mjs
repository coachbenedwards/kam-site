import { chromium } from 'playwright';
const [origin, url, password, sel, width, props] = process.argv.slice(2);
const b = await chromium.launch({ args: ['--no-sandbox'] });
const ctx = await b.newContext({ viewport: { width: Number(width || 1512), height: 1000 } });
const p = await ctx.newPage();
if (password !== '-') { await p.goto(origin + '/?password-protected=login'); if (await p.$('#password_protected_pass')) { await p.fill('#password_protected_pass', password); await Promise.all([p.waitForNavigation().catch(()=>{}), p.click('#wp-submit')]); } }
await p.goto(origin + url, { waitUntil: 'networkidle' });
console.log(JSON.stringify(await p.evaluate(([sel, props]) => Array.from(document.querySelectorAll(sel)).slice(0, 4).map(el => { const cs = getComputedStyle(el); const r = el.getBoundingClientRect(); const o = { x: Math.round(r.x), w: Math.round(r.width) }; for (const k of props.split(',')) o[k] = cs.getPropertyValue(k); return o; }), [sel, props]), null, 0));
await b.close();
