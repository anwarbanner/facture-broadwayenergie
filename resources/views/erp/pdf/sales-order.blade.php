<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Commande client {{ $order->order_number }}</title>
	<style>
		body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; margin: 24px; }
		.brandbar { width: 100%; border: 1px solid #bfdbfe; background: #eff6ff; border-radius: 6px; padding: 10px; margin-bottom: 16px; }
		.brand-logo { height: 46px; margin-right: 10px; vertical-align: middle; }
		.brand-name { font-size: 18px; font-weight: 700; color: #0f4c81; }
		.brand-sub { color: #475569; font-size: 11px; margin-top: 2px; }
		.header { width: 100%; margin-bottom: 20px; }
		.left, .right { width: 48%; vertical-align: top; display: inline-block; }
		h1 { margin: 0; font-size: 24px; color: #0f172a; }
		.muted { color: #6b7280; }
		.box { border: 1px solid #d1d5db; border-radius: 6px; padding: 10px; margin-bottom: 14px; }
		table { width: 100%; border-collapse: collapse; }
		th { background: #f3f4f6; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; }
		th, td { border: 1px solid #e5e7eb; padding: 8px; }
		.text-end { text-align: right; }
		.totals { width: 44%; margin-left: auto; margin-top: 14px; }
		.totals td { border: none; padding: 6px 0; }
		.totals .grand td { border-top: 1px solid #cbd5e1; font-weight: bold; font-size: 13px; }
		.doc-footer { margin-top: 24px; font-size: 11px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 8px; }
	</style>
</head>
<body>
	@php
		// Company identity: config/branding.php (.env), see App\Support\Branding.
		$brand = \App\Support\Branding::pdf();
		$companyName = $brand['company_name'];
		$logoPath = $brand['logo_path'];
		$hasLogo = $logoPath !== null;
		$tagline = $brand['tagline'];
		$footerParts = $brand['footer_parts'];
	@endphp

	<div class="brandbar">
		@if($hasLogo)
			<img src="{{ $logoPath }}" alt="Logo" class="brand-logo">
		@endif
		<span class="brand-name">{{ $companyName }}</span>
		<div class="brand-sub">{{ $tagline }}</div>
	</div>

	<div class="header">
		<div class="left">
			<h1>COMMANDE CLIENT</h1>
			<div class="muted">Numero: {{ $order->order_number }}</div>
			<div class="muted">Date: {{ $order->order_date ? \Illuminate\Support\Carbon::parse($order->order_date)->format('Y-m-d') : '-' }}</div>
		</div>
		<div class="right">
			<div class="box">
				<strong>Client</strong><br>
				{{ $order->client->company_name ?? '-' }}<br>
				<span class="muted">Statut: {{ \App\Support\Format::label($order->status) }}</span>
			</div>
		</div>
	</div>

	<table>
		<thead>
			<tr>
				<th style="width:34%">Article</th>
				<th class="text-end" style="width:11%">Quantite</th>
				<th class="text-end" style="width:15%">Prix HT</th>
				<th class="text-end" style="width:10%">TVA %</th>
				<th class="text-end" style="width:15%">Total HT</th>
				<th class="text-end" style="width:15%">Total TTC</th>
			</tr>
		</thead>
		<tbody>
			@foreach($order->items as $item)
				@php
					$lineTva = round(((float) $item->total_ht) * (((float) $item->tva_rate) / 100), 2);
					$lineTtc = round(((float) $item->total_ht) + $lineTva, 2);
				@endphp
				<tr>
					<td>{{ $item->product_id ? ($item->product->product_name ?? '-') : ($item->service_id ? ($item->service->service_name ?? '-') : '-') }}</td>
					<td class="text-end">{{ number_format((float) $item->quantity, 3, '.', ' ') }}</td>
					<td class="text-end">{{ number_format((float) $item->price_ht, 2, '.', ' ') }}</td>
					<td class="text-end">{{ number_format((float) $item->tva_rate, 2, '.', ' ') }}</td>
					<td class="text-end">{{ number_format((float) $item->total_ht, 2, '.', ' ') }}</td>
					<td class="text-end">{{ number_format((float) $lineTtc, 2, '.', ' ') }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>

	<table class="totals">
		<tr>
			<td><strong>Total HT</strong></td>
			<td class="text-end">{{ number_format((float) $order->total_ht, 2, '.', ' ') }} {{ $order->currency }}</td>
		</tr>
		<tr>
			<td><strong>Total TVA</strong></td>
			<td class="text-end">{{ number_format((float) $order->total_tva, 2, '.', ' ') }} {{ $order->currency }}</td>
		</tr>
		<tr class="grand">
			<td>Total TTC</td>
			<td class="text-end">{{ number_format((float) $order->total_ttc, 2, '.', ' ') }} {{ $order->currency }}</td>
		</tr>
	</table>

	<p class="doc-footer">{{ implode(' - ', $footerParts) }} - Document non contractuel sauf mention contraire.</p>
</body>
</html>
