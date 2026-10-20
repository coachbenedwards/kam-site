#!/usr/bin/env python3
"""
The home page (capture/html/index.php.html) as a page-blocks layout.

Carousel -> kam_hero block. Service grids -> one Image Box widget per tile
(image, title, words and link are its fields). About -> heading + text.
Reviews -> kam_reviews block, seeded from the six reviews the live page
loaded on capture day (capture/ratings.json).

    python3 tools/home-to-blocks.py        # writes build/home.json
"""
import json, os, re, sys
from bs4 import BeautifulSoup, Tag
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from branch_lib import fix_url, fix_html, squash, column_widgets, ROOT, MEDIA

# .container-fluid pads 60px 10px 80px on the live site. Elementor writes a
# container's padding per element, so the band asks for it here.
BAND = {'top': 60, 'right': 10, 'bottom': 80, 'left': 10}

def grid_columns(row, cls_for_widget):
    cols = []
    for c in [k for k in row.children if isinstance(k, Tag)]:
        cls = c.get('class', [])
        a = c.find('a'); img = c.find('img'); h = c.find(['h5', 'h4', 'h6']); p = c.find('p')
        w = {'widget': 'image-box', 'fields': {
            'url': fix_url(img['src']) if img else '', 'alt': img.get('alt', '') if img else '',
            'title': squash(h.get_text()) if h else '', 'tag': h.name if h else 'h5',
            'text': squash(fix_html(p)) if p else '', 'link': fix_url(a['href']) if a else '',
            'align': 'center', 'class': cls_for_widget}}
        # col-6 col-lg-4: a third on desktop, half on tablet (Bootstrap's lg
        # starts at 992, Elementor's tablet ends at 1024) and half on a phone.
        # .row.g-4: 12px either side, 24px above each tile.
        cols.append({'class': 'kam-blockrow', 'width': 33.333, 'tablet': 50, 'mobile': 50,
                     'padding': {'top': 24, 'right': 12, 'bottom': 0, 'left': 12}, 'widgets': [w]})
    return cols

def convert():
    html = open(os.path.join(ROOT, 'capture/html/index.php.html'), encoding='utf-8', errors='ignore').read()
    main = BeautifulSoup(re.search(r'<main>(.*?)</main>', html, re.S).group(1), 'lxml')
    tops = [t for t in main.body.children if isinstance(t, Tag) and t.name != 'style']
    rows = []

    # 1. Carousel
    car = tops[0]; assert 'carousel' in car.get('class', [])
    lines = []
    for item in car.select('.carousel-item'):
        img = item.find('img'); hs = item.select('.carousel-caption > *')
        lines.append('|'.join([fix_url(img['src']), squash(hs[0].get_text()) if hs else '', squash(hs[1].get_text()) if len(hs) > 1 else '']))
    rows.append({'class': 'kam-blockrow', 'widgets': [{'block': 'kam_hero', 'fields': {'id': car.get('id', 'demo')}, 'lines': '\n'.join(lines)}]})

    # 2. Service grid (12 tiles)
    grid = tops[1]; r = grid.select_one('.row')
    rows.append({'wrap': 'container-fluid', 'wrap_padding': {**BAND, 'top': BAND['top'] - 24},   # the row's -24px top margin
                 'class': 'container kam-grid', 'columns': grid_columns(r, 'service-item')})

    # 3. About: band (grey, inline style on the live site) > container > h4 + one full column
    about = tops[2]; assert about.get('id') == 'about'
    h4 = about.find('h4'); col = about.select_one('.row > div')
    rows.append({'wrap': 'container-fluid container-grey', 'wrap_padding': BAND, 'id': 'about', 'class': 'container',
                 'columns': [{'class': 'kam-blockrow text-center', 'width': 100, 'padding': {'left': 12, 'right': 12},
                              'widgets': [{'widget': 'heading', 'fields': {'tag': 'h4', 'title': squash(h4.get_text()), 'align': 'center', 'class': 'mb-4'}}]
                                         + column_widgets(col)}]})

    # 4. Services: band > container > h4, grid of 6, "View All Services"
    services = tops[3]; assert services.get('id') == 'services'
    h4 = services.find('h4'); grid_row, btn_row = services.select('.container > .row')
    # The h4's 24px bottom margin and the grid row's -24px top margin cancel on
    # the live site, so here the heading carries no margin and the tiles' own
    # 24px top padding is the gap.
    rows.append({'wrap': 'container-fluid container-grey', 'wrap_padding': {**BAND, 'bottom': 0}, 'id': 'services', 'class': 'container',
                 'columns': [{'class': 'kam-blockrow', 'width': 100, 'padding': {'left': 12, 'right': 12},
                              'widgets': [{'widget': 'heading', 'fields': {'tag': 'h4', 'title': squash(h4.get_text()), 'align': 'center', 'class': 'mb-0'}}]}]})
    rows.append({'wrap': 'container-fluid container-grey', 'wrap_padding': {'top': 0, 'right': 10, 'bottom': 0, 'left': 10},
                 'class': 'container kam-grid', 'columns': grid_columns(grid_row, 'service-item')})
    btn = btn_row.select_one('p')
    rows.append({'wrap': 'container-fluid container-grey', 'wrap_padding': {**BAND, 'top': 0}, 'class': 'container',
                 'columns': [{'class': 'kam-blockrow view-all-services', 'width': 100, 'padding': {'top': 60, 'left': 12, 'right': 12},
                              'widgets': [{'widget': 'text-editor', 'fields': {'editor': '<p class="text-center mb-0">' + fix_html(btn) + '</p>'}}]}]})

    # 5. Reviews
    ratings = tops[4]; assert ratings.get('id') == 'ratings'
    h4 = ratings.find('h4')
    rj = json.load(open(os.path.join(ROOT, 'capture/ratings.json')))
    cards = BeautifulSoup(rj['content'], 'lxml').select('.card')
    lines = [squash(c.h6.get_text()) + '|' + squash(c.select_one('.feedback-text').get_text(' ')) for c in cards]
    frag = BeautifulSoup(rj['content'], 'lxml')
    rating = squash(frag.select_one('.rating-text').get_text()) if frag.select_one('.rating-text') else ''
    ggs = next((a for a in frag.select('a.badge-card') if 'goodgaragescheme' in a.get('href', '')), None)
    rows.append({'wrap': 'container-fluid container-grey', 'wrap_padding': BAND, 'id': 'ratings', 'class': 'container',
                 'columns': [{'class': 'kam-blockrow', 'width': 100, 'padding': {'left': 12, 'right': 12},
                              'widgets': [{'widget': 'heading', 'fields': {'tag': 'h4', 'title': squash(h4.get_text()), 'align': 'center', 'class': 'mb-4'}},
                                          {'block': 'kam_reviews', 'fields': {
                                              'google_logo': MEDIA + 'Google_2015_logo.svg_.webp', 'google_rating': rating,
                                              'ggs_logo': MEDIA + 'logo.png', 'ggs_url': ggs['href'] if ggs else ''},
                                           'lines': '\n'.join(lines)}]}]})
    return {'page': 'home', 'replace': True, 'blocks': rows}

if __name__ == '__main__':
    doc = convert()
    out = os.path.join(ROOT, 'build', 'home.json')
    json.dump(doc, open(out, 'w'), indent=1, ensure_ascii=False)
    print('home rows', len(doc['blocks']), '->', os.path.relpath(out, ROOT))
