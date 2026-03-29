#!/usr/bin/env python3
"""
WordPress (SANGO theme) HTML → Project Markdown converter.

Usage:
    python3 convert_html_to_md.py <URL> [output_file]

Dependencies:
    pip install beautifulsoup4 requests
"""

import sys
import os
import re
from datetime import date
from urllib.parse import urlparse

try:
    from bs4 import BeautifulSoup, NavigableString, Tag
except ImportError:
    print("beautifulsoup4 が必要です。以下のコマンドでインストールしてください:", file=sys.stderr)
    print("  pip install beautifulsoup4", file=sys.stderr)
    sys.exit(1)

try:
    import requests
except ImportError:
    print("requests が必要です。以下のコマンドでインストールしてください:", file=sys.stderr)
    print("  pip install requests", file=sys.stderr)
    sys.exit(1)

# ---------------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------------

INTERNAL_LINK_MAP = {
    '/sokmill-link/': 'https://www.sokmil.com/av/?ref=header',
    '/duga-monthly-link/': 'https://duga.jp/',
    '/rakuten-tv/': 'https://tv.rakuten.co.jp/',
    '/xcity-link/': 'https://xcity.jp/',
    '/hnext-link/': 'https://video.unext.jp/',
    '/dmmpremium/': '/article/dmmpremium/',
    '/duga/': '/article/duga/',
    '/xcity/': '/article/xcity/',
    '/sokmil/': '/article/sokmil/',
}

SANGO_BOX_MAP = {
    'sankou': ':::box',
    'attention': ':::alert',
    'point': ':::memo',
    'kaisetsu': ':::box',
    'question': ':::faq',
    'good': ':::box',
    'bad': ':::alert',
    'kanren': '',
    'innerlink': '',
    'relation': '',
}

SITE_ORIGIN = 'https://hpkenkyu.mixh.jp'

ARTICLES_DIR = os.path.join(os.path.dirname(__file__), '..', '..', '..', '..',
                            'meikan', 'content', 'articles')
ARTICLES_DIR = os.path.normpath(ARTICLES_DIR)

USER_AGENT = (
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
    'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
)

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


def fetch_html(url: str) -> str:
    """Fetch page HTML with a browser-like User-Agent."""
    resp = requests.get(url, headers={'User-Agent': USER_AGENT}, timeout=30)
    resp.raise_for_status()
    resp.encoding = resp.apparent_encoding
    return resp.text


def slug_from_url(url: str) -> str:
    path = urlparse(url).path.strip('/')
    return path.split('/')[-1] if path else 'untitled'


def resolve_internal_link(href: str) -> str | None:
    """Resolve an internal link. Returns the new URL or None to strip the link."""
    parsed = urlparse(href)
    # Only process links to the old site or relative links
    if parsed.netloc and parsed.netloc != urlparse(SITE_ORIGIN).netloc:
        return href  # external link, keep as-is

    path = parsed.path
    if not path.startswith('/'):
        path = '/' + path

    # Exact mapping
    if path in INTERNAL_LINK_MAP:
        return INTERNAL_LINK_MAP[path]

    # Try slug lookup in articles directory
    slug = path.strip('/').split('/')[-1]
    if slug:
        md_path = os.path.join(ARTICLES_DIR, f'{slug}.md')
        if os.path.isfile(md_path):
            return f'/article/{slug}/'

    # No mapping found – strip the link
    return None


def get_img_src(img_tag: Tag) -> str:
    """Extract best image src from <img> tag."""
    for attr in ('data-src', 'data-lazy-src', 'src'):
        val = img_tag.get(attr, '')
        if val and val.startswith('https://'):
            return val
    return img_tag.get('src', '')


def is_toc_element(el: Tag) -> bool:
    """Check if element is a table-of-contents block."""
    if not isinstance(el, Tag):
        return False
    el_id = el.get('id', '')
    el_classes = ' '.join(el.get('class', []))
    if 'toc_container' in el_id or 'ez-toc' in el_classes:
        return True
    text = el.get_text(strip=True)
    if text.startswith('目次') and el.find('a'):
        return True
    return False


def is_share_link(el: Tag) -> bool:
    """Check if an <a> is a social share link."""
    href = el.get('href', '')
    share_patterns = ['twitter.com/intent', 'facebook.com/share',
                      'b.hatena.ne.jp', 'line.me/R/msg']
    return any(p in href for p in share_patterns)


def is_excluded_block(el: Tag) -> bool:
    """Check if a block-level element should be excluded from output."""
    if not isinstance(el, Tag):
        return False
    classes = ' '.join(el.get('class', []))
    # Related article cards
    for kw in ('kanren', 'relation', 'innerlink'):
        if kw in classes:
            return True
    # Prev/Next navigation
    if el.name == 'nav' or 'prev' in classes or 'next' in classes:
        return True
    if 'post-navi' in classes or 'pagination' in classes:
        return True
    # Category line
    text = el.get_text(strip=True)
    if text.startswith('CATEGORY :') or text.startswith('CATEGORY:'):
        return True
    # PR-only paragraph
    if el.name == 'p' and text == 'PR':
        return True
    return False


def has_class_containing(el: Tag, keyword: str) -> bool:
    classes = el.get('class', [])
    return any(keyword in c for c in classes)


# ---------------------------------------------------------------------------
# Inline conversion
# ---------------------------------------------------------------------------


def convert_inline(el) -> str:
    """Convert an element's inline content to Markdown text."""
    if isinstance(el, NavigableString):
        text = str(el)
        # Collapse internal whitespace but preserve single newlines as spaces
        text = re.sub(r'[ \t]+', ' ', text)
        return text

    if not isinstance(el, Tag):
        return ''

    tag = el.name

    # <br> → newline
    if tag == 'br':
        return '\n'

    # Skip images handled at block level
    if tag == 'img':
        src = get_img_src(el)
        alt = el.get('alt', '')
        return f'![{alt}]({src})'

    # Inline formatting
    children_text = ''.join(convert_inline(c) for c in el.children)

    if tag in ('strong', 'b'):
        inner = children_text.strip()
        if not inner:
            return ''
        return f'**{inner}**'

    if tag in ('em', 'i'):
        inner = children_text.strip()
        if not inner:
            return ''
        return f'*{inner}*'

    if tag == 'a':
        href = el.get('href', '')
        text = children_text.strip()
        if is_share_link(el):
            return ''
        if not text:
            return ''
        # Resolve internal links
        if href.startswith('/') or SITE_ORIGIN in href:
            rel_href = href.replace(SITE_ORIGIN, '')
            resolved = resolve_internal_link(rel_href)
            if resolved is None:
                return text  # strip link, keep text
            href = resolved
        return f'[{text}]({href})'

    if tag == 'span':
        classes = ' '.join(el.get('class', []))
        if 'marker' in classes:
            inner = children_text.strip()
            if 'red' in classes:
                return f'==[red]{inner}=='
            return f'=={inner}=='

    return children_text


def convert_inline_children(parent: Tag) -> str:
    """Convert all children of a parent element to inline Markdown."""
    parts = []
    for child in parent.children:
        parts.append(convert_inline(child))
    return ''.join(parts).strip()


# ---------------------------------------------------------------------------
# Table conversion
# ---------------------------------------------------------------------------


def convert_table_cell(cell: Tag) -> str:
    """Convert a <th>/<td> cell to Markdown cell content.

    Handles <img>, <a>, and nested <img> inside <a> individually.
    """
    parts = []
    for child in cell.children:
        if isinstance(child, Tag):
            if child.name == 'img':
                src = get_img_src(child)
                if src:
                    parts.append(f'!img[{src}]')
            elif child.name == 'a':
                href = child.get('href', '')
                # Check for nested <img> inside the <a>
                nested_img = child.find('img')
                if nested_img:
                    src = get_img_src(nested_img)
                    if src:
                        parts.append(f'!img[{src}]')
                text = child.get_text(strip=True)
                # Resolve internal links
                if href.startswith('/') or SITE_ORIGIN in href:
                    rel_href = href.replace(SITE_ORIGIN, '')
                    resolved = resolve_internal_link(rel_href)
                    if resolved is None:
                        if text:
                            parts.append(text)
                    else:
                        if text:
                            parts.append(f'[{text}]({resolved})')
                else:
                    if text:
                        parts.append(f'[{text}]({href})')
            else:
                parts.append(convert_inline(child))
        elif isinstance(child, NavigableString):
            t = str(child).strip()
            if t:
                parts.append(t)
    return ' '.join(p.replace('\n', ' ').strip() for p in parts if p.strip())


def convert_table(table: Tag) -> str:
    """Convert an HTML <table> to Markdown table."""
    rows = table.find_all('tr')
    if not rows:
        return ''

    md_rows = []
    for row in rows:
        cells = row.find_all(['th', 'td'])
        md_cells = [convert_table_cell(c).replace('|', '\\|') for c in cells]
        md_rows.append('| ' + ' | '.join(md_cells) + ' |')

    if len(md_rows) < 1:
        return ''

    # Insert separator after first row
    num_cols = md_rows[0].count('|') - 1
    separator = '|' + '|'.join(['---'] * num_cols) + '|'
    result = [md_rows[0], separator] + md_rows[1:]
    return '\n'.join(result)


# ---------------------------------------------------------------------------
# Block conversion
# ---------------------------------------------------------------------------


def detect_sango_box(el: Tag) -> str | None:
    """Detect SANGO theme box class and return the corresponding Markdown directive."""
    classes = ' '.join(el.get('class', []))
    for keyword, directive in SANGO_BOX_MAP.items():
        if keyword in classes:
            return directive
    return None


def detect_speech_bubble(el: Tag) -> bool:
    """Detect SANGO speech bubble / say block."""
    classes = el.get('class', [])
    return any(c == 'say' or c.startswith('say-') or c == 'speech-balloon'
               or c.startswith('speech-balloon-') or c == 'voice'
               for c in classes)


def detect_cta_button(el: Tag) -> tuple[str, str] | None:
    """Detect CTA button <a> and return (text, url) or None."""
    classes = ' '.join(el.get('class', []))
    if 'wp-block-button' in classes or 'btn' in classes:
        # Might be a wrapper div; find the <a> inside
        a_tag = el.find('a') if el.name != 'a' else el
        if a_tag and a_tag.name == 'a':
            text = a_tag.get_text(strip=True)
            href = a_tag.get('href', '')
            if href.startswith('/') or SITE_ORIGIN in href:
                rel_href = href.replace(SITE_ORIGIN, '')
                resolved = resolve_internal_link(rel_href)
                if resolved:
                    href = resolved
                else:
                    return None  # strip
            if text and href:
                return (text, href)
    return None


def extract_box_title(el: Tag) -> str:
    """Try to extract a title from a SANGO box element."""
    # SANGO boxes often have a title in a child with class containing 'title'
    for child in el.children:
        if isinstance(child, Tag):
            child_classes = ' '.join(child.get('class', []))
            if 'title' in child_classes:
                return child.get_text(strip=True)
    return ''


def convert_block(el, output: list[str]):
    """Convert a block-level element and append Markdown lines to output."""
    if isinstance(el, NavigableString):
        text = str(el).strip()
        if text:
            output.append(text)
            output.append('')
        return

    if not isinstance(el, Tag):
        return

    # Skip excluded elements
    if is_excluded_block(el):
        return
    if is_toc_element(el):
        return

    tag = el.name

    # script/style/noscript – already removed but just in case
    if tag in ('script', 'style', 'noscript'):
        return

    # Headings
    if tag in ('h1', 'h2', 'h3', 'h4', 'h5', 'h6'):
        level = int(tag[1])
        text = convert_inline_children(el)
        if text:
            prefix = '#' * level
            output.append(f'{prefix} {text}')
            output.append('')
        return

    # Horizontal rule
    if tag == 'hr':
        output.append('---')
        output.append('')
        return

    # Figure — may contain <table> (wp-block-table) or <img>
    if tag == 'figure':
        table = el.find('table')
        if table:
            md = convert_table(table)
            if md:
                output.append(md)
                output.append('')
            return
        img = el.find('img')
        if img:
            src = get_img_src(img)
            alt = img.get('alt', '')
            output.append(f'![{alt}]({src})')
            output.append('')
        return

    # Standalone img (not inside figure)
    if tag == 'img':
        src = get_img_src(el)
        alt = el.get('alt', '')
        output.append(f'![{alt}]({src})')
        output.append('')
        return

    # Table
    if tag == 'table':
        md = convert_table(el)
        if md:
            output.append(md)
            output.append('')
        return

    # Blockquote
    if tag == 'blockquote':
        text = convert_inline_children(el)
        if text:
            for line in text.split('\n'):
                output.append(f'> {line}')
            output.append('')
        return

    # Lists
    if tag == 'ul':
        items = el.find_all('li', recursive=False)
        for item in items:
            text = convert_inline_children(item)
            if text:
                output.append(f'- {text}')
        output.append('')
        return

    if tag == 'ol':
        items = el.find_all('li', recursive=False)
        for i, item in enumerate(items, 1):
            text = convert_inline_children(item)
            if text:
                output.append(f'{i}. {text}')
        output.append('')
        return

    # CTA button detection (div wrapper or direct a)
    cta = detect_cta_button(el)
    if cta:
        text, href = cta
        output.append(f'[btn {text}]({href})')
        output.append('')
        return

    # Speech bubble / say block
    if detect_speech_bubble(el):
        # Extract the speech text (skip the avatar/name parts)
        balloon = el.find(class_=lambda c: c and ('speech-balloon' in c or 'say-' in c))
        if balloon:
            text = convert_inline_children(balloon)
        else:
            text = convert_inline_children(el)
        if text:
            output.append(':::say')
            output.append(text)
            output.append(':::')
            output.append('')
        return

    # SANGO box detection
    if tag == 'div':
        directive = detect_sango_box(el)
        if directive is not None:
            if directive == '':
                # Excluded block (kanren, innerlink, relation)
                return
            title = extract_box_title(el)
            # Get inner content (skip the title element)
            inner_parts = []
            for child in el.children:
                if isinstance(child, Tag):
                    child_classes = ' '.join(child.get('class', []))
                    if 'title' in child_classes:
                        continue
                    convert_block(child, inner_parts)
                elif isinstance(child, NavigableString):
                    t = str(child).strip()
                    if t:
                        inner_parts.append(t)

            inner_text = '\n'.join(inner_parts).strip()
            if inner_text:
                if title:
                    output.append(f'{directive}[{title}]')
                else:
                    output.append(directive)
                output.append(inner_text)
                output.append(':::')
                output.append('')
            return

    # Paragraph
    if tag == 'p':
        text = convert_inline_children(el)
        if text:
            output.append(text)
            output.append('')
        return

    # Generic div / section / article – recurse into children
    if tag in ('div', 'section', 'article', 'main', 'aside', 'header', 'footer',
               'span', 'center'):
        for child in el.children:
            convert_block(child, output)
        return

    # Fallback: try to get text
    text = convert_inline_children(el)
    if text:
        output.append(text)
        output.append('')


# ---------------------------------------------------------------------------
# Main pipeline
# ---------------------------------------------------------------------------


def extract_meta(soup: BeautifulSoup) -> dict:
    """Extract meta information from the page."""
    meta = {}

    # Title from <h1> or <title>
    h1 = soup.find('h1')
    if h1:
        meta['title'] = h1.get_text(strip=True)
    else:
        title_tag = soup.find('title')
        if title_tag:
            meta['title'] = title_tag.get_text(strip=True)
        else:
            meta['title'] = ''

    # Remove site name suffix from title (common patterns)
    for sep in [' | ', ' - ', ' – ', ' — ']:
        if sep in meta['title']:
            meta['title'] = meta['title'].rsplit(sep, 1)[0].strip()

    # Description
    desc_tag = soup.find('meta', attrs={'name': 'description'})
    meta['description'] = desc_tag['content'] if desc_tag and desc_tag.get('content') else ''

    # Canonical
    canon = soup.find('link', attrs={'rel': 'canonical'})
    meta['canonical'] = canon['href'] if canon and canon.get('href') else ''

    return meta


def convert_page(url: str) -> str:
    """Fetch a page and convert it to project Markdown."""
    html = fetch_html(url)
    soup = BeautifulSoup(html, 'html.parser')

    # Extract meta
    meta = extract_meta(soup)
    slug = slug_from_url(url)
    today = date.today().isoformat()

    # Find entry-content
    entry = soup.find(class_='entry-content')
    if not entry:
        # Fallback: try article or main
        entry = soup.find('article') or soup.find('main')
    if not entry:
        print("Error: entry-content not found", file=sys.stderr)
        sys.exit(1)

    # Remove script/style/noscript
    for tag_name in ('script', 'style', 'noscript'):
        for t in entry.find_all(tag_name):
            t.decompose()

    # Convert blocks
    output_lines: list[str] = []
    for child in entry.children:
        convert_block(child, output_lines)

    body = '\n'.join(output_lines)

    # --- Post-processing ---

    # Remove bold from h3 headings: ### **text** → ### text
    body = re.sub(r'^(#{2,4}) \*\*(.+?)\*\*$', r'\1 \2', body, flags=re.MULTILINE)

    # Compress 3+ consecutive blank lines to 2
    body = re.sub(r'\n{3,}', '\n\n', body)

    # Strip trailing whitespace on each line
    body = '\n'.join(line.rstrip() for line in body.split('\n'))

    # Strip trailing blank lines
    body = body.rstrip('\n')

    # Build frontmatter
    # Escape YAML special chars in title/description
    title_yaml = meta['title'].replace('"', '\\"')
    desc_yaml = meta['description'].replace('"', '\\"')

    frontmatter = f'''---
title: "{title_yaml}"
slug: {slug}
description: "{desc_yaml}"
category:
published_at: {today}
updated_at: {today}
noindex: true
---'''

    return frontmatter + '\n\n' + body + '\n'


def main():
    if len(sys.argv) < 2:
        print(f"Usage: {sys.argv[0]} <URL> [output_file]", file=sys.stderr)
        sys.exit(1)

    url = sys.argv[1]
    output_file = sys.argv[2] if len(sys.argv) > 2 else None

    result = convert_page(url)

    if output_file:
        os.makedirs(os.path.dirname(os.path.abspath(output_file)), exist_ok=True)
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write(result)
        print(f"Written to {output_file}", file=sys.stderr)
    else:
        print(result)


if __name__ == '__main__':
    main()
