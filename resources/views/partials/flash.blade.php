@if (session('status'))
    <div class="pg-alert pg-alert-success" role="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="pg-alert pg-alert-critical" role="alert">
        <strong>Please correct the following.</strong>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
