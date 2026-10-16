{{--
    Live account ledger. Params: $ledger (StatementService::build), $documentRoute ('erp.client-invoices.show' | 'erp.supplier-invoices.show'),
    $owedLabel (what the balance means, e.g. "Montant du par le client")
--}}
<div class="erp-doc-lines mt-3">
    <h6>Mouvements de la periode</h6>
    <div class="table-responsive">
        <table class="table table-sm erp-lines-table mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Operation</th>
                    <th>Reference</th>
                    <th class="text-end">Debit (+)</th>
                    <th class="text-end">Credit (-)</th>
                    <th class="text-end">Solde</th>
                </tr>
            </thead>
            <tbody>
                <tr class="table-light">
                    <td colspan="5"><strong>Solde d'ouverture</strong></td>
                    <td class="text-end"><strong>{{ \App\Support\Format::money($ledger['opening']) }}</strong></td>
                </tr>
                @forelse($ledger['lines'] as $line)
                    <tr>
                        <td>{{ $line['date']->format('d/m/Y') }}</td>
                        <td>{{ $line['label'] }}</td>
                        <td>
                            @if($line['document'])
                                <a href="{{ route($documentRoute, $line['document']) }}">{{ $line['reference'] }}</a>
                            @else
                                {{ $line['reference'] }}
                            @endif
                        </td>
                        <td class="text-end">{{ $line['amount'] > 0 ? \App\Support\Format::money($line['amount']) : '' }}</td>
                        <td class="text-end">{{ $line['amount'] < 0 ? \App\Support\Format::money(-$line['amount']) : '' }}</td>
                        <td class="text-end">{{ \App\Support\Format::money($line['balance']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">Aucun mouvement sur la periode.</td></tr>
                @endforelse
                <tr class="table-light">
                    <td colspan="5"><strong>Solde de cloture</strong> <small class="text-muted">({{ $owedLabel }} ; negatif = a rembourser)</small></td>
                    <td class="text-end"><strong>{{ \App\Support\Format::money($ledger['closing']) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
    <small class="text-muted">Calcule en direct a partir des factures validees, avoirs et reglements (reglements annules exclus).</small>
</div>
