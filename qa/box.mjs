import { chromium } from 'playwright';
const [origin, url, password, sel, width] = process.argv.slice(2);
const b = await chromium.launch({ args: ['--no-sandbox'] });
const ctx = await b.newContext({ viewport: { width: Number(width || 1512), height: 1000 } });
const p = await ctx.newPage();
if (password !== '-') { await p.goto(origin + '/?password-protected=login'); if (await p.$('#password_protected_pass')) { await p.fill('#password_protected_pass', password); await Promise.all([p.waitForNavigation().catch(()=>{}), p.click('#wp-submit')]); } }
await p.goto(origin + url, { waitUntil: 'networkidle' });
console.log(JSON.stringify(await p.evaluate((sel) => { let el = document.querySelector(sel); const out = []; while (el && el !== document.body) { const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); out.push([el.tagName + '.' + String(el.className).slice(0, 50), Math.round(r.x), Math.round(r.width), cs.paddingLeft, cs.paddingRight, cs.maxWidth, cs.display]); el = el.parentElement; } return out; }, sel), null, 0));
await b.close();
