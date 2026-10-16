{{--
    Messages of the ERP pages, in one place for every page: confirmation after an action, refusal of an action
    (conversion, validation...) and the summary of validation errors. Before, most document pages
    (show / edit) did not display them, so a refused conversion looked like "nothing happened".
--}}
@if(request()->routeIs('erp.*', 'settings.*', 'activity-logs.*', 'users.*', 'roles.*'))
    <div class="erp-flash erp-pro-page" aria-live="polite">
        @if(session('highlight'))
            {{-- Row just created: highlighted in the list by public/assets/js/erp-ui.js --}}
            <span hidden data-erp-highlight="{{ session('highlight') }}"></span>
        @endif
        @foreach(['success' => 'alert-success', 'warning' => 'alert-warning', 'error' => 'alert-danger'] as $key => $class)
            @if(session($key))
                <div class="alert {{ $class }} alert-dismissible fade show" role="{{ $key === 'success' ? 'status' : 'alert' }}">
                    {{ session($key) }}
                    <button type="button" class="close btn-close" data-dismiss="alert" data-bs-dismiss="alert" aria-label="Fermer"><span aria-hidden="true">&times;</span></button>
                </div>
            @endif
        @endforeach
        @if(isset($errors) && $errors->any())
            <div class="alert alert-danger" role="alert">
                @if($errors->count() > 1)
                    <strong>Corrigez les points suivants :</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                    </ul>
                @else
                    {{ $errors->first() }}
                @endif
            </div>
        @endif
    </div>
@endif
