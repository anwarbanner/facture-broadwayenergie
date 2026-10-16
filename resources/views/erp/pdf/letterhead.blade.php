{{--
    Company letterhead for every commercial document (invoice, credit note, quote, order, delivery, receipt...).
    Geometry follows the paper letterhead, in pt from the top-left corner of an A4 page (595 x 842 pt):
      logo x 7-205 / y 7-109, slogan x 320-545 / y 70-93, client frame x 267-543 / y 160-263,
      watermark x -24-632 / y 274-606, footer rules x 91-543 / y 800-802, footer lines baselines 815 / 824 / 834.
    Page margins: 125 pt top (logo + slogan), 52 pt sides, 62 pt bottom (rules + footer lines).
    Fixed elements are positioned relative to the page content box, hence "x - 52" and "y - 125" below.
    Data: App\Services\Pdf\LetterheadDocument.
--}}
@php
    $primary = \App\Support\Branding::colorPrimary();
    $accent = \App\Support\Branding::colorAccent();
    $logo = \App\Support\Branding::logoFile();
    $watermark = \App\Support\Branding::watermarkFile();
    $legalForm = \App\Support\Branding::legalForm();
    $slogan = \App\Support\Branding::sloganLines();
    $footer = \App\Support\Branding::footerLines();
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ');
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', ' '), '0'), ',');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $doc['title'] }} {{ $doc['number'] }}</title>
    <style>
        @page { size: A4 portrait; margin: 125pt 52pt 62pt 52pt; }

        body {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9pt;
            line-height: 1.35;
            color: #1d2733;
        }

        /* ---- Letterhead (repeated on every page) ---- */
        .lh-watermark { position: fixed; left: -76pt; top: 149pt; width: 656pt; height: 332pt; }
        .lh-watermark img { width: 656pt; height: 332pt; }
        .lh-logo { position: fixed; left: -45pt; top: -118pt; width: 198pt; }
        .lh-logo img { width: 198pt; }
        .lh-legal-form {
            position: fixed; left: 126pt; top: -55.5pt;
            font-size: 8.5pt; font-weight: bold; line-height: 1; letter-spacing: .3pt; color: #4b6a88;
        }
        .lh-slogan {
            position: fixed; left: 250.5pt; top: -59pt; width: 260pt;
            text-align: center; font-size: 10.6pt; font-weight: bold; line-height: 12pt; color: {{ $primary }};
            white-space: nowrap;
        }
        .lh-rule { position: fixed; left: 39pt; width: 452pt; height: 0; }
        .lh-rule-primary { top: 674.4pt; border-top: 1.3pt solid {{ $primary }}; }
        .lh-rule-accent { top: 675.9pt; border-top: 1.3pt solid {{ $accent }}; }
        .lh-footer {
            position: fixed; left: -52pt; width: 595pt; height: 9pt;
            text-align: center; font-size: 7.5pt; line-height: 1; letter-spacing: .6pt; color: {{ $primary }};
            white-space: nowrap;
        }

        /* ---- First page: client frame (only corners are drawn) ---- */
        .first-page { position: relative; height: 155pt; }
        .party-frame { position: absolute; left: 215pt; top: 35pt; width: 276pt; height: 103pt; }
        .corner { position: absolute; width: 12pt; height: 12pt; border: 0 solid #000; }
        .corner.tl { left: 0; top: 0; border-top-width: .75pt; border-left-width: .75pt; }
        .corner.tr { right: 0; top: 0; border-top-width: .75pt; border-right-width: .75pt; }
        .corner.bl { left: 0; bottom: 0; border-bottom-width: .75pt; border-left-width: .75pt; }
        .corner.br { right: 0; bottom: 0; border-bottom-width: .75pt; border-right-width: .75pt; }
        .party-text { position: absolute; left: 8pt; top: 8pt; right: 8pt; font-size: 8.5pt; line-height: 11pt; }
        .party-label { font-size: 7pt; letter-spacing: .6pt; text-transform: uppercase; color: #6b7a8c; }
        .party-name { font-size: 10pt; font-weight: bold; color: #0f1c2b; }

        /* ---- Content zone ---- */
        .doc-head { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
        .doc-title { font-size: 15pt; font-weight: bold; color: {{ $primary }}; letter-spacing: .5pt; }
        .doc-number { font-size: 10pt; font-weight: bold; color: #0f1c2b; margin-top: 2pt; }
        .draft-note { margin-top: 3pt; font-size: 8pt; color: {{ $accent }}; font-weight: bold; }
        .meta td { padding: 1pt 0; font-size: 8.5pt; }
        .meta .k { color: #6b7a8c; padding-right: 8pt; white-space: nowrap; }
        .meta .v { font-weight: bold; text-align: right; }

        table.lines { width: 100%; border-collapse: collapse; margin-top: 4pt; }
        table.lines th {
            background: {{ $primary }}; color: #fff; font-size: 8pt; font-weight: bold; text-transform: uppercase;
            letter-spacing: .3pt; padding: 5pt 6pt; text-align: left;
        }
        table.lines td { padding: 5pt 6pt; border-bottom: .5pt solid #d9e1ea; vertical-align: top; }
        table.lines tr:nth-child(even) td { background: #f6f9fc; }
        .num { text-align: right !important; white-space: nowrap; }

        /* Totals and amount in words are never split across two pages */
        .summary { page-break-inside: avoid; }
        .totals-wrap { width: 100%; margin-top: 10pt; }
        table.totals tr { page-break-inside: avoid; }
        table.totals { width: 215pt; margin-left: auto; border-collapse: collapse; }
        table.totals td { padding: 4pt 6pt; border-bottom: .5pt solid #d9e1ea; }
        table.totals .label { color: #3b4a5c; }
        table.totals tr.grand td {
            background: {{ $primary }}; color: #fff; font-weight: bold; font-size: 10pt; border-bottom: 0;
        }
        table.totals tr.due td { font-weight: bold; color: {{ $accent }}; }

        .words { margin-top: 12pt; padding: 7pt 9pt; border-left: 2pt solid {{ $primary }}; background: #f3f7fb; font-size: 8.5pt; }
        .words strong { color: #0f1c2b; }
        .notes { margin-top: 10pt; font-size: 8pt; color: #3b4a5c; }

        table.signatures { width: 100%; margin-top: 26pt; border-collapse: separate; border-spacing: 12pt 0; }
        table.signatures td { width: 50%; height: 62pt; border: .5pt solid #b8c4d1; vertical-align: top; padding: 5pt 7pt; font-size: 8pt; color: #3b4a5c; }

        /* Signature / company stamp (Administration > Signature et cachet) */
        table.sign-block { width: 100%; margin-top: 18pt; border-collapse: collapse; page-break-inside: avoid; }
        table.sign-block td.sign-cell { width: 200pt; border: .5pt solid #b8c4d1; padding: 5pt 7pt; vertical-align: top; font-size: 8pt; color: #3b4a5c; text-align: center; }
        .sign-label { text-align: left; }
        .sign-name { text-align: left; font-weight: bold; color: #0f1c2b; margin-top: 1pt; }
        img.sign-img { max-width: 185pt; max-height: 72pt; margin-top: 4pt; }
    </style>
</head>
<body>
    {{-- Letterhead: first in the document so that everything else is drawn above the watermark --}}
    @if($watermark)
        <div class="lh-watermark"><img src="{{ $watermark }}" alt=""></div>
    @endif
    <div class="lh-logo">
        @if($logo)
            <img src="{{ $logo }}" alt="{{ \App\Support\Branding::companyName() }}">
        @else
            <strong style="font-size:18pt;color:{{ $primary }}">{{ \App\Support\Branding::companyName() }}</strong>
        @endif
    </div>
    @if($logo && $legalForm !== '')
        <div class="lh-legal-form">{{ $legalForm }}</div>
    @endif
    @if($slogan)
        <div class="lh-slogan">{!! collect($slogan)->map(fn ($line) => e($line))->implode('<br>') !!}</div>
    @endif
    <div class="lh-rule lh-rule-primary"></div>
    <div class="lh-rule lh-rule-accent"></div>
    @foreach($footer as $i => $line)
        <div class="lh-footer" style="top: {{ [684.3, 693.3, 703.3][$i] ?? 703.3 + ($i - 2) * 9.5 }}pt;">{{ $line }}</div>
    @endforeach

    {{-- First page: client / supplier frame --}}
    <div class="first-page">
        <div class="party-frame">
            <div class="corner tl"></div><div class="corner tr"></div>
            <div class="corner bl"></div><div class="corner br"></div>
            <div class="party-text">
                <div class="party-label">{{ $doc['party_label'] }}</div>
                <div class="party-name">{{ $doc['party']['name'] }}</div>
                @foreach($doc['party']['lines'] as $line)
                    <div>{{ $line }}</div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Document number, date and references --}}
    <table class="doc-head">
        <tr>
            <td style="vertical-align: top;">
                <div class="doc-title">{{ $doc['title'] }}</div>
                <div class="doc-number">N° {{ $doc['number'] }}</div>
                @if($doc['draft'])
                    <div class="draft-note">Document provisoire, sans valeur comptable.</div>
                @endif
            </td>
            <td style="vertical-align: top; width: 210pt;">
                <table class="meta" style="width: 100%;">
                    @foreach($doc['meta'] as $key => $value)
                        <tr><td class="k">{{ $key }}</td><td class="v">{{ $value }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    @if($doc['kind'] === 'priced')
        <table class="lines">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th class="num" style="width: 48pt;">Qté</th>
                    <th class="num" style="width: 82pt;">P.U. HT</th>
                    <th class="num" style="width: 88pt;">Total HT</th>
                </tr>
            </thead>
            <tbody>
                @forelse($doc['lines'] as $line)
                    <tr>
                        <td>{{ $line['designation'] }}</td>
                        <td class="num">{{ $qty($line['quantity']) }}</td>
                        <td class="num">{{ $money($line['unit_price']) }}</td>
                        <td class="num">{{ $money($line['total']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;color:#6b7a8c;">Aucune ligne.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="summary">
        <div class="totals-wrap">
            <table class="totals">
                <tr><td class="label">Total HT</td><td class="num">{{ $money($doc['totals']['ht']) }} {{ $doc['currency'] }}</td></tr>
                <tr><td class="label">{{ $doc['totals']['tva_label'] }}</td><td class="num">{{ $money($doc['totals']['tva']) }} {{ $doc['currency'] }}</td></tr>
                <tr class="grand"><td>Total TTC</td><td class="num">{{ $money($doc['totals']['ttc']) }} {{ $doc['currency'] }}</td></tr>
                @if($doc['payments'])
                    <tr><td class="label">Déjà réglé</td><td class="num">{{ $money($doc['payments']['paid']) }} {{ $doc['currency'] }}</td></tr>
                    <tr class="due"><td>Reste à payer</td><td class="num">{{ $money($doc['payments']['balance']) }} {{ $doc['currency'] }}</td></tr>
                @endif
            </table>
        </div>

        @if($doc['amount_in_words'])
            <div class="words">{{ \Illuminate\Support\Str::before($doc['amount_in_words'], ' : ') }} :
                <strong>{{ \Illuminate\Support\Str::after($doc['amount_in_words'], ' : ') }}</strong></div>
        @endif

        @if($doc['signature'])
            <table class="sign-block">
                <tr>
                    <td></td>
                    <td class="sign-cell">
                        <div class="sign-label">Cachet et signature</div>
                        @if($doc['signature']['signatory'])<div class="sign-name">{{ $doc['signature']['signatory'] }}</div>@endif
                        <img class="sign-img" src="{{ $doc['signature']['image'] }}" alt="">
                    </td>
                </tr>
            </table>
        @endif
        </div>
    @else
        <table class="lines">
            <thead>
                <tr>
                    <th>Désignation</th>
                    @unless($doc['single'] ?? false)
                    <th class="num" style="width: 90pt;">Qté commandée</th>
                    @endunless
                    <th class="num" style="width: 90pt;">{{ $doc['done_label'] }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($doc['lines'] as $line)
                    <tr>
                        <td>{{ $line['designation'] }}</td>
                        @unless($doc['single'] ?? false)
                        <td class="num">{{ $qty($line['ordered']) }}</td>
                        @endunless
                        <td class="num"><strong>{{ $qty($line['done']) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align:center;color:#6b7a8c;">Aucune ligne.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if($doc['signatures'])
            <table class="signatures">
                <tr>
                    @foreach($doc['signatures'] as $label)
                        <td>{{ $label }}
                            {{-- The company's box (the last one) carries the uploaded signature --}}
                            @if($loop->last && $doc['signature'])
                                @if($doc['signature']['signatory'])<div class="sign-name">{{ $doc['signature']['signatory'] }}</div>@endif
                                <div style="text-align: center;"><img class="sign-img" style="max-height: 48pt;" src="{{ $doc['signature']['image'] }}" alt=""></div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif
    @endif

    @foreach($doc['notes'] as $note)
        <div class="notes">{{ $note }}</div>
    @endforeach
</body>
</html>
