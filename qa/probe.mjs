// Print computed box metrics for a few selectors on a page (password-aware).
import { chromium } from 'playwright';
const [origin, url, password, ...sels] = process.argv.slice(2);
const b = await chromium.launch({ args: ['--no-sandbox'] });
const ctx = await b.newContext({ viewport: { width: 1512, height: 1000 }, userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36' });
const p = await ctx.newPage();
if (password && password !== '-') {
	await p.goto(origin + '/?password-protected=login', { waitUntil: 'domcontentloaded' });
	if (await p.$('#password_protected_pass')) { await p.fill('#password_protected_pass', password); await Promise.all([p.waitForNavigation().catch(()=>{}), p.click('#wp-submit')]); }
}
await p.goto(origin + url, { waitUntil: 'networkidle' });
for (const sel of sels) {
	const r = await p.evaluate((sel) => Array.from(document.querySelectorAll(sel)).slice(0, 3).map((el) => {
		const cs = getComputedStyle(el); const bb = el.getBoundingClientRect();
		return { sel, text: (el.textContent || '').trim().slice(0, 30), y: Math.round(bb.y + scrollY), h: Math.round(bb.height), mt: cs.marginTop, mb: cs.marginBottom, pt: cs.paddingTop, pb: cs.paddingBottom, ff: cs.fontFamily.slice(0, 20), fs: cs.fontSize, lh: cs.lineHeight, color: cs.color, display: cs.display, parent: el.parentElement.className.slice(0, 60) };
	}), sel);
	console.log(JSON.stringify(r));
}
await b.close();
