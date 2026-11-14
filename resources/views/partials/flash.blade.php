@if (session('status'))
    <div class="pg-alert pg-alert-success" role="status" data-pg-alert>
        <div class="pg-alert-body">{{ session('status') }}</div>
        <button class="pg-alert-dismiss" type="button" data-pg-alert-dismiss aria-label="Dismiss alert">&times;</button>
    </div>
@endif

@if ($errors->any())
    <div class="pg-alert pg-alert-critical" role="alert" data-pg-alert>
        <div class="pg-alert-body">
            <strong>Please correct the following.</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <button class="pg-alert-dismiss" type="button" data-pg-alert-dismiss aria-label="Dismiss alert">&times;</button>
    </div>
@endif
