"""
Generates the automatic part of public/assets/css/erp-dark.css (between the AUTO markers):
for every rule of erp-pro.css / erp-ui.css that sets a dark text colour, a light background or a
light border, the same selector under html[data-theme="dark"] with the dark-theme token.
Re-run after changing those files:  python scripts/build-dark-css.py
"""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SOURCES = [ROOT / 'public/assets/css/erp-pro.css', ROOT / 'public/assets/css/erp-ui.css']
TARGET = ROOT / 'public/assets/css/erp-dark.css'
START, END = '/* AUTO-DARK:START (scripts/build-dark-css.py) */', '/* AUTO-DARK:END */'

# Parts that must keep their light colours (printed paper, brand band, coloured pills handled by hand).
SKIP = ('erp-signature', 'erp-status', 'tone-', 'erp-todo-', 'erp-brand', 'main-sidebar-header', 'erp-sidebar',
        'side-menu', 'app-sidebar', 'slide', '::-webkit', 'erp-page-loader', '.btn-primary', 'erp-avatar', 'erp-row-new')


def rgb(value):
    value = value.strip().lower()
    if value in ('#fff', 'white'):
        return (255, 255, 255)
    m = re.fullmatch(r'#([0-9a-f]{3}|[0-9a-f]{6})', value)
    if not m:
        return None
    h = m.group(1)
    if len(h) == 3:
        h = ''.join(c * 2 for c in h)
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def luminance(c):
    return (0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]) / 255


def blocks(css):
    """Top-level rules (selector, body), media blocks skipped except the screen ones (kept with their query)."""
    css = re.sub(r'/\*.*?\*/', '', css, flags=re.S)
    i, out = 0, []
    while i < len(css):
        brace = css.find('{', i)
        if brace < 0:
            break
        head = css[i:brace].strip()
        depth, j = 1, brace + 1
        while depth and j < len(css):
            depth += {'{': 1, '}': -1}.get(css[j], 0)
            j += 1
        body = css[brace + 1:j - 1]
        if head.startswith('@media'):
            if 'print' not in head:
                out += [(head, s, b) for _, s, b in blocks(body)]
        elif not head.startswith('@'):
            out.append((None, head, body))
        i = j
    return out


def dark_rules():
    rules = []
    for source in SOURCES:
        for media, selector, body in blocks(source.read_text(encoding='utf-8')):
            if any(skip in selector for skip in SKIP) or selector.startswith(':root'):
                continue
            decls = []
            for prop, value in re.findall(r'(?:^|;)\s*([a-z-]+)\s*:\s*([^;]+)', body):
                important = ' !important' if '!important' in value else ''
                value = value.replace('!important', '').strip()
                c = rgb(value)
                if prop == 'color' and c and luminance(c) < 0.45:
                    saturated = max(c) - min(c) > 70
                    token = 'var(--erp-primary-hover)' if saturated and c[2] > c[0] else ('var(--erp-ink)' if luminance(c) < 0.25 else 'var(--erp-ink-soft)')
                    decls.append(f'color: {token}{important}')
                elif prop in ('background', 'background-color') and c and luminance(c) > 0.85:
                    decls.append(f'background: {"var(--erp-surface)" if c == (255, 255, 255) else "var(--erp-surface-2)"}{important}')
                elif prop in ('border-color', 'border', 'border-top', 'border-bottom', 'border-left', 'border-right'):
                    m = re.search(r'#[0-9a-fA-F]{3,6}\b', value)
                    if m and rgb(m.group(0)) and luminance(rgb(m.group(0))) > 0.7:
                        side = '' if prop in ('border', 'border-color') else prop[7:]
                        decls.append(f'border{("-" + side) if side else ""}-color: var(--erp-line){important}')
            if decls:
                scoped = ', '.join('html[data-theme="dark"] ' + s.strip() for s in selector.split(','))
                rules.append((media, scoped, decls))
    return rules


def render(rules):
    lines = [START]
    for media, selector, decls in rules:
        rule = selector + ' { ' + '; '.join(decls) + '; }'
        lines.append(f'{media} {{ {rule} }}' if media else rule)
    lines.append(END)
    return '\n'.join(lines)


css = TARGET.read_text(encoding='utf-8')
generated = render(dark_rules())
if START in css:
    css = css[:css.index(START)] + generated + css[css.index(END) + len(END):]
else:
    anchor = '/* ---------- Page, text'
    css = css.replace(anchor, generated + '\n\n' + anchor, 1)
TARGET.write_text(css, encoding='utf-8')
print(f'{generated.count(chr(10)) - 1} dark rules generated')
