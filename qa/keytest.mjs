import { chromium } from 'playwright';
const b = await chromium.launch({ args: ['--no-sandbox'] });
const ctx = await b.newContext({ viewport: { width: 1200, height: 800 } });
const p = await ctx.newPage();
const logs = [];
p.on('console', m => logs.push(m.type() + ': ' + m.text().slice(0, 200)));
await p.goto('https://kamservicing.eastwoodfc.com/?password-protected=login');
if (await p.$('#password_protected_pass')) { await p.fill('#password_protected_pass', 'KamPreview2026'); await Promise.all([p.waitForNavigation().catch(()=>{}), p.click('#wp-submit')]); }
await p.goto('https://kamservicing.eastwoodfc.com/bulwell/', { waitUntil: 'domcontentloaded' });
await p.evaluate(() => new Promise((res) => {
  const d = document.createElement('div'); d.id = 'kt'; d.style.cssText = 'width:600px;height:300px'; document.body.prepend(d);
  window.kt_init = () => { try { new google.maps.Map(document.getElementById('kt'), { center: { lat: 52.99, lng: -1.19 }, zoom: 12 }); res('init ok'); } catch (e) { res('init err ' + e.message); } };
  const s = document.createElement('script'); s.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyDGrGuuFnXuejU-iuzOTXUSIDn6KTFYCa4&callback=kt_init'; s.onerror = () => res('script error'); document.body.appendChild(s);
  setTimeout(() => res('timeout'), 8000);
}));
await p.waitForTimeout(3000);
console.log(logs.filter(l => /google|maps|Error|error/i.test(l)).join('\n') || 'no map errors in console');
const txt = await p.evaluate(() => document.getElementById('kt').innerText.slice(0, 200));
console.log('map div text:', JSON.stringify(txt));
await b.close();
