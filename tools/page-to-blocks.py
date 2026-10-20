#!/usr/bin/env python3
"""
Any captured page -> a page-blocks layout, by the shapes KAM's pages share.

  band      a top-level .container / .container-fluid -> a wrap row
  .row      -> a columns row; each Bootstrap col -> a column, widths mapped
              from Bootstrap's breakpoints onto Elementor's
  carousel  -> kam_hero block
  a > img + h5 + p   -> Image Box widget (the whole tile is one link)
  .card     -> the column takes the card's classes and the card-body padding
  h1-h6 / p, ul, hr / img / bare <br> -> heading / text / image / spacer widgets

    python3 tools/page-to-blocks.py services careers payment-assist ...
    python3 tools/page-to-blocks.py --all
"""
import json, os, re, sys
from bs4 import BeautifulSoup, Tag, NavigableString, Comment
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from branch_lib import fix_url, fix_html, squash, ROOT

PAGES = {   # slug -> captured file
    'services': 'all-kam-services.php', 'careers': 'careers_at_kam_servicing.php',
    'payment-assist': 'payment-assist.php', 'abs-repairs': 'abs-repairs.php',
    'exhausts': 'car-exhaust-prices.php', 'airbag-faults': 'airbag-fault-repair.php',
    'brakes': 'car-brakes.php', 'diagnostics': 'car-diagnostics.php',
    'clutch-replacement': 'clutch-replacement.php', 'privacy-policy': 'gdpr-privacy-policy.php',
    'terms': 'terms-conditions.php', 'contact': 'kam-servicing.php',
}
BAND = {'top': 60, 'right': 10, 'bottom': 80, 'left': 10}    # .container-fluid in kam.css
LINE = 27                                                    # body line-height: 15px * 1.8

def col_widths(cls):
    """(desktop, tablet, mobile) percent for Bootstrap column classes.
    Elementor: mobile <768, tablet 768-1024, desktop >1024. Bootstrap: sm 576, md 768, lg 992."""
    def pick(prefixes):
        for pref in prefixes:
            for c in cls:
                m = re.fullmatch(pref + r'(\d+)', c)
                if m:
                    return round(int(m.group(1)) / 12 * 100, 3)
        return 100
    return pick(['col-lg-', 'col-md-', 'col-sm-', 'col-']), pick(['col-md-', 'col-sm-', 'col-']), pick(['col-'])

def classes(el, keep):
    return ' '.join(c for c in el.get('class', []) if c in keep or any(c.startswith(k) for k in keep if k.endswith('-')))

def hero(car):
    lines = []
    for item in car.select('.carousel-item'):
        img = item.find('img'); hs = item.select('.carousel-caption > *')
        lines.append('|'.join([fix_url(img['src']), squash(hs[0].get_text()) if hs else '', squash(hs[1].get_text()) if len(hs) > 1 else '']))
    return {'class': 'kam-blockrow', 'widgets': [{'block': 'kam_hero', 'fields': {'id': car.get('id', 'demo')}, 'lines': '\n'.join(lines)}]}

def is_link_card(a):
    """A link that is exactly image + heading + words: Elementor's Image Box."""
    return (a.name == 'a' and a.find('img') and a.find(['h4', 'h5', 'h6']) and a.find('p')
            and not a.find('button') and not a.find('hr'))

def link_card(a, extra_class=''):
    img = a.find('img'); h = a.find(['h4', 'h5', 'h6']); p = a.find('p')
    box = h.parent if h.parent is not a else None
    space = 0
    if box is not None:
        for c in box.get('class', []):
            m = re.fullmatch(r'mt-(\d)', c)
            if m: space = BS_SPACE[m.group(1)]
    return {'widget': 'image-box', 'fields': {
        'url': fix_url(img['src']), 'alt': img.get('alt', ''), 'title': squash(h.get_text()), 'tag': h.name,
        'text': squash(fix_html(p)), 'link': fix_url(a.get('href', '')), 'align': 'center', 'image_space': space,
        'class': (extra_class + ' ' + classes(a, ['service-item'])).strip()}}

def linked_parts(a):
    """A link holding more than a card can (a button, a rule): its parts as
    widgets, the image and the button keeping the link."""
    href = fix_url(a.get('href', ''))
    holder = BeautifulSoup('<div></div>', 'lxml').div
    src = a.find('div') if (a.find('div') and 'text-center' in a.find('div').get('class', [])) else a
    centred = src is not a
    for k in list(src.children):
        if isinstance(k, Tag) and k.name == 'button':
            wrap = BeautifulSoup('<a></a>', 'lxml').a; wrap['href'] = href; wrap['class'] = 'text-decoration-none'
            wrap.append(k.__copy__()); holder.append(wrap)
        else:
            holder.append(k.__copy__())
    if centred: holder['class'] = 'text-center'
    out = widgets(holder)
    for w in out:
        if centred and w.get('widget') == 'text-editor':
            w['fields']['class'] = 'text-center'
        if w.get('widget') == 'image':
            w['fields']['link'] = href
            if centred: w['fields']['align'] = 'center'
        if centred and w.get('widget') == 'heading':
            w['fields']['align'] = 'center'
    return out, centred

def widgets(el):
    """The children of a column (or a card body) as widgets, in order."""
    out, buf, brs = [], [], 0
    after_inline = False
    def flush():
        nonlocal brs
        if brs:
            out.append({'widget': 'spacer', 'fields': {'height': LINE * brs}}); brs = 0
        if buf:
            out.append({'widget': 'text-editor', 'fields': {'editor': ''.join(buf)}}); buf.clear()
    def flush_text():
        if buf:
            out.append({'widget': 'text-editor', 'fields': {'editor': ''.join(buf)}}); buf.clear()
    for k in el.children:
        if isinstance(k, Comment):
            continue
        if isinstance(k, NavigableString):
            t = squash(str(k))
            if t:
                flush(); buf.append(t)
            continue
        if k.name == 'br':
            # A <br> right after an inline image only ends the image's line.
            flush_text()
            if after_inline: after_inline = False
            else: brs += 1
            continue
        after_inline = False
        if k.name in ('h1', 'h2', 'h3', 'h4', 'h5', 'h6'):
            flush()
            f = {'tag': k.name, 'title': squash(fix_html(k)), 'class': classes(k, ['fw-bold', 'card-title', 'mb-', 'mt-'])}
            if 'text-center' in k.get('class', []): f['align'] = 'center'
            m = re.search(r'color\s*:\s*([^;]+)', k.get('style', '') or '')
            if m: f['color'] = m.group(1).strip()
            out.append({'widget': 'heading', 'fields': f}); continue
        if k.name == 'hr':
            brs and flush(); buf.append('<hr>'); continue
        if k.name in ('p', 'ul', 'ol', 'table', 'blockquote'):
            brs and flush()
            cls = ' '.join(k.get('class', []))
            buf.append('<%s%s>%s</%s>' % (k.name, ' class="%s"' % cls if cls else '', fix_html(k), k.name)); continue
        if k.name == 'img':
            flush()
            cls = k.get('class', [])
            f = {'url': fix_url(k['src']), 'alt': k.get('alt', '') or k.get('title', ''),
                 'align': 'center' if 'mx-auto' in cls else 'left', 'class': classes(k, ['img-fluid', 'rounded', 'w-100'])}
            m = re.fullmatch(r'(\d+)%', (k.get('width') or '').strip())
            if m: f['width'] = int(m.group(1))
            out.append({'widget': 'image', 'fields': f})
            after_inline = 'd-block' not in cls      # a block image has no line for a <br> to end
            continue
        if is_link_card(k):
            flush(); out.append(link_card(k)); continue
        if k.name == 'a' and k.find('button') and not k.find('img'):
            # A button in a link: inline on the live site, so no paragraph margin of its own.
            brs and flush()
            centre = ' text-center' if ('text-center' in (el.get('class') or [])) else ''
            buf.append('<p class="mb-0%s">%s</p>' % (centre, str(k).replace(k['href'], fix_url(k['href'])))); continue
        if k.name == 'a' and k.find('img') and (k.find('button') or k.find('hr')):
            flush(); parts, centred = linked_parts(k); out.extend(parts); continue
        if k.name == 'a' and ('secondary-btn-link' in k.get('class', []) or 'primary-btn-link' in k.get('class', [])):
            flush()
            if out and out[-1].get('block') == 'kam_buttons' and 'call' not in out[-1]['fields']:
                out[-1]['fields']['call'] = squash(k.get_text()); out[-1]['fields']['call_url'] = fix_url(k['href'])
            else:
                out.append({'block': 'kam_buttons', 'fields': {'book': squash(k.get_text()), 'book_url': fix_url(k['href'])}})
            continue
        if k.name == 'div' and 'card-body' in k.get('class', []):
            flush(); out.extend(widgets(k)); continue
        if k.name == 'div' and not k.get('class') and not k.get('id'):
            flush(); out.extend(widgets(k)); continue
        if k.name == 'div' and 'text-center' in k.get('class', []) and len(k.get('class', [])) == 1:
            flush(); out.extend(widgets(k)); continue
        if k.name == 'div' and 'warranty-item' in k.get('class', []):
            # One numbered term: a styled line of prose, one text widget each.
            flush(); out.append({'widget': 'text-editor', 'fields': {'editor': str(k)}}); continue
        raise SystemExit('unhandled <%s class=%s id=%s> in %s' % (k.name, k.get('class'), k.get('id'), el.get('class')))
    flush()
    return out

def column(c):
    cls = c.get('class', [])
    d, t, m = col_widths(cls)
    pad = {'top': 0, 'right': 12, 'bottom': 0, 'left': 12}
    col = {'class': ('kam-blockrow ' + classes(c, ['text-center'])).strip(), 'width': d, 'tablet': t, 'mobile': m, 'padding': pad}
    kids = [k for k in c.children if isinstance(k, Tag)]
    # A column that is just a card: the column becomes the card (its classes,
    # the card-body's 16px padding) and the card's parts are the widgets.
    if len(kids) == 1 and kids[0].name == 'div' and 'card' in kids[0].get('class', []):
        card = kids[0]
        body = card.select_one('.card-body') or card
        pad, pad_m = card_padding(body)
        col['inner'] = {'class': 'kam-blockrow ' + ' '.join(card.get('class', [])) + (' text-center' if 'text-center' in body.get('class', []) else ''),
                        'padding': pad, 'widgets': widgets(body)}
        if pad_m: col['inner']['padding_mobile'] = pad_m
        return col, True
    col['widgets'] = widgets(c)
    return col, False

BS_SPACE = {'0': 0, '1': 4, '2': 8, '3': 16, '4': 24, '5': 48}

def card_padding(body):
    """Bootstrap .card-body pads 16px; p-N / p-md-N override it (md = 768+, which is Elementor's tablet and desktop)."""
    cls = body.get('class', [])
    base = 16; md = None
    for c in cls:
        m = re.fullmatch(r'p-(\d)', c)
        if m: base = BS_SPACE[m.group(1)]
        m = re.fullmatch(r'p-md-(\d)', c)
        if m: md = BS_SPACE[m.group(1)]
    if md is None:
        return {'top': base, 'right': base, 'bottom': base, 'left': base}, None
    return {'top': md, 'right': md, 'bottom': md, 'left': md}, {'top': base, 'right': base, 'bottom': base, 'left': base}

def maps_key(html):
    m = re.search(r'maps/api/js\?key=([A-Za-z0-9_-]+)', html)
    return m.group(1) if m else ''

def all_branches_map(html):
    """The contact page's map: every pin from the page's own script, with the
    words of each pin's info window."""
    markers = re.findall(r'\[\s*"([^"]+)",\s*([-\d.]+),\s*([-\d.]+)\s*\]', html)
    cards = re.findall(r'`<div class="text-center info_content">(.*?)</div>`', html, re.S)
    lines = []
    for i, (name, lat, lng) in enumerate(markers):
        addr = phone = url = ''
        if i < len(cards):
            c = BeautifulSoup(cards[i], 'lxml')
            ps = c.find_all('p')
            if len(ps) > 1:
                addr = ';'.join(t for t in (squash(x) for x in ps[1].get_text('\n').split('\n')) if t)
            tel = c.find('a', href=re.compile(r'^tel:'))
            if tel: phone = squash(tel.get_text())
            d = c.find('a', class_='btn')
            if d: url = d.get('href', '')
        lines.append('|'.join([name, lat, lng, addr, phone, url]))
    return {'class': 'kam-blockrow', 'widgets': [{'block': 'kam_map', 'fields': {'key': maps_key(html), 'height': '420', 'zoom': '9'}, 'lines': '\n'.join(lines)}]}

def band_rows(top, html=''):
    """One top-level element -> the rows it becomes."""
    rows = []
    cls = top.get('class', [])
    if top.get('id') == 'map_wrapper':
        return [all_branches_map(html)]
    if 'carousel' in cls:
        return [hero(top)]
    fluid = 'container-fluid' in cls
    wrap = ' '.join(c for c in cls if c in ('container-fluid', 'container-grey', 'my-5', 'mb-5', 'mt-5', 'bg-light', 'warranty-header'))
    wrap_pad = dict(BAND) if fluid else {'top': 0, 'right': 0, 'bottom': 0, 'left': 0}
    if 'py-5' in cls: wrap_pad.update({'top': 48, 'bottom': 48})          # Bootstrap .py-5
    if 'warranty-header' in cls: wrap_pad.update({'top': 60, 'bottom': 60})  # its own rule: 60px 0, centred
    inner_cls = 'container'          # the centred width, Bootstrap's .container
    if top.name == 'section':
        # <section class><div class=container> ... : the container is the band's only child
        inner = top.find('div', class_='container', recursive=False)
        if inner is not None:
            top = inner
    pending = []                     # non-row children, gathered into one full column
    def flush_pending():
        if not pending:
            return
        holder = BeautifulSoup('<div></div>', 'lxml').div
        for k in pending: holder.append(k.__copy__())
        w = widgets(holder)
        pending.clear()
        if w:
            card = len(w) and any(isinstance(k, Tag) and k.name == 'div' and 'card' in k.get('class', []) for k in [])
            rows.append({'wrap': wrap or 'kam-blockrow', 'wrap_padding': dict(wrap_pad), 'class': inner_cls,
                         'columns': [{'class': 'kam-blockrow' + (' text-center' if centred else ''), 'width': 100, 'padding': {'left': 12, 'right': 12}, 'widgets': w}]})
    centred = 'warranty-header' in cls
    for k in top.children:
        if isinstance(k, Comment): continue
        if isinstance(k, NavigableString):
            if squash(str(k)): pending.append(k)
            continue
        if k.name == 'div' and 'row' in k.get('class', []):
            flush_pending()
            rcls = k.get('class', [])
            cols = [c for c in k.children if isinstance(c, Tag) and any(x.startswith('col') for x in c.get('class', []))]
            built = [column(c) for c in cols]
            row = {'wrap': wrap or 'kam-blockrow', 'wrap_padding': dict(wrap_pad), 'class': inner_cls,
                   'columns': [b[0] for b in built]}
            if 'g-4' in rcls:          # 24px gutters both ways; the row pulls up by 24
                for col in row['columns']:
                    col['padding']['top'] = 24
                row['margin'] = {'top': -24}
            if 'flex-column-reverse' in rcls:
                row['class'] += ' kam-reverse-mobile'
            if 'justify-content-center' in rcls:
                row['class'] += ' justify-content-center'
            rows.append(row)
        elif k.name == 'div' and 'card' in k.get('class', []):
            flush_pending()
            body = k.select_one('.card-body') or k
            pad, pad_m = card_padding(body)
            inner = {'class': 'kam-blockrow ' + ' '.join(k.get('class', [])), 'padding': pad, 'widgets': widgets(body)}
            if pad_m: inner['padding_mobile'] = pad_m
            rows.append({'wrap': wrap or 'kam-blockrow', 'wrap_padding': dict(wrap_pad), 'class': inner_cls,
                         'columns': [{'class': 'kam-blockrow', 'width': 100, 'padding': {'left': 12, 'right': 12}, 'inner': inner}]})
        else:
            pending.append(k)
    flush_pending()
    # The band's padding is only on its first and last rows; the ones between carry none.
    for i, r in enumerate(rows):
        if i > 0: r['wrap_padding']['top'] = 0
        if i < len(rows) - 1: r['wrap_padding']['bottom'] = 0
    if top.get('id'):
        rows[0]['id'] = top['id']
    return rows

def convert(slug):
    html = open(os.path.join(ROOT, 'capture/html', PAGES[slug] + '.html'), encoding='utf-8', errors='ignore').read()
    main = BeautifulSoup(re.search(r'<main>(.*?)</main>', html, re.S).group(1), 'lxml')
    rows = []
    tops = [t for t in main.body.children if not isinstance(t, Comment) and (isinstance(t, Tag) and t.name != 'style' or (isinstance(t, NavigableString) and squash(str(t))))]
    for top in tops:
        if isinstance(top, NavigableString):
            raise SystemExit(slug + ': loose text at top level: ' + squash(str(top))[:60])
        if top.name == 'br':
            rows.append({'class': 'kam-blockrow', 'widgets': [{'widget': 'spacer', 'fields': {'height': LINE}}]}); continue
        rows.extend(band_rows(top, html))
    return {'page': slug, 'replace': True, 'blocks': rows}

if __name__ == '__main__':
    slugs = list(PAGES) if '--all' in sys.argv else [a for a in sys.argv[1:] if not a.startswith('-')]
    for slug in slugs:
        doc = convert(slug)
        out = os.path.join(ROOT, 'build', slug + '.json')
        json.dump(doc, open(out, 'w'), indent=1, ensure_ascii=False)
        n = sum(len(c.get('widgets') or c.get('inner', {}).get('widgets', [])) for r in doc['blocks'] for c in r.get('columns', [])) + sum(len(r.get('widgets', [])) for r in doc['blocks'])
        print(slug, 'rows', len(doc['blocks']), 'widgets', n)
