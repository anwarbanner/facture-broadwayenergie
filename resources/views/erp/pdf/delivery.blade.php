<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Bon de livraison {{ $delivery->delivery_number }}</title>
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
			<h1>BON DE LIVRAISON</h1>
			<div class="muted">Numero: {{ $delivery->delivery_number }}</div>
			<div class="muted">Date: {{ $delivery->delivery_date ? \Illuminate\Support\Carbon::parse($delivery->delivery_date)->format('Y-m-d') : '-' }}</div>
			<div class="muted">Statut: {{ \App\Support\Format::label($delivery->status) }}</div>
		</div>
		<div class="right">
			<div class="box">
				<strong>Client</strong><br>
				{{ $delivery->client->company_name ?? '-' }}<br>
				<span class="muted">Commande: {{ $delivery->order->order_number ?? '-' }}</span>
			</div>
		</div>
	</div>

	<table>
		<thead>
			<tr>
				<th style="width:50%">Article</th>
				<th class="text-end" style="width:25%">Qte commandee</th>
				<th class="text-end" style="width:25%">Qte expediee</th>
			</tr>
		</thead>
		<tbody>
			@foreach($delivery->items as $item)
				<tr>
					<td>{{ $item->product_id ? ($item->product->product_name ?? '-') : ($item->service_id ? ($item->service->service_name ?? '-') : '-') }}</td>
					<td class="text-end">{{ number_format((float) $item->quantity, 3, '.', ' ') }}</td>
					<td class="text-end">{{ number_format((float) $item->shipped_qty, 3, '.', ' ') }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>

	<p class="doc-footer">{{ implode(' - ', $footerParts) }} - Document non contractuel sauf mention contraire.</p>
</body>
</html>
