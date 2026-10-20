import { chromium } from 'playwright';
const [origin, url, password, sel] = process.argv.slice(2);
const b = await chromium.launch({ args: ['--no-sandbox'] });
const ctx = await b.newContext({ viewport: { width: 1512, height: 1000 } });
const p = await ctx.newPage();
if (password !== '-') { await p.goto(origin + '/?password-protected=login'); if (await p.$('#password_protected_pass')) { await p.fill('#password_protected_pass', password); await Promise.all([p.waitForNavigation().catch(()=>{}), p.click('#wp-submit')]); } }
await p.goto(origin + url, { waitUntil: 'networkidle' });
console.log(await p.evaluate((sel) => Array.from(document.querySelectorAll(sel)).map(e => e.outerHTML.slice(0, 1500)).join('\n----\n'), sel));
await b.close();
