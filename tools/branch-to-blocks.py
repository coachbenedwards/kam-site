#!/usr/bin/env python3
"""
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
    html = re.sub(r'\s*\n\s*', ' ', html)
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
            out.append({'widget':'text-editor','fields':{'editor':''.join(buf),'class':'font-16'}})
            buf.clear()
    kids = [k for k in col.children if isinstance(k, Tag)]
    for k in kids:
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
            flush(); out.append({'block':'kam_map','fields':{'lat':lat,'lng':lng,'zoom':'16','title':'KAM ' + (branch or '')}})
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

def convert(slug):
    path = os.path.join(ROOT, 'capture/html', BRANCHES[slug] + '.html')
    html = open(path, encoding='utf-8', errors='ignore').read()
    m = re.search(r'LatLng\(([-\d.]+),\s*([-\d.]+)\)', html) or re.search(r'lat:\s*([-\d.]+),\s*lng:\s*([-\d.]+)', html)
    if not m:
        raise SystemExit(slug + ': no map coordinates found in the page script')
    lat, lng = m.group(1), m.group(2)
    main = BeautifulSoup(re.search(r'<main>(.*?)</main>', html, re.S).group(1), 'lxml')
    title = squash(main.find('h1').get_text())
    rows = []
    for top in [t for t in main.body.children if isinstance(t, Tag)] if main.body else []:
        if top.name == 'style':
            continue
        cls = top.get('class', [])
        if 'jumbotron' in cls:
            rows.append({'class':'kam-blockrow', 'widgets':[tiles_block(top)]})
            continue
        # Every other top-level div: a band with one or more .row inside .container
        band = ' '.join(c for c in cls if c in ('booking-summary','booking-summary-font','container-grey'))
        rid = top.get('id')
        for cont in top.select(':scope > .container'):
          # .container.p-20 pads 20 all round; with Bootstrap's -12 row margin the
          # columns start 8px in. A plain .container pads 12, the row takes it back.
          cpad = 20 if 'p-20' in cont.get('class', []) else 0
          for r in cont.select(':scope > .row'):
            cols = [c for c in r.children if isinstance(c, Tag) and any(x.startswith('col-') for x in c.get('class', []))]
            rcls = ' '.join(c for c in r.get('class', []) if c in ('mb-5',))
            def colspec(c):
                cls = c.get('class', [])
                pad = 20 if 'p-20' in cls else 12       # .p-20 on a column replaces Bootstrap's 12
                return {'class': ('kam-blockrow ' + ' '.join(x for x in cls if x in ('text-center',))).strip(),
                        'width': col_width(cls),
                        'tablet': 100 if (any(c.startswith('col-lg-') for c in cls) and not any(c.startswith('col-md-') for c in cls)) else col_width(cls),
                        'padding': {'top':pad if 'p-20' in cls else 0,'right':pad,'bottom':pad if 'p-20' in cls else 0,'left':pad},
                        'widgets': column_widgets(c, lat, lng, slug)}
            row = {'wrap': band or 'kam-blockrow', 'class': ('container ' + rcls).strip(),
                   'padding': {'top':cpad,'right':max(cpad-12,0),'bottom':cpad,'left':max(cpad-12,0)},
                   'columns': [colspec(c) for c in cols]}
            if rid:
                row['id'] = rid; rid = None
            rows.append(row)
    return {'page': slug, 'title': title, 'replace': True, 'blocks': rows}

if __name__ == '__main__':
    slugs = list(BRANCHES) if '--all' in sys.argv else [a for a in sys.argv[1:] if not a.startswith('-')]
    for slug in slugs:
        doc = convert(slug)
        out = os.path.join(ROOT, 'build', slug + '.json')
        json.dump(doc, open(out, 'w'), indent=1, ensure_ascii=False)
        nw = sum(len(c['widgets']) for r in doc['blocks'] for c in r.get('columns', [])) + sum(len(r.get('widgets', [])) for r in doc['blocks'])
        print(slug, 'rows', len(doc['blocks']), 'widgets', nw, '->', os.path.relpath(out, ROOT))
