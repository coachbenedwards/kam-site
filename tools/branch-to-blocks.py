#!/usr/bin/env python3
"""Branch pages -> page-blocks layouts. python3 tools/branch-to-blocks.py bulwell | --all"""
import json, os, re, sys
from bs4 import BeautifulSoup, Tag
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from branch_lib import *

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
