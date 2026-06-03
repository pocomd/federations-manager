        <div class="col-12">
            <label class="form-label fw-semibold small">Driver</label>
            <select class="form-select form-select-sm" name="db_driver" disabled>
                <option>MySQL / MariaDB</option>
            </select>
            <div class="form-text">PostgreSQL support is planned for a future release.</div>
        </div>

        <div class="col-md-8">
            <label class="form-label fw-semibold small">Host</label>
            <input type="text" class="form-control form-control-sm @error('db_host') is-invalid @enderror"
                   name="db_host" value="{{ old('db_host', $existing['host'] ?? '127.0.0.1') }}" required>
            @error('db_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold small">Port</label>
            <input type="number" class="form-control form-control-sm @error('db_port') is-invalid @enderror"
                   name="db_port" value="{{ old('db_port', $existing['port'] ?? '3306') }}" min="1" max="65535" required>
            @error('db_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold small">Database name</label>
            <input type="text" class="form-control form-control-sm @error('db_database') is-invalid @enderror"
                   name="db_database" value="{{ old('db_database', $existing['database'] ?? '') }}"
                   placeholder="federation" required>
            @error('db_database')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold small">Username</label>
            <input type="text" class="form-control form-control-sm @error('db_username') is-invalid @enderror"
                   name="db_username" value="{{ old('db_username', $existing['username'] ?? '') }}"
                   autocomplete="username" required>
            @error('db_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold small">Password</label>
            <input type="password" class="form-control form-control-sm"
                   name="db_password" autocomplete="current-password">
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold small">
                Table prefix <span class="text-muted fw-normal">(optional)</span>
            </label>
            <input type="text" class="form-control form-control-sm"
                   name="db_prefix" value="{{ old('db_prefix', '') }}" placeholder="e.g. fr_">
        </div>
