@if (session('status'))
    <div class="alert alert-ok" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-error" role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
