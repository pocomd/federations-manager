@auth
@php $unreadCount = \App\Models\AppNotification::where('user_id', \Illuminate\Support\Facades\Auth::id())->whereNull('read_at')->count(); @endphp
<div x-data="{ open: false }" @click.outside="open = false" class="position-relative">
    <button @click="open = !open" class="btn btn-sm btn-link position-relative p-2 text-secondary" type="button" title="Notifications">
        <i class="bi bi-bell fs-5"></i>
        @if($unreadCount > 0)
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        @endif
    </button>
    <div x-show="open" x-cloak style="position:absolute;right:0;top:calc(100% + 4px);width:320px;z-index:1050;"
         class="bg-white border rounded shadow-sm">
        @forelse(\App\Models\AppNotification::where('user_id',\Illuminate\Support\Facades\Auth::id())->whereNull('read_at')->latest('created_at')->take(5)->get() as $n)
        <div class="px-3 py-2 border-bottom small">
            <div class="fw-semibold">{{ $n->title }}</div>
            <div class="text-muted">{{ \Illuminate\Support\Str::limit($n->body, 60) }}</div>
        </div>
        @empty
        <div class="px-3 py-2 small text-muted text-center">No unread notifications</div>
        @endforelse
        <div class="px-3 py-2 d-flex justify-content-between align-items-center">
            <a href="{{ route('notifications.index') }}" class="small">View all</a>
            @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn btn-link btn-sm p-0 small">Mark all read</button>
            </form>
            @endif
        </div>
    </div>
</div>
@endauth
