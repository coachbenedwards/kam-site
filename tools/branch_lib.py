#!/usr/bin/env python3
"""
Shared helpers for the page converters (see branch-to-blocks.py).

Turn a captured branch page (capture/html/kam-servicing-*.php.html) into the
page-blocks layout the kam-site plugin writes into Elementor.

Every piece of copy becomes a heading, text or image widget; the pieces with
logic (tiles, button pairs, branch details, map, form) become KAM blocks.
Nothing is one HTML box. Usage:

    python3 tools/branch-to-blocks.py bulwell            # writes build/bulwell.json
    python3 tools/branch-to-blocks.py --all
"""
import json, re, sys, os
from bs4 import BeautifulSoup, NavigableString, Tag

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MEDIA = 'https://kamservicing.eastwoodfc.com/wp-content/uploads/2026/10/'
OLD = 'https://kamservicing.com/'
MAPS_KEY = '@MAPS_KEY@'   # filled in by tools/push-page.sh from ~/Documents/Claude outputs/kam-review/kam-maps-key.txt (never in the repo)

BRANCHES = {
 'nottingham':'kam-servicing-nottingham.php','bulwell':'kam-servicing-bulwell.php',
 'nuthall':'kam-servicing-nuthall.php','colwick':'kam-servicing-colwick.php',
 'hucknall':'kam-servicing-hucknall.php','beeston':'kam-servicing-beeston.php',
 'sutton-in-ashfield':'kam-servicing-sutton.php','stapleford':'kam-servicing-stapleford.php',
 'derby':'kam-servicing-derby.php','derby-ascot-drive':'kam-servicing-ascot.php',
 'derby-sinfin':'kam-servicing-sinfin.php','long-eaton-derby-road':'kam-servicing-longeaton.php',
 'long-eaton-tamworth-road':'kam-servicing-sawley.php','heanor':'kam-servicing-heanor.php',
 'belper':'kam-servicing-belper.php','ambergate':'kam-servicing-ambergate.php',
}
# Old .php pages that now live here (same list as kam_moved_paths in the plugin).
MOVED = {
 'all-kam-services.php':'/services/','careers_at_kam_servicing.php':'/careers/',
 'payment-assist.php':'/payment-assist/','abs-repairs.php':'/abs-repairs/',
 'car-exhaust-prices.php':'/exhausts/','airbag-fault-repair.php':'/airbag-faults/',
 'car-brakes.php':'/brakes/','car-diagnostics.php':'/diagnostics/',
 'clutch-replacement.php':'/clutch-replacement/','gdpr-privacy-policy.php':'/privacy-policy/',
 'terms-conditions.php':'/terms/','kam-servicing.php':'/contact/','index.php':'/',
}
for slug, old in BRANCHES.items():
    MOVED[old] = '/' + slug + '/'

def fix_url(u):
    u = (u or '').strip()
    if not u or u.startswith('#') or u.startswith('tel:') or u.startswith('mailto:'):
        return u
    u = re.sub(r'^https?://(kams\.vwddev\.co\.uk|kamservicing\.com)/', '', u)
    u = u.lstrip('/')
    if u.startswith('images/'):
        return MEDIA + u.split('/')[-1]
    base = u.split('?')[0].split('#')[0]
    if base in MOVED:
        return MOVED[base] + u[len(base):]
    if base.endswith('.php'):               # bookings, tyres, blank: still on the old host
        return OLD + u
    if u.startswith('http'):
        return u
    return '/' + u

def fix_html(el):
    """Inner HTML of an element with every link and image pointed at the new home."""
    for a in el.find_all('a', href=True):
        a['href'] = fix_url(a['href'])
    for img in el.find_all('img', src=True):
        img['src'] = fix_url(img['src'])
    html = el.decode_contents()
    html = re.sub(r'[ \t]*\n[ \t\n]*', ' ', html)   # newlines + indent -> one space; keeps &nbsp;
    html = html.replace('\xa0', '&nbsp;')
    return html.strip()

def squash(s):
    return re.sub(r'\s+', ' ', s).strip()

def heading(el):
    return {'widget':'heading','fields':{'tag':el.name,'title':squash(fix_html(el))}}

def column_widgets(col, lat=None, lng=None, branch=None):
    """One Bootstrap column -> a list of widget specs, in order."""
    out, buf = [], []
    def flush():
        if buf:
            out.append({'widget':'text-editor','fields':{'editor':''.join(buf)}})
            buf.clear()
    import html as _html
    for k in col.children:
        if isinstance(k, NavigableString):
            # Loose text straight in the column (Sinfin has a line like this).
            t = squash(str(k))
            if t and not isinstance(k, type(col.find(string=lambda x: False))):  # plain text, not a comment
                if k.__class__.__name__ == 'Comment':
                    continue
                buf.append(_html.escape(t, quote=False))
                print('  note: loose text kept in', col.get('class'), '->', t[:60], file=sys.stderr)
            continue
        if k.name in ('h1','h2','h3','h4','h5','h6'):
            flush(); out.append(heading(k))
        elif k.name == 'hr':
            buf.append('<hr>')
        elif k.name in ('p','ul','ol','div') and not k.get('id') == 'map_canvas' and not k.find('form'):
            buf.append('<%s%s>%s</%s>' % (k.name, ' class="%s"' % ' '.join(k.get('class',[])) if k.get('class') else '', fix_html(k), k.name))
        elif k.name == 'img':
            flush(); out.append({'widget':'image','fields':{'url':fix_url(k.get('src')),'alt':k.get('alt',''),'align':'center'}})
        elif k.name == 'a' and ('secondary-btn-link' in k.get('class',[]) or 'primary-btn-link' in k.get('class',[])):
            flush()
            if out and out[-1].get('block') == 'kam_buttons' and 'call' not in out[-1]['fields']:
                out[-1]['fields']['call'] = squash(k.get_text()); out[-1]['fields']['call_url'] = fix_url(k['href'])
            else:
                out.append({'block':'kam_buttons','fields':{'book':squash(k.get_text()),'book_url':fix_url(k['href'])}})
        elif k.name == 'form':
            flush(); out.append(form_block(k))
        elif k.get('id') == 'map_canvas':
            flush(); out.append({'block':'kam_map','fields':{'lat':lat,'lng':lng,'zoom':'16','title':'KAM ' + (branch or ''), 'key': MAPS_KEY}})
        else:
            raise SystemExit('unhandled element in column: <%s class=%s>' % (k.name, k.get('class')))
    flush()
    return out

def branch_block(col):
    h5 = col.find('h5')
    ps = col.find_all('p')
    f = {'title': squash(h5.get_text()) if h5 else ''}
    for p in ps:
        txt = [squash(t) for t in p.stripped_strings]
        if p.find('i', class_='fa-map-marker-alt'):
            f['address'] = '|'.join(txt)
        elif p.find('i', class_='fa-clock'):
            f['hours'] = '|'.join(txt)
        else:
            for t in txt:
                if t.startswith('Manager:'): f['manager'] = t.replace('Manager:','').strip()
                elif '@' in t: f['email'] = t
                elif re.search(r'\d{4,}', t): f['phone'] = t
    return {'block':'kam_branch','fields':f}

def form_block(form):
    depot = form.find('input', {'name':'depot'})
    name = form.find('input', {'name':'full_depot_name'})
    return {'block':'kam_contact_form','fields':{
        'depot': depot['value'] if depot else '', 'depot_name': name['value'] if name else '', 'to': '', 'intro': ''}}

def col_width(cls):
    """Bootstrap column classes -> percent at desktop (col-md-N wins over col-N)."""
    for pref in ('col-lg-', 'col-md-', 'col-sm-', 'col-'):
        for c in cls:
            m = re.fullmatch(pref + r'(\d+)', c)
            if m:
                return round(int(m.group(1)) / 12 * 100)
    return 100

def tiles_block(jumbo):
    lines, n = [], 0
    for tile in jumbo.select('.branch-col-lg-3'):
        n += 1
        img = tile.find('img'); a = tile.find('a'); h = tile.find(['h1','h2']); small = tile.find('small')
        lines.append('|'.join([fix_url(img['src']) if img else '', squash(h.get_text()) if h else '',
                               squash(small.get_text()) if small else '', fix_url(a['href']) if a else '']))
    return {'block':'kam_tiles','fields':{'h1':'1'},'lines':'\n'.join(lines)}

