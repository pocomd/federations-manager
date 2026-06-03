@extends('layouts.app')

@section('title', 'Notification Settings')

@section('content')

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0">Notification Settings</h1>
            <p class="text-muted small mb-0">Choose how you receive notifications</p>
        </div>
    </div>

    <form method="POST" action="{{ route('profile.notifications.update') }}">
        @csrf

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="small">Event</th>
                            <th class="small">Description</th>
                            <th class="small text-center">In-app</th>
                            <th class="small text-center">Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($types as $type)
                        @php
                            $pref = $prefs->get($type->id);
                            $viaUi    = $pref !== null ? $pref->via_ui    : $type->default_via_ui;
                            $viaEmail = $pref !== null ? $pref->via_email : $type->notify_email;
                        @endphp
                        <tr>
                            <td class="small fw-semibold">{{ $type->label }}</td>
                            <td class="small text-muted">{{ $type->description }}</td>
                            <td class="text-center">
                                <input type="checkbox"
                                       name="via_ui_{{ $type->id }}"
                                       class="form-check-input"
                                       value="1"
                                       {{ $viaUi ? 'checked' : '' }}>
                            </td>
                            <td class="text-center">
                                @if($type->notify_email)
                                <input type="checkbox"
                                       name="via_email_{{ $type->id }}"
                                       class="form-check-input"
                                       value="1"
                                       {{ $viaEmail ? 'checked' : '' }}>
                                @else
                                <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end">
                <button type="submit" class="btn btn-primary btn-sm">Save preferences</button>
            </div>
        </div>

    </form>

</div>

@endsection
